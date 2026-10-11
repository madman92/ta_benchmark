export { warmup } from './common.js';
export { handleSummary } from './report.js';
export const options = {
    scenarios: {
        warmup: {
            executor: 'constant-vus', vus: Number(__ENV.WARMUP_VUS || 64),
            duration: __ENV.WARMUP_DURATION || '5s', gracefulStop: '10s', exec: 'warmup',
        },
    },
    thresholds: { checks: ['rate==1'], http_req_failed: ['rate==0'] },
};
