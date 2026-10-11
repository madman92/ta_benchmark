import { pool, now } from './pg';


export async function runBench(parallel = false) {
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

    return Response.json({
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
    });
}

async function runQueries(queries: Array<() => Promise<import('pg').QueryResult>>, parallel: boolean) {
    if (parallel) return Promise.all(queries.map(query => query()));
    const results: import('pg').QueryResult[] = [];
    for (const query of queries) results.push(await query());
    return results;
}
