import http from 'k6/http';
import { check } from 'k6';

export let baseURL = __ENV.BASE_URL || 'http://localhost:8083';
export function setDefaultURL(url) { baseURL = __ENV.BASE_URL || url; }
export const mode = __ENV.MODE || 'sequential';
if (!['sequential', 'async'].includes(mode)) throw new Error('Invalid MODE');
export const benchPath = mode === 'async' ? '/bench-async' : '/bench';
const params = { timeout: __ENV.REQUEST_TIMEOUT || '10s', redirects: 0 };
let failedSamples = 0;

export function validBench(body, expectedMode) {
    if (!body || typeof body !== 'object') return false;
    const user = /^User ([1-9][0-9]?|100)$/.exec(body.user);
    return !!user && body.other_user === `User ${Number(user[1]) % 100 + 1}` &&
        body.posts === 10 && body.other_posts === 5 && body.views === 1 &&
        body.top_posts === 5 && body.leaderboard === 5 && body.recent_views === 10 &&
        body.queries === 10 && body.mode === expectedMode && typeof body.time === 'string';
}

function request(path, validate) {
    const response = http.get(`${baseURL}${path}`, params);
    let body;
    try { body = response.json(); } catch (_) { body = null; }
    const valid = validate(body);
    // Cap diagnostics at three samples from each of the first three VUs.
    if ((response.status !== 200 || !valid) && __VU <= 3 && failedSamples < 3) {
        failedSamples++;
        console.warn(JSON.stringify({
            event: 'invalid_response', path, status: response.status,
            content_type: response.headers['Content-Type'],
            error: response.error, error_code: response.error_code,
            body: String(response.body || '').slice(0, 2048),
        }));
    }
    check(response, {
        [`${path} status 200`]: r => r.status === 200,
        [`${path} valid body`]: () => valid,
    });
}
export function testHello() {
    request('/hello', b => !!b && typeof b.message === 'string' && typeof b.time === 'string');
}
export function testDb() {
    request('/test', b => !!b && b.ok === true && typeof b.time === 'string');
}
export function bench() {
    request(benchPath, b => validBench(b, mode));
}
export function warmup() {
    testHello();
    testDb();
    bench();
}

export function arrival(rate, exec) {
    return {
        executor: 'constant-arrival-rate', rate, timeUnit: '1s',
        duration: __ENV.DURATION || '30s',
        preAllocatedVUs: Number(__ENV.VUS || 1000),
        maxVUs: Number(__ENV.VUS || 1000),
        gracefulStop: __ENV.REQUEST_TIMEOUT || '10s', exec,
    };
}
export function thresholds(p95) {
    return {
        http_req_failed: ['rate<0.01'],
        checks: ['rate==1'],
        dropped_iterations: ['count==0'],
        http_req_duration: [`p(95)<${p95}`],
    };
}
