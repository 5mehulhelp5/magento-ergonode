'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function fixture() {
    let createProgress;
    let modalOptions;
    let now = 1000;
    const timers = [];
    const modalActions = [];
    let modalShell;

    function element(sharedChildren) {
        const children = sharedChildren instanceof Map ? sharedChildren : new Map();
        const listeners = {};
        const attributes = {};
        return {
            style: {}, hidden: false, disabled: false, textContent: '',
            set innerHTML(html) {
                for (const match of html.matchAll(/<[^>]*data-role="([^"]+)"[^>]*>/g)) {
                    const child = element();
                    child.hidden = /\bhidden\b/.test(match[0]);
                    children.set(`[data-role="${match[1]}"]`, child);
                }
                if (html.includes('veui-publish-progress-actions')) {
                    children.set('.veui-publish-progress-actions', element(children));
                }
            },
            querySelector: (selector) => children.get(selector),
            closest: (selector) => selector === '.modal-popup' ? modalShell : null,
            setAttribute: (name, value) => { attributes[name] = value; },
            getAttribute: (name) => attributes[name],
            addEventListener: (name, callback) => { listeners[name] = callback; },
            click() { if (!this.disabled) listeners.click?.(); },
            replaceChildren() {}, append() {}, appendChild() {}
        };
    }

    vm.runInNewContext(fs.readFileSync(path.resolve(
        __dirname, '../../view/adminhtml/web/js/bulk-publish-progress.js'
    ), 'utf8'), {
        define: (names, factory) => {
            createProgress = factory(
                () => ({
                    remove: () => modalActions.push('remove'),
                    modal: (action, name) => action === 'option' && name === 'isOpen'
                        ? true : modalActions.push(action)
                }),
                (options) => {
                    modalOptions = options;
                    const innerWrap = element();
                    const closeButton = element();
                    modalShell = element();
                    modalShell.querySelector = (selector) => {
                        if (selector === '.modal-inner-wrap') {
                            return innerWrap;
                        }
                        if (selector === '[data-role="closeBtn"]') {
                            return closeButton;
                        }

                        return undefined;
                    };
                },
                (text) => text
            );
        },
        document: {createElement: element, body: element()},
        window: {setTimeout: (callback) => { timers.push(callback); return callback; },
            clearTimeout: (callback) => { const index = timers.indexOf(callback); if (index >= 0) timers.splice(index, 1); }},
        Date: {now: () => now}, Promise
    });
    const progress = createProgress();
    const node = (role) => progress.element.querySelector(`[data-role="publish-${role}"]`);

    return {
        progress, node, modalActions, timers,
        escape: () => modalOptions.keyEventHandlers.escapeKey(),
        advance: (milliseconds) => { now += milliseconds; },
        tick: () => { assert.ok(timers.length); timers.shift()(); }
    };
}

test('pause is optional and Escape only closes a finished publication', () => {
    const f = fixture();
    f.progress.open(10);
    assert.equal(f.node('progress-pause').hidden, true);
    f.escape();
    assert.deepEqual(f.modalActions, ['openModal']);
    let closed = 0;
    f.progress.complete(false, () => closed++);
    f.escape();
    assert.equal(closed, 1);
    assert.deepEqual(f.modalActions, ['openModal', 'closeModal']);
});

