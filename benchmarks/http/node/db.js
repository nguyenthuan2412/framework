const mysql = require('mysql2/promise');

// Pool 15 kết nối / worker, giống pool_options.max_connections của Ryra
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'mysql',
  user: 'bench',
  password: 'bench',
  database: 'bench',
  connectionLimit: 15,
});

const SQL = 'SELECT id, randomNumber FROM world WHERE id = ?';
const randomId = () => Math.floor(Math.random() * 10000) + 1;

async function one() {
  const [rows] = await pool.query(SQL, [randomId()]);
  return rows[0];
}

// 20 query chạy tuần tự, giống bên Ryra
async function many() {
  const out = [];
  for (let i = 0; i < 20; i++) out.push(await one());
  return out;
}

module.exports = { one, many };
