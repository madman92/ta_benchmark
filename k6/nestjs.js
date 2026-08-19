import http from 'k6/http';
import { check } from 'k6';

const BASE_URL = 'http://localhost:8091';

export const options = {
    scenarios: {
        hello_rps: {
            executor: 'constant-arrival-rate',
            rate: 840,
            timeUnit: '1s',
            duration: '30s',
            preAllocatedVUs: 100,
            maxVUs: 500,
            gracefulStop: '2s',
            exec: 'testHello',
        },
        test_rps: {
            executor: 'constant-arrival-rate',
            rate: 360,
            timeUnit: '1s',
            duration: '30s',
            preAllocatedVUs: 100,
            maxVUs: 500,
            gracefulStop: '2s',
            exec: 'testDb',
        },
    },
    thresholds: {
        http_req_failed:   ['rate<0.01'],
        http_req_duration: ['p(95)<200'],
    },
};

export function testHello() {
    const r = http.get(`${BASE_URL}/hello`);
    check(r, { 'hello 200': (r) => r.status === 200 });
}

export function testDb() {
    const r = http.get(`${BASE_URL}/test`);
    check(r, { 'test 200': (r) => r.status === 200 });
}