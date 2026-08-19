import { Inject, Injectable } from '@nestjs/common';
import { Pool } from 'pg';
import { PG_POOL } from '../pg/pg.module';

const now = () => new Date().toISOString();

@Injectable()
export class BenchService {
    private peakHeap = 0;

    constructor(@Inject(PG_POOL) private readonly pool: Pool) {}

    async hello() {
        return {
            message: 'Hello from Node.js NestJS!',
            time: now(),
        };
    }

    async test() {
        await this.pool.query('SELECT pg_sleep(0.01)');

        return {
            ok: true,
            time: now(),
        };
    }

    async bench() {
        const userId = Math.floor(Math.random() * 100) + 1;
        const otherUserId = (userId % 100) + 1;

        const [user, posts, topPosts, leaderboard, otherUser, otherPosts, recentViews] = await Promise.all([
            this.pool.query('SELECT id, name, email FROM users WHERE id = $1', [userId]),
            this.pool.query('SELECT id, title, views_count FROM posts WHERE user_id = $1 ORDER BY created_at DESC LIMIT 10', [userId]),
            this.pool.query('SELECT id, title, views_count FROM posts ORDER BY views_count DESC LIMIT 5'),
            this.pool.query('SELECT user_id, COUNT(*)::int AS post_count FROM posts GROUP BY user_id ORDER BY post_count DESC LIMIT 5'),
            this.pool.query('SELECT id, name, email FROM users WHERE id = $1', [otherUserId]),
            this.pool.query('SELECT id, title FROM posts WHERE user_id = $1 LIMIT 5', [otherUserId]),
            this.pool.query('SELECT post_id, ip_address, viewed_at FROM post_views ORDER BY viewed_at DESC LIMIT 10'),
        ]);

        const postId = posts.rows.length > 0 ? posts.rows[0].id : 1;

        await Promise.all([
            this.pool.query(
                'INSERT INTO post_views (post_id, ip_address, viewed_at) VALUES ($1, $2, NOW())',
                [postId, `10.0.${Math.floor(Math.random() * 256)}.${Math.floor(Math.random() * 256)}`]
            ),
            this.pool.query(
                'UPDATE posts SET views_count = views_count + 1 WHERE id = $1', [postId]
            ),
        ]);

        const stats = await this.pool.query(
            'SELECT COUNT(*)::int AS total_views, MAX(viewed_at) AS last_view FROM post_views WHERE post_id = $1',
            [postId]
        );

        const heapUsed = process.memoryUsage().heapUsed;
        this.peakHeap = Math.max(this.peakHeap, heapUsed);

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
            memory_usage: heapUsed,
            memory_peak: this.peakHeap,
            time: now(),
        };
    }

    async connections() {
        const byState = await this.pool.query(`
            SELECT state, count(*)::int AS cnt
            FROM pg_stat_activity
            WHERE datname = current_database()
            GROUP BY state
            ORDER BY cnt DESC
        `);

        const limit = await this.pool.query(
            "SELECT current_setting('max_connections')::int AS max_connections"
        );

        return {
            max_connections: limit.rows[0].max_connections,
            total: byState.rows.reduce((sum, r) => sum + r.cnt, 0),
            by_state: byState.rows,
            time: now(),
        };
    }
}
