import { pool, now } from '../../lib/pg';

export const dynamic = 'force-dynamic';

export async function GET() {
    return Response.json({
        message: 'Hello from Node.js Next.js!',
        time: now(),
    });
}