'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const mappingSource = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Option/Mapping.php'),
    'utf8'
);

test('option matching decisions are requested from the backend contract', () => {
    const autoMatchHandler = mappingSource.slice(
        mappingSource.indexOf('function initAutoMatch(root)'),
        mappingSource.indexOf('function initSaveAction(root)')
    );

    assert.match(mappingBlock, /'auto_match' => \$this->getUrl\('ergonode\/option\/autoMatch'\)/);
    assert.match(autoMatchHandler, /request\.post\(actionUrl\(config, 'auto_match'\)/);
    assert.match(autoMatchHandler, /collectAutoMatchPayload\(root, config\)/);
    assert.match(autoMatchHandler, /applyAutoMatches\(root, response\.matches\)/);
    assert.doesNotMatch(mappingSource, /optionMatcher|js\/option-matcher|scoreAutoMatch/);
});

test('backend suggestions are inserted as one DOM fragment and remain an unsaved draft', () => {
    const applyMatches = mappingSource.slice(
        mappingSource.indexOf('function applyAutoMatches(root, matches)'),
        mappingSource.indexOf('function collectAutoMatchPayload(root, config)')
    );
    const payload = mappingSource.slice(
        mappingSource.indexOf('function collectAutoMatchPayload(root, config)'),
        mappingSource.indexOf('function bindPairSlot(root, slot)')
    );

    assert.match(applyMatches, /document\.createDocumentFragment\(\)/);
    assert.match(mappingSource, /mappingElements\.markNewMapping/);
    assert.doesNotMatch(applyMatches, /saveCurrentMappings|saveWithFeedback/);
    assert.match(payload, /attribute_mapping_id/);
    assert.match(payload, /ergonode_options: getAutoMatchPayloads\(root, 'ergo'\)/);
    assert.match(payload, /magento_options: getAutoMatchPayloads\(root, 'magento'\)/);
});
