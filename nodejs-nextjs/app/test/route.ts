import { pool, now } from '../../lib/pg';

export const dynamic = 'force-dynamic';

export async function GET() {
    await pool.query('SELECT pg_sleep(0.01)');

    return Response.json({
        ok: true,
        time: now(),
    });
}