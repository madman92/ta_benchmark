export function handleSummary(data) {
    if (!__ENV.SUMMARY_FILE) return { stdout: 'Run complete; set SUMMARY_FILE to retain metrics.\n' };
    return { [__ENV.SUMMARY_FILE]: JSON.stringify(data, null, 2) };
}
