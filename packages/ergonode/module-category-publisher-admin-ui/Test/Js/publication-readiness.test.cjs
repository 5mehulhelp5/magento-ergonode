const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname,
    '../../view/adminhtml/web/js/publication-readiness.js'), 'utf8');

function fixture(responses, initial = {ready: false, message: 'Disabled', configuration_url: '/config'}) {
    let factory;
    let requests = 0;
    const changes = [];
    vm.runInNewContext(source, {Promise, define(_deps, init) {
        factory = init((s) => s, {post: () => {
            requests++;
            const response = responses.shift();
            return response instanceof Error ? Promise.reject(response) : Promise.resolve(response);
        }});
    }});
    const readiness = factory({write_readiness: initial, urls: {status: '/status'}},
        (state) => changes.push(state));
    return {readiness, changes, requests: () => requests};
}

test('disabled configuration stays blocked despite an authenticated REST session', async () => {
    const f = fixture([{success: true, authenticated: true, write_readiness: {ready: false, message: 'Missing key'}}]);
    assert.equal(f.readiness.isReady(), false);
    assert.equal(await f.readiness.ensure(), false);
    assert.equal(f.changes.at(-1).message, 'Missing key');
});

test('rechecking completed configuration enables the existing screen; stale readiness is checked again', async () => {
    const f = fixture([
        {success: true, write_readiness: {ready: true, message: ''}},
        {success: true, write_readiness: {ready: false, message: 'Disabled'}}
    ]);
    assert.equal(await f.readiness.ensure(), true);
    assert.equal(f.readiness.isReady(), true);
    assert.equal(await f.readiness.ensure(), false);
});

test('unavailable or incomplete readiness response fails closed and concurrent checks share a request', async () => {
    const f = fixture([new Error('Connection failed'), {success: true, authenticated: true}]);
    const first = f.readiness.ensure();
    assert.equal(first, f.readiness.ensure());
    assert.equal(await first, false);
    assert.equal(f.requests(), 1);
    assert.equal(await f.readiness.ensure(), false);
    assert.equal(f.readiness.isReady(), false);
});
