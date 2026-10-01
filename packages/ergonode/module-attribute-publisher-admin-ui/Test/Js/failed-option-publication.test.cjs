'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname,
    '../../view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js'), 'utf8');

function classList(classes) {
    const values = new Set(classes);

    return {
        add(...names) { names.forEach((name) => values.add(name)); },
        remove(...names) { names.forEach((name) => values.delete(name)); },
        contains(name) { return values.has(name); }
    };
}

function slot(side, code, label) {
    const attributes = {
        'data-side': side,
        'data-code': code,
        'data-label': label,
        'data-type': 'option',
        'data-scope': 'pl_PL',
        'data-pending-create': side === 'ergo' ? '1' : '0'
    };

    return {
        classList: classList(['vea-pair-card']),
        getAttribute(name) { return attributes[name] || ''; },
        setAttribute(name, value) { attributes[name] = value; },
        removeAttribute(name) { delete attributes[name]; },
        querySelector() { return null; },
        innerHTML: ''
    };
}

function row(code, label) {
    const left = slot('ergo', code, label);
    const right = slot('magento', code, label);
    const message = {textContent: '', hidden: true};
    const attributes = {};

    return {
        left, right, message,
        classList: classList(['vea-pair-row', 'vea-status-tone-warning']),
        querySelector(selector) {
            if (selector.includes('data-side="ergo"')) { return left; }
            if (selector.includes('data-side="magento"')) { return right; }
            if (selector.includes('pair-message')) { return message; }
            return null;
        },
        setAttribute(name, value) { attributes[name] = value; },
        getAttribute(name) { return attributes[name] || ''; }
    };
}

test('failed option is left unpaired, red and first while a successful option is saved', async () => {
    const successful = row('option_77', 'Other Brand');
    const failed = row('option_17', 'Zwiesel Glas');
    const rows = [successful, failed];
    const list = {
        get firstChild() { return rows[0]; },
        insertBefore(item, before) {
            rows.splice(rows.indexOf(item), 1);
            rows.splice(before ? rows.indexOf(before) : rows.length, 0, item);
        }
    };
    rows.forEach((item) => { item.parentNode = list; });
    const listeners = {};
    const saveButton = {};
    let savedPayload;
    let initialize;
    const root = {
        veaConfig: {attribute_mapping_id: 4, urls: {save: '/save'}},
        veaContext: {
            serialize() {
                return {mappings: rows.map((item) => ({
                    left: item.left.getAttribute('data-code')
                        ? {code: item.left.getAttribute('data-code'),
                            pending_create: item.left.getAttribute('data-pending-create') === '1'}
                        : null,
                    right: {code: item.right.getAttribute('data-code')}
                }))};
            }
        },
        veaWorkspace: {cleanup() {}},
        querySelectorAll(selector) {
            return selector === '[data-role="mapping-row"]' ? rows : [];
        },
        addEventListener(name, handler, capture) { listeners[name + ':' + !!capture] = handler; },
        contains() { return true; }
    };
    const jquery = () => ({});
    jquery.extend = (_deep, _target, value) => JSON.parse(JSON.stringify(value));
    jquery.ajax = () => ({
        done(callback) {
            callback({success: true, items: [
                {code: 'option_77', status: 'synchronized', mapping: {code: 'other_brand'}},
                {code: 'option_17', status: 'failed', message: 'Duplicate code zwiesel_glas'}
            ]});
            return this;
        },
        fail() { return this; }
    });
    vm.runInNewContext(source, {
        define: (_dependencies, factory) => {
            initialize = factory(
                jquery, () => {}, (value) => value,
                {setBusy() {}},
                {post(_url, _config, data) {
                    savedPayload = JSON.parse(data.payload);
                    return Promise.resolve({success: true});
                }},
                () => ({open() {}, showBatch() {}, applyBatch() {}, finalizing() {}, complete() {}, fail() {}}),
                {escapeHtml: (value) => value},
                {
                    slotPayload: (item) => item && item.getAttribute('data-code')
                        ? {code: item.getAttribute('data-code'), label: item.getAttribute('data-label'),
                            type: 'option', scope: 'pl_PL'} : null,
                    typeBadgeHtml: () => '<span>option</span>'
                }
            );
        },
        window: {FORM_KEY: ''}
    });
    initialize({mode: 'option', batch_size: 20, urls: {batch_create: '/batch'}}, root);
    listeners['click:true']({
        target: {closest() { return saveButton; }},
        preventDefault() {},
        stopImmediatePropagation() {}
    });
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(rows[0], failed);
    assert.equal(failed.left.getAttribute('data-code'), '');
    assert.equal(failed.left.getAttribute('data-pending-create'), '0');
    assert.equal(failed.classList.contains('vea-status-tone-error'), true);
    assert.equal(failed.message.textContent, 'Duplicate code zwiesel_glas');
    assert.equal(failed.message.hidden, false);
    assert.equal(savedPayload.mappings[0].left, null);
    assert.equal(savedPayload.mappings[1].left.code, 'other_brand');
});
