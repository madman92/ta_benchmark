const cluster = require('node:cluster');
const http = require('node:http');
const path = require('node:path');

const WORKERS = parseInt(process.env.WORKERS || '16', 10);
const PORT = parseInt(process.env.PORT || '8080', 10);
const INTERNAL_BASE = parseInt(process.env.INTERNAL_BASE || '8100', 10);
const STANDALONE_SERVER = path.join(__dirname, '.next', 'standalone', 'server.js');

const portByWorker = new Map();
let forkSeq = 0;

const spawn = () => {
    let port;
    for (let i = 0; i < WORKERS; i++) {
        const candidate = INTERNAL_BASE + ((forkSeq + i) % WORKERS);
        if (![...portByWorker.values()].includes(candidate)) {
            port = candidate;
            break;
        }
    }
    if (port === undefined) port = INTERNAL_BASE + (forkSeq % WORKERS);
    forkSeq++;
    const worker = cluster.fork({ PORT: String(port), HOSTNAME: '0.0.0.0' });
    portByWorker.set(worker.id, port);
    return worker;
};

if (cluster.isPrimary) {
    console.log(`Master ${process.pid} starting ${WORKERS} standalone Next.js instances + proxy on :${PORT}`);
    for (let i = 0; i < WORKERS; i++) spawn();
    cluster.on('exit', (worker) => {
        portByWorker.delete(worker.id);
        console.log(`Worker ${worker.process.pid} died, forking replacement`);
        spawn();
    });

    const agent = new http.Agent({ keepAlive: true, maxSockets: Infinity });
    let next = 0;

    const server = http.createServer((req, res) => {
        const ports = [...portByWorker.values()];
        if (ports.length === 0) {
            res.writeHead(502);
            res.end();
            return;
        }
        const port = ports[next % ports.length];
        next++;
        const upstream = http.request(
            {
                host: '127.0.0.1',
                port,
                path: req.url,
                method: req.method,
                headers: req.headers,
                agent,
            },
            (upRes) => {
                res.writeHead(upRes.statusCode, upRes.headers);
                upRes.pipe(res);
            }
        );
        upstream.on('error', () => {
            res.writeHead(502);
            res.end();
        });
        req.pipe(upstream);
    });
    server.listen(PORT, '0.0.0.0');
} else {
    import(STANDALONE_SERVER).catch((err) => {
        console.error('Failed to start standalone Next.js server', err);
        process.exit(1);
    });
}