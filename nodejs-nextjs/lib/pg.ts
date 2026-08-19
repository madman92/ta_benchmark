import { Pool } from 'pg';

export const pool = new Pool({
    host: process.env.DB_HOST || 'db',
    port: parseInt(process.env.DB_PORT || '5432', 10),
    database: process.env.DB_NAME || 'next',
    user: process.env.DB_USER || 'next',
    password: process.env.DB_PASSWORD || 'secret',
    min: 2,
    max: 10,
});

export const now = () => new Date().toISOString();