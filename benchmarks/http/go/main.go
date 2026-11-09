package main

import (
	"database/sql"
	"math/rand/v2"
	"net/http"

	"github.com/gin-gonic/gin"
	_ "github.com/go-sql-driver/mysql"
)

type World struct {
	ID           int `json:"id"`
	RandomNumber int `json:"randomNumber"`
}

const query = "SELECT id, randomNumber FROM world WHERE id = ?"

var db *sql.DB

func one() (World, error) {
	var w World
	err := db.QueryRow(query, rand.IntN(10000)+1).Scan(&w.ID, &w.RandomNumber)
	return w, err
}

func main() {
	var err error
	db, err = sql.Open("mysql", "bench:bench@tcp(mysql:3306)/bench")
	if err != nil {
		panic(err)
	}
	// Go chạy 1 process đa luồng: 60 kết nối = 4 worker x 15 của Ryra/Node
	db.SetMaxOpenConns(60)
	db.SetMaxIdleConns(60)

	gin.SetMode(gin.ReleaseMode)
	r := gin.New() // không gắn Logger/Recovery mặc định (Node cũng không log request)

	r.GET("/plaintext", func(c *gin.Context) {
		c.String(http.StatusOK, "Hello, World!")
	})

	r.GET("/json", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "Hello, World!"})
	})

	r.GET("/db", func(c *gin.Context) {
		w, err := one()
		if err != nil {
			c.Status(http.StatusInternalServerError)
			return
		}
		c.JSON(http.StatusOK, w)
	})

	// 20 query tuần tự, giống Ryra/Node
	r.GET("/queries", func(c *gin.Context) {
		out := make([]World, 0, 20)
		for i := 0; i < 20; i++ {
			w, err := one()
			if err != nil {
				c.Status(http.StatusInternalServerError)
				return
			}
			out = append(out, w)
		}
		c.JSON(http.StatusOK, out)
	})

	r.Run(":8080")
}
