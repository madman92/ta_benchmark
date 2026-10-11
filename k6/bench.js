import { arrival, thresholds } from './common.js';
export { bench } from './common.js';

export const options = {
    scenarios: { bench: arrival(Number(__ENV.RATE || 2000), 'bench') },
    thresholds: thresholds(Number(__ENV.P95_MS || 500)),
};
export { handleSummary } from './report.js';
