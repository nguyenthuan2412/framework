const cluster = require('node:cluster');

const workers = Number(process.env.WORKERS || 4);
const app = process.argv[2];

if (cluster.isPrimary) {
  for (let i = 0; i < workers; i++) cluster.fork();
  cluster.on('exit', () => cluster.fork());
  console.log(`${app}: ${workers} workers on :8080`);
} else {
  require(`./${app}.js`);
}
