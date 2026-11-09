const express = require('express');
const db = require('./db');

const app = express();

app.get('/plaintext', (req, res) => {
  res.type('text/plain').send('Hello, World!');
});

app.get('/json', (req, res) => {
  res.json({ message: 'Hello, World!' });
});

app.get('/db', async (req, res) => {
  res.json(await db.one());
});

app.get('/queries', async (req, res) => {
  res.json(await db.many());
});

app.listen(8080, '0.0.0.0');
