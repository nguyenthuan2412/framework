const fastify = require('fastify')();
const db = require('./db');

fastify.get('/plaintext', async (req, reply) => {
  reply.type('text/plain; charset=utf-8');
  return 'Hello, World!';
});

fastify.get('/json', async () => ({ message: 'Hello, World!' }));

fastify.get('/db', async () => db.one());

fastify.get('/queries', async () => db.many());

fastify.listen({ port: 8080, host: '0.0.0.0' });
