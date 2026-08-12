import cluster from 'node:cluster';
import express from 'express';
import pg from 'pg';

const WORKERS = parseInt(process.env.WORKERS || '16', 10);
const PORT = parseInt(process.env.PORT || '8080', 10);

const DB_CONFIG = {
    host: process.env.DB_HOST || 'db',
    port: parseInt(process.env.DB_PORT || '5432', 10),
    database: process.env.DB_NAME || 'express',
    user: process.env.DB_USER || 'express',
    password: process.env.DB_PASSWORD || 'secret',
    min: 2,
    max: 10,
};

const now = () => new Date().toISOString();

function buildApp() {
    const pool = new pg.Pool(DB_CONFIG);
    let peakHeap = 0;

    const app = express();
    app.disable('x-powered-by');

    app.get('/hello', async (req, res) => {
        res.json({
            message: 'Hello from Node.js Express!',
            time: now(),
        });
    });

    app.get('/test', async (req, res) => {
        await pool.query('SELECT pg_sleep(0.01)');

        res.json({
            ok: true,
            time: now(),
        });
    });

    app.get('/bench', async (req, res) => {
        const userId = Math.floor(Math.random() * 100) + 1;

        const user = await pool.query(
            'SELECT id, name, email FROM users WHERE id = $1', [userId]
        );

        const posts = await pool.query(
            'SELECT id, title, views_count FROM posts WHERE user_id = $1 ORDER BY created_at DESC LIMIT 10',
            [userId]
        );

        const postId = posts.rows.length > 0 ? posts.rows[0].id : 1;

        await pool.query(
            'INSERT INTO post_views (post_id, ip_address, viewed_at) VALUES ($1, $2, NOW())',
            [postId, `10.0.${Math.floor(Math.random() * 256)}.${Math.floor(Math.random() * 256)}`]
        );

        await pool.query(
            'UPDATE posts SET views_count = views_count + 1 WHERE id = $1', [postId]
        );

        const stats = await pool.query(
            'SELECT COUNT(*)::int AS total_views, MAX(viewed_at) AS last_view FROM post_views WHERE post_id = $1',
            [postId]
        );

        const topPosts = await pool.query(
            'SELECT id, title, views_count FROM posts ORDER BY views_count DESC LIMIT 5'
        );

        const leaderboard = await pool.query(
            'SELECT user_id, COUNT(*)::int AS post_count FROM posts GROUP BY user_id ORDER BY post_count DESC LIMIT 5'
        );

        const otherUserId = (userId % 100) + 1;
        const otherUser = await pool.query(
            'SELECT id, name, email FROM users WHERE id = $1', [otherUserId]
        );

        const otherPosts = await pool.query(
            'SELECT id, title FROM posts WHERE user_id = $1 LIMIT 5', [otherUserId]
        );

        const recentViews = await pool.query(
            'SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC LIMIT 10'
        );

        const heapUsed = process.memoryUsage().heapUsed;
        peakHeap = Math.max(peakHeap, heapUsed);

        res.json({
            user: user.rows[0]?.name ?? null,
            posts: posts.rows.length,
            views: Number(stats.rows[0]?.total_views ?? 0),
            top_posts: topPosts.rows.length,
            leaderboard: leaderboard.rows.length,
            other_user: otherUser.rows[0]?.name ?? null,
            other_posts: otherPosts.rows.length,
            recent_views: recentViews.rows.length,
            queries: 10,
            memory_usage: heapUsed,
            memory_peak: peakHeap,
            time: now(),
        });
    });

    app.get('/debug/connections', async (req, res) => {
        const byState = await pool.query(`
            SELECT state, count(*)::int AS cnt
            FROM pg_stat_activity
            WHERE datname = current_database()
            GROUP BY state
            ORDER BY cnt DESC
        `);

        const limit = await pool.query(
            "SELECT current_setting('max_connections')::int AS max_connections"
        );

        res.json({
            max_connections: limit.rows[0].max_connections,
            total: byState.rows.reduce((sum, r) => sum + r.cnt, 0),
            by_state: byState.rows,
            time: now(),
        });
    });

    return app;
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
    buildApp().listen(PORT, '0.0.0.0');
}