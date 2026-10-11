import { arrival, thresholds } from './common.js';
export { testHello, testDb } from './common.js';

const rate = Number(__ENV.RATE || 2000);
if (!Number.isInteger(rate) || rate < 2) throw new Error('Mixed RATE must be an integer of at least 2');
const helloRate = Math.floor(rate * 0.7);

export const options = {
    scenarios: {
        hello: arrival(helloRate, 'testHello'),
        db: arrival(rate - helloRate, 'testDb'),
    },
    thresholds: {
        ...thresholds(Number(__ENV.P95_MS || 200)),
        'http_req_duration{scenario:hello}': ['p(95)<200'],
        'http_req_duration{scenario:db}': ['p(95)<200'],
    },
};
export { handleSummary } from './report.js';
