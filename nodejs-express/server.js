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

    async function bench(parallel = false) {
        const userId = Math.floor(Math.random() * 100) + 1;
        const otherUserId = (userId % 100) + 1;

        const [user, posts, topPosts, leaderboard, otherUser, otherPosts, recentViews] = await runQueries([
            () => pool.query('SELECT id, name, email FROM users WHERE id = $1', [userId]),
            () => pool.query('SELECT id, title, views_count FROM posts WHERE user_id = $1 ORDER BY created_at DESC, id DESC LIMIT 10', [userId]),
            () => pool.query('SELECT id, title, views_count FROM posts ORDER BY views_count DESC, id DESC LIMIT 5'),
            () => pool.query('SELECT user_id, COUNT(*) AS post_count FROM posts GROUP BY user_id ORDER BY post_count DESC, user_id ASC LIMIT 5'),
            () => pool.query('SELECT id, name, email FROM users WHERE id = $1', [otherUserId]),
            () => pool.query('SELECT id, title FROM posts WHERE user_id = $1 ORDER BY id LIMIT 5', [otherUserId]),
            () => pool.query('SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC, id DESC LIMIT 10'),
        ], parallel);

        const postId = posts.rows.length > 0 ? posts.rows[0].id : 1;

        await runQueries([
            () => pool.query(
                'UPDATE post_views SET ip_address = $1, viewed_at = NOW() WHERE id = $2',
                [`10.0.${Math.floor(Math.random() * 256)}.${Math.floor(Math.random() * 256)}`, postId]
            ),
            () => pool.query(
                'UPDATE posts SET views_count = views_count + 1 WHERE id = $1', [postId]
            ),
        ], parallel);

        const stats = await pool.query(
            'SELECT COUNT(*) AS total_views, MAX(viewed_at) AS last_view FROM post_views WHERE post_id = $1',
            [postId]
        );

        return {
            user: user.rows[0]?.name ?? null,
            posts: posts.rows.length,
            views: Number(stats.rows[0]?.total_views ?? 0),
            top_posts: topPosts.rows.length,
            leaderboard: leaderboard.rows.length,
            other_user: otherUser.rows[0]?.name ?? null,
            other_posts: otherPosts.rows.length,
            recent_views: recentViews.rows.length,
            queries: 10,
            mode: parallel ? 'async' : 'sequential',
            time: now(),
        };
    }

    app.get('/bench', async (req, res) => res.json(await bench(false)));
    app.get('/bench-async', async (req, res) => res.json(await bench(true)));

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

    app.get('/debug/runtime', (req, res) => res.json({ version: process.version, versions: process.versions, workers: WORKERS, pool_max: DB_CONFIG.max }));

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
async function runQueries(queries, parallel) {
    if (parallel) return Promise.all(queries.map(query => query()));
    const results = [];
    for (const query of queries) results.push(await query());
    return results;
}
