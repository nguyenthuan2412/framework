FROM golang:1-alpine AS build
WORKDIR /src
COPY main.go ./
RUN go mod init bench && go get github.com/gin-gonic/gin github.com/go-sql-driver/mysql \
    && go mod tidy && go list -m github.com/gin-gonic/gin \
    && CGO_ENABLED=0 go build -ldflags="-s -w" -o /bench .

FROM alpine:3.22
COPY --from=build /bench /bench
ENV GOMAXPROCS=4
EXPOSE 8080
CMD ["/bench"]
