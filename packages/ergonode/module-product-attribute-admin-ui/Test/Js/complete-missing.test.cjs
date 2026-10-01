'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {readMappingTemplate, readPairTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const completeMissingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/complete-missing.js'),
    'utf8'
);
const attributeScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const optionScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);
const attributeTemplate = readMappingTemplate(moduleRoot, 'attribute');
const attributePairTemplate = readPairTemplate(moduleRoot, 'attribute');
const optionTemplate = readMappingTemplate(moduleRoot, 'option');
const optionPairTemplate = readPairTemplate(moduleRoot, 'option');
const completeMissingStyles = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi'), 'view/adminhtml/web/css/attribute-mapping.css'),
    'utf8'
);
const completeMissingIcon = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi'), 'view/adminhtml/web/images/complete-missing.svg'),
    'utf8'
);
test('core mapping screens own the neutral completion action and its new label', () => {
    [attributeTemplate, optionTemplate].forEach((template) => {
        assert.match(template, /data-role="complete-missing"/);
        assert.match(template, /<span class="vea-complete-missing-icon" aria-hidden="true"><\/span>/);
        assert.match(template, /escapeHtml\(__\('Uzupełnij'\)\)/);
        assert.doesNotMatch(template, /Utwórz brakujące w Ergonode/);
    });
    assert.match(completeMissingStyles, /\.vea-complete-missing-icon\s*\{[\s\S]*?background: currentColor[\s\S]*?height: 18px[\s\S]*?mask: url\('\.\.\/images\/complete-missing\.svg'\) center \/ 18px 18px no-repeat[\s\S]*?width: 18px/);
    assert.match(completeMissingIcon, /<svg[^>]*viewBox="0 0 24 24">/);
    assert.match(completeMissingIcon, /<path d="M20\.5,0h-4\.5/);
});

test('creation actions on both mapping screens participate in draft completion', () => {
    [attributeScript, attributePairTemplate, optionScript, optionPairTemplate].forEach((source) => {
        assert.match(source, /data-completes-missing-side=\\?"1\\?"/);
    });
    assert.match(attributeScript, /completeMissing\(element(?:,|\))/);
    assert.match(optionScript, /completeMissing\(element(?:,|\))/);
});

test('batch completion prepares every contributed side without triggering save', () => {
    assert.match(completeMissingScript, /actionSelector = '\[data-completes-missing-side="1"\]'/);
    assert.match(completeMissingScript, /availableActions\(root\)\.length === 0/);
    assert.match(completeMissingScript, /actions = availableActions\(root\);/);
    assert.match(completeMissingScript, /typeof options\.complete === 'function'/);
    assert.match(completeMissingScript, /runActions\(actions\)/);
    assert.match(completeMissingScript, /getAttribute\('aria-busy'\) === 'true'/);
    assert.doesNotMatch(completeMissingScript, /saveButton\.click\(\)/);
});

test('default completion updates the draft but never clicks save', async () => {
    let completeMissing;
    let handler;
    let actionClicks = 0;
    let saveClicks = 0;
    const completeButton = {
        disabled: false,
        attributes: {},
        getAttribute(name) { return this.attributes[name] || null; },
        removeAttribute(name) { delete this.attributes[name]; },
        setAttribute(name, value) { this.attributes[name] = value; }
    };
    const saveButton = {
        disabled: false,
        click() { saveClicks += 1; },
        getAttribute() { return null; }
    };
    const action = {
        disabled: false,
        click() { actionClicks += 1; },
        closest() { return {}; }
    };
    const root = {
        querySelector(selector) {
            return selector === '[data-role="complete-missing"]' ? completeButton : saveButton;
        },
        querySelectorAll() { return [action]; }
    };
    const scope = {
        cleanup() {},
        listen(target, type, listener) { handler = listener; }
    };

    vm.runInNewContext(completeMissingScript, {
        MutationObserver: undefined,
        Promise,
        define(dependencies, factory) {
            completeMissing = factory({get: () => scope});
        }
    });
    completeMissing(root);
    handler({preventDefault() {}, stopPropagation() {}});
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(actionClicks, 1);
    assert.equal(saveClicks, 0);
});

test('option completion and backend auto-connect only update the local draft', () => {
    const initialization = optionScript.slice(
        optionScript.indexOf('initCreateMagentoOption(element)'),
        optionScript.indexOf('initSaveAction(element)')
    );

    assert.match(initialization, /initRefreshErgonode\(element\)/);
    assert.match(initialization, /initAutoMatch\(element\)/);
    assert.match(initialization, /completeMissing\(element\)/);
    assert.doesNotMatch(initialization, /initSynchronizationActions|saveWithFeedback|saveCurrentMappings/);
    assert.match(optionScript, /request\.post\(actionUrl\(config, 'auto_match'\)/);
    assert.match(optionScript, /applyAutoMatches\(root, response\.matches\)/);
    assert.doesNotMatch(optionScript, /optionMatcher|js\/option-matcher/);
});

test('attribute and option save actions are enabled only for dirty mappings', () => {
    [attributeTemplate, optionTemplate].forEach((template) => {
        assert.match(template, /data-role="save-mapping"[\s\S]*?disabled/);
        assert.doesNotMatch(template, /data-role="unsaved-state"|Niezapisane zmiany/);
    });
    [attributeScript, optionScript].forEach((script) => {
        assert.match(script, /function updateSaveState\(root\)/);
        assert.match(script, /button\.disabled = !/);
        assert.match(script, /getAttribute\('aria-busy'\) === 'true'/);
    });
});
