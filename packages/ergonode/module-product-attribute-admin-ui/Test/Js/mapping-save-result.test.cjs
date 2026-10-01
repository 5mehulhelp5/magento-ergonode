'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const resultFile = path.join(
    coreRoot,
    'view/adminhtml/web/js/mapping-save-result.js'
);
const attributeMapping = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const optionMapping = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);

function loadResultModule() {
    let exported;
    const values = new Map();
    const storage = {
        getItem(key) {
            return values.has(key) ? values.get(key) : null;
        },
        removeItem(key) {
            values.delete(key);
        },
        setItem(key, value) {
            values.set(key, value);
        }
    };
    const sandbox = {
        window: {sessionStorage: storage},
        define(names, factory) {
            assert.deepEqual(Array.from(names), []);
            exported = factory();
        }
    };

    vm.runInNewContext(fs.readFileSync(resultFile, 'utf8'), sandbox, {filename: resultFile});

    return exported;
}

test('warning save result survives one reload and is then consumed', () => {
    const resultModule = loadResultModule();
    const warning = {
        tone: 'warning',
        message: 'Atrybut „sku” już istnieje w Ergonode i został połączony.'
    };

    resultModule.persist('attribute', warning);

    assert.deepEqual(
        {...resultModule.consume('attribute')},
        warning
    );
    assert.equal(resultModule.consume('attribute'), null);
});

test('ordinary successful saves do not create a post-reload notice', () => {
    const resultModule = loadResultModule();

    resultModule.persist('attribute', {tone: 'success', message: 'Saved.'});

    assert.equal(resultModule.consume('attribute'), null);
});

test('attribute and option mappings persist and show their own warning result', () => {
    assert.match(attributeMapping, /mappingSaveResult\.persist\('attribute', response\)/);
    assert.match(attributeMapping, /mappingSaveResult\.consume\('attribute'\)/);
    assert.match(optionMapping, /mappingSaveResult\.persist\('option', response\)/);
    assert.match(optionMapping, /mappingSaveResult\.consume\('option'\)/);
});
