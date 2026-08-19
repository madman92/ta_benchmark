const crypto = require('node:crypto');
const { Client } = require('pg');

const client = new Client({
    host: process.env.DB_HOST || 'db',
    port: parseInt(process.env.DB_PORT || '5432', 10),
    database: process.env.DB_NAME || 'next',
    user: process.env.DB_USER || 'next',
    password: process.env.DB_PASSWORD || 'secret',
});

async function main() {
    await client.connect();

    await client.query('DROP TABLE IF EXISTS post_views');
    await client.query('DROP TABLE IF EXISTS posts');
    await client.query('DROP TABLE IF EXISTS users');

    await client.query(`
        CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )
    `);

    await client.query(`
        CREATE TABLE posts (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            views_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )
    `);

    await client.query('CREATE INDEX idx_posts_user_id ON posts (user_id)');

    await client.query(`
        CREATE TABLE post_views (
            id SERIAL PRIMARY KEY,
            post_id INT NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            ip_address VARCHAR(45) NOT NULL,
            viewed_at TIMESTAMP DEFAULT NOW()
        )
    `);

    await client.query('CREATE INDEX idx_post_views_post_id ON post_views (post_id)');

    const password = crypto.scryptSync('password', 'bench-salt', 32).toString('hex');

    console.log('Seeding users...');
    for (let i = 1; i <= 100; i++) {
        await client.query(
            'INSERT INTO users (name, email, password, created_at, updated_at) VALUES ($1, $2, $3, NOW(), NOW())',
            [`User ${i}`, `user${i}@bench.test`, password]
        );
    }

    console.log('Seeding posts...');
    for (let i = 1; i <= 1000; i++) {
        await client.query(
            'INSERT INTO posts (user_id, title, body, views_count, created_at, updated_at) VALUES ($1, $2, $3, $4, NOW(), NOW())',
            [
                Math.floor(Math.random() * 100) + 1,
                `Post title ${i}`,
                `Body content for post ${i}. Lorem ipsum dolor sit amet.`,
                Math.floor(Math.random() * 501),
            ]
        );
    }

    console.log('Done.');
    await client.end();
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});