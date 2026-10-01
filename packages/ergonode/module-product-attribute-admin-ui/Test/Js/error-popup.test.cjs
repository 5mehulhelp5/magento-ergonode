'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const composerJson = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'composer.json'), 'utf8'));
const attributeMapping = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const attributeMappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Attribute/Mapping.php'),
    'utf8'
);
const optionMapping = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);
const sharedMessages = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi'), 'view/adminhtml/web/js/messages.js'),
    'utf8'
);

[attributeMapping, optionMapping].forEach((mappingScript) => {
    test('mapping errors use the shared workspace message instead of a local alert', () => {
        assert.match(mappingScript, /Ergonode_CoreAdminUi\/js\/workspace-context/);
        assert.match(mappingScript, /veaContext\.message\.error\(/);
        assert.doesNotMatch(mappingScript, /function showErrorPopup|modalAlert\(/);
        assert.doesNotMatch(mappingScript, /window\.alert\(/);
    });
});

test('shared messages adapter owns accessible in-page error rendering', () => {
    assert.match(sharedMessages, /function error\(title, exception, fallbackMessage, action\)/);
    assert.match(sharedMessages, /show\('error', errorMessage\(/);
    assert.match(sharedMessages, /container\.setAttribute\('role', tone === 'error' \? 'alert' : 'status'\)/);
});

test('admin UI declares the shared workspace dependency', () => {
    assert.equal(composerJson.require['ergonode/module-core-admin-ui'], '*');
    assert.equal(composerJson.require['magento/module-ui'], undefined);
});

test('attribute refresh and save failures have contextual popup titles', () => {
    assert.match(attributeMapping, /Nie udało się odświeżyć atrybutów/);
    assert.match(attributeMapping, /Nie udało się zapisać mapowania/);
});

test('attribute refresh failure links directly to language mapping', () => {
    assert.match(attributeMappingBlock, /'language_mapping' => \$this->getUrl\('ergonode\/language\/index'\)/);
    assert.match(attributeMapping, /function languageMappingAction\(config, error\)/);
    assert.match(attributeMapping, /error\.message !== \$t\('Configure at least one active Ergonode language/);
    assert.match(attributeMapping, /label: \$t\('Przejdź do mapowania języków'\)/);
    assert.match(attributeMapping, /url: config\.urls && config\.urls\.language_mapping/);
    assert.match(attributeMapping, /languageMappingAction\(config, error\)/);
});

test('option refresh and save failures have contextual popup titles', () => {
    assert.match(optionMapping, /Unable to synchronize options/);
    assert.match(optionMapping, /Nie udało się zapisać mapowania opcji/);
});