test('pause waits for the controller and resume preserves counts and excludes paused time from ETA', () => {
    const f = fixture();
    let pauses = 0;
    let resumes = 0;
    f.progress.open(4, {pause: () => pauses++, resume: () => resumes++});
    f.progress.showBatch(1, 2, [{code: 'one'}, {code: 'two'}]);
    f.node('progress-pause').click();
    f.node('progress-pause').click();
    assert.equal(pauses, 1);
    assert.equal(f.node('progress-pause').getAttribute('aria-disabled'), 'true');
    assert.match(f.node('progress-status').textContent, /Wstrzymuję/);
    f.advance(2000);
    f.progress.applyBatch([{status: 'synchronized'}, {status: 'failed', code: 'two'}]);
    f.progress.setPauseState('paused');
    assert.equal(f.node('progress-pause').textContent, 'Wznów');
    assert.equal(f.node('progress-pause').getAttribute('aria-disabled'), 'false');
    assert.equal(f.node('progress-title').textContent, 'Przetworzono 2 z 4');
    assert.equal(f.node('error-count').textContent, '1');
    assert.equal(f.node('progress-close').hidden, true);
    f.escape();
    assert.deepEqual(f.modalActions, ['openModal']);
    f.advance(120000);
    f.node('progress-pause').click();
    assert.equal(resumes, 1);
    assert.equal(f.node('progress-pause').textContent, 'Wstrzymaj');
    assert.match(f.node('progress-eta').textContent, /około 2 s/);
    assert.match(f.node('progress-status').textContent, /Przetwarzam paczkę 1/);
    assert.equal(f.node('progress-title').textContent, 'Przetworzono 2 z 4');
});

test('rate-limit countdown does not overwrite pause status or expose premature resume', async () => {
    const f = fixture();
    f.progress.open(2, {pause() {}, resume() {}});
    const waiting = f.progress.wait(2);
    f.node('progress-pause').click();
    f.tick();
    assert.match(f.node('progress-status').textContent, /Wstrzymuję/);
    assert.match(f.node('progress-eta').textContent, /Pozostaw tę kartę otwartą/);
    assert.equal(f.node('progress-pause').getAttribute('aria-disabled'), 'true');
    f.tick();
    await waiting;
    f.progress.setPauseState('paused');
    assert.match(f.node('progress-status').textContent, /Proces wstrzymany/);
});

test('finalization and failure retire controls and reopening starts clean', () => {
    const f = fixture();
    const controls = {pause() {}, resume() {}};
    f.progress.open(2, controls);
    f.progress.finalizing();
    assert.equal(f.node('progress-pause').hidden, true);
    f.progress.complete(false);
    f.progress.open(3, controls);
    f.node('progress-pause').click();
    f.progress.fail('Transport failed');
    assert.equal(f.node('progress-pause').hidden, true);
    assert.equal(f.node('progress-close').hidden, false);
    assert.equal(f.node('progress-status').textContent, 'Proces został zatrzymany.');
    f.progress.open(5, controls);
    assert.equal(f.node('progress-pause').hidden, false);
    assert.equal(f.node('progress-pause').getAttribute('aria-disabled'), 'false');
    assert.equal(f.node('progress-title').textContent, 'Przetworzono 0 z 5');
});


test('stop is optional, fires once and preserves completed counters', () => {
    const f = fixture();
    f.progress.open(4);
    assert.equal(f.node('progress-stop').hidden, true);
    let stops = 0;
    f.progress.open(4, {pause() {}, resume() {}, stop() { stops++; }});
    f.progress.applyBatch([{code:'one', status:'success'}]);
    f.node('progress-stop').click();
    f.node('progress-stop').click();
    assert.equal(stops, 1);
    f.progress.stopped();
    assert.equal(f.node('success-count').textContent, '1');
    assert.equal(f.node('remaining-count').textContent, '3');
    assert.equal(f.node('progress-stop').hidden, true);
    assert.equal(f.node('progress-close').hidden, false);
});


test('destroy rejects a pending wait, removes modal once and ignores late completion', async () => {
    const f = fixture();
    f.progress.open(5);
    const wait = f.progress.wait(60);
    const rejected = assert.rejects(wait, /disposed/);
    f.progress.destroy();
    f.progress.destroy();
    await rejected;
    assert.equal(f.timers.length, 0);
    assert.deepEqual(f.modalActions, ['openModal', 'option', 'closeModal', 'destroy', 'remove']);
    f.progress.complete(false);
    f.progress.open(10);
    assert.equal(f.modalActions.length, 5);
    await assert.rejects(f.progress.wait(1), /disposed/);
});
