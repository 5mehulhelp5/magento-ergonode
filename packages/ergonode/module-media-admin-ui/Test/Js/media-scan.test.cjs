'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'view/adminhtml/web/js/media-scan.js'), 'utf8');
const template = fs.readFileSync(path.join(root, 'view/adminhtml/web/template/media-scan.html'), 'utf8');

function harness(responses) {
    const nodes = {};
    for (const match of template.matchAll(/data-role="([^"]+)"/g)) {
        nodes[match[1]] = {textContent: '', hidden: false, disabled: false, attributes: {},
            setAttribute(key, value) { this.attributes[key] = value; },
            removeAttribute(key) { delete this.attributes[key]; },
            addEventListener(event, handler) { this[event] = handler; }};
    }
    const element = {isConnected: true, querySelector(selector) {
        return nodes[selector.match(/data-role="([^"]+)"/)[1]];
    }, querySelectorAll() { return []; }};
    const calls = [];
    const timers = [];
    let initialize;
    vm.runInNewContext(source, {
        define(deps, factory) {
            initialize = factory({ajax(options) {
                calls.push(options);
                const response = responses.shift();
                assert.ok(response, 'Unexpected request');
                const chain = {
                    done(callback) { callback(response); return chain; },
                    fail() { return chain; },
                    always(callback) { callback(); return chain; }
                };
                return chain;
            }}, text => text, template);
        }, window: {FORM_KEY: 'test-key'},
        setTimeout(callback) { timers.push(callback); return timers.length; },
        clearTimeout() {}
    });
    initialize({statusUrl: '/status', startUrl: '/start'}, element);
    return {nodes, calls, timers};
}

function status(state, extra = {}) {
    return {success: true, scan: {status: state, blocked: false, processed: 2, estimated_total: 2,
        removed: 0, percent: state === 'audited' ? 100 : 0, elapsed_seconds: 0,
        estimated_remaining_seconds: null, last_completed_at: 100,
        verification_completed_at: null, error: null, ...extra}};
}

test('opening the panel only reads status; verification is requested by its button', () => {
    const h = harness([status('complete'), {success: true}, status('audit_pending')]);
    assert.equal(h.calls.length, 1);
    assert.equal(h.calls[0].type, 'GET');
    assert.equal(h.timers.length, 0);
    h.nodes.verify.click();
    assert.equal(h.calls[1].type, 'POST');
    assert.equal(h.calls[1].data.verify, '1');
    assert.equal(h.calls[1].data.form_key, 'test-key');
    assert.equal(h.nodes.status.textContent, 'Waiting for file verification');
    assert.equal(h.nodes.verify.disabled, true);
    assert.equal(h.nodes.start.disabled, true);
    assert.equal(h.timers.length, 1);
});

test('completed verification shows its date and findings on reopening without starting work', () => {
    const h = harness([status('audited', {verification_completed_at: 120, error: '1 missing file; see logs.'})]);
    assert.equal(h.nodes.status.textContent, 'File verification completed');
    assert.match(h.nodes['verification-time'].textContent, /^File verification completed at: /);
    assert.equal(h.nodes.error.textContent, '1 missing file; see logs.');
    assert.equal(h.nodes.errors.hidden, false);
    assert.equal(h.nodes.progress.value, 100);
    assert.equal(h.nodes.verify.disabled, false);
    assert.equal(h.calls.length, 1);
    assert.equal(h.timers.length, 0);
});

test('the running background verification blocks duplicate requests and polls for completion', () => {
    const h = harness([status('auditing'), status('audited', {verification_completed_at: 200})]);
    assert.equal(h.nodes.status.textContent, 'Verifying file integrity');
    h.nodes.verify.click();
    assert.equal(h.calls.length, 1);
    h.timers[0]();
    assert.equal(h.calls.length, 2);
    assert.equal(h.calls.every(call => call.type === 'GET'), true);
    assert.equal(h.nodes.verify.disabled, false);
    assert.equal(h.nodes.status.textContent, 'File verification completed');
});

test('normal index refresh retains the verification date and uses its original mode', () => {
    const h = harness([status('complete', {verification_completed_at: 120}), {success: true},
        status('pending', {verification_completed_at: 120})]);
    const previous = h.nodes['verification-time'].textContent;
    h.nodes.start.click();
    assert.equal(h.calls[1].data.verify, '0');
    assert.equal(h.nodes['verification-time'].textContent, previous);
    assert.match(template, /Verification runs only on request, in the background/);
});
