// Run real JS/TS controllers with a query spy; never open sockets or a database.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const ts = require(path.join(root, 'nodejs-nestjs/node_modules/typescript'));
const [source, mode] = process.argv.slice(2);
const normalize = sql => sql.trim().replace(/;$/, '').replace(/\$\d+/g, '?').replace(/\s+/g, ' ').toLowerCase();
const queries = JSON.parse(fs.readFileSync(path.join(__dirname, 'workload.json'))).map(normalize);
const trace = [];
let sequence = 0;
const pool = { async query(sql, params = []) {
    sql = normalize(sql);
    const id = sequence++;
    trace.push({event: 'start', id, sql, params});
    // Yield deterministically so Promise.all launches siblings before completion.
    await new Promise(resolve => setImmediate(resolve));
    trace.push({event: 'end', id});
    let rows;
    if (sql === 'select pg_sleep(0.01)') rows = [{}];
    else switch (queries.indexOf(sql)) {
        case 0: rows = [{id: params[0], name: `User ${params[0]}`, email: 'fixture@bench.test'}]; break;
        case 1: rows = Array.from({length: 10}, () => ({id: 900 + params[0], title: 'Post', views_count: 0})); break;
        case 2: case 3: case 5: rows = Array.from({length: 5}, () => ({id: 1})); break;
        case 6: rows = Array.from({length: 10}, () => ({post_id: 1})); break;
        case 7: case 8: rows = []; break;
        case 9: rows = [{total_views: 1, last_view: '2000-01-02'}]; break;
        default: throw new Error(`Unexpected SQL: ${sql}`);
    }
    return {rows, rowCount: rows.length};
}};
const routes = new Map();
const app = {disable() {}, get(route, handler) { routes.set(route, handler); }, listen() {}};
const dependencies = {
    'node:cluster': {isPrimary: false},
    express: () => app,
    pg: {Pool: function () { return pool; }},
    '@nestjs/common': {Inject: () => () => {}, Injectable: () => target => target},
    '../pg/pg.module': {PG_POOL: 'test-pool'},
    './pg': {pool, now: () => new Date().toISOString()},
    '../../lib/pg': {pool, now: () => new Date().toISOString()},
};
function load(file) {
    const compiled = ts.transpileModule(fs.readFileSync(file, 'utf8'), {
        compilerOptions: {module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2021,
            experimentalDecorators: true, esModuleInterop: true},
        fileName: file, reportDiagnostics: true,
    });
    const errors = compiled.diagnostics.filter(d => d.category === ts.DiagnosticCategory.Error);
    if (errors.length) throw new Error(ts.formatDiagnosticsWithColorAndContext(errors, {
        getCanonicalFileName: name => name, getCurrentDirectory: () => root, getNewLine: () => '\n',
    }));
    const module = {exports: {}};
    vm.runInNewContext(compiled.outputText, {
        module, exports: module.exports,
        require(name) {
            if (!(name in dependencies)) throw new Error(`Unexpected dependency: ${name}`);
            return dependencies[name];
        },
        Response: {json: body => body}, process: {env: {}}, console: {log() {}},
    }, {filename: file});
    return module.exports;
}
async function main() {
    let body;
    if (source.includes('nodejs-express')) {
        load(source);
        const route = mode === 'test' ? '/test' : mode === 'async' ? '/bench-async' : '/bench';
        await routes.get(route)({}, {json(value) { body = value; }});
    } else if (source.includes('nodejs-nestjs') || path.basename(source) === 'bench.service.ts') {
        const service = new (load(source).BenchService)(pool);
        body = mode === 'test' ? await service.test() : await service.bench(mode === 'async');
    } else {
        body = mode === 'test'
            ? await load(path.join(root, 'nodejs-nextjs/app/test/route.ts')).GET()
            : await load(source).runBench(mode === 'async');
    }
    process.stdout.write(JSON.stringify({body, trace}));
}
main().catch(error => { console.error(error); process.exitCode = 1; });
