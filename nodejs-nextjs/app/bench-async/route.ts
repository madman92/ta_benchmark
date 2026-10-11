import { runBench } from '../../lib/bench';

export const dynamic = 'force-dynamic';

export async function GET() {
    return runBench(true);
}
