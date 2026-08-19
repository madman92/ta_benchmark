import { Global, Module } from '@nestjs/common';
import { Pool } from 'pg';

export const PG_POOL = 'PG_POOL';

const poolFactory = () =>
    new Pool({
        host: process.env.DB_HOST || 'db',
        port: parseInt(process.env.DB_PORT || '5432', 10),
        database: process.env.DB_NAME || 'nest',
        user: process.env.DB_USER || 'nest',
        password: process.env.DB_PASSWORD || 'secret',
        min: 2,
        max: 10,
    });

@Global()
@Module({
    providers: [{ provide: PG_POOL, useFactory: poolFactory }],
    exports: [PG_POOL],
})
export class PgModule {}
