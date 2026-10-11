export const dynamic = 'force-dynamic';

export async function GET() {
    return Response.json({ version: process.version, versions: process.versions, pool_max: 10 });
}
