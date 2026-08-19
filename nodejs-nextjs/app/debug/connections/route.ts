import { pool, now } from '../../../lib/pg';

export const dynamic = 'force-dynamic';

export async function GET() {
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

    return Response.json({
        max_connections: limit.rows[0].max_connections,
        total: byState.rows.reduce((sum, r) => sum + r.cnt, 0),
        by_state: byState.rows,
        time: now(),
    });
}