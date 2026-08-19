import cluster from 'node:cluster';
import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';

const WORKERS = parseInt(process.env.WORKERS || '16', 10);
const PORT = parseInt(process.env.PORT || '8080', 10);

async function bootstrap() {
    const app = await NestFactory.create(AppModule, { logger: false });
    app.getHttpAdapter().getInstance().disable('x-powered-by');
    await app.listen(PORT, '0.0.0.0');
}

if (cluster.isPrimary) {
    console.log(`Master ${process.pid} starting ${WORKERS} workers`);
    for (let i = 0; i < WORKERS; i++) {
        cluster.fork();
    }
    cluster.on('exit', (worker) => {
        console.log(`Worker ${worker.process.pid} died, forking replacement`);
        cluster.fork();
    });
} else {
    bootstrap();
}
