'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {resolveModuleRoot} = require('./module-contract.cjs');
const {readMappingTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const mappingSource = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);
const mappingTemplate = readMappingTemplate(moduleRoot, 'option');
const refreshController = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), 'Controller/Adminhtml/Option/Refresh.php'),
    'utf8'
);
const syncController = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), 'Controller/Adminhtml/Option/Sync.php'),
    'utf8'
);
const attributeRefreshController = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), 'Controller/Adminhtml/Attribute/Refresh.php'),
    'utf8'
);
const attributeSyncController = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), 'Controller/Adminhtml/Attribute/Sync.php'),
    'utf8'
);
const attributeTemplate = readMappingTemplate(moduleRoot, 'attribute');
const attributeLayout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_attribute_index.xml'),
    'utf8'
);
const optionLayout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_option_index.xml'),
    'utf8'
);
const attributeMappingSource = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const attributeRefreshResult = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-refresh-result.js'),
    'utf8'
);
const autoMatchController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Option/AutoMatch.php'),
    'utf8'
);

test('refresh and sync invoke separate option processes', () => {
    const action = mappingSource.slice(
        mappingSource.indexOf('function runOptionAction(root, button, action, messages)'),
        mappingSource.indexOf('function refreshOptions(root, button)')
    );
    const synchronization = mappingSource.slice(
        mappingSource.indexOf('function synchronizeOptions(root, button)'),
        mappingSource.indexOf('function initRefreshErgonode(root)')
    );
    const refresh = mappingSource.slice(
        mappingSource.indexOf('function initRefreshErgonode(root)'),
        mappingSource.indexOf('function initAutoMatch(root)')
    );
    const autoMatch = mappingSource.slice(
        mappingSource.indexOf('function initAutoMatch(root)'),
        mappingSource.indexOf('function initSaveAction(root)')
    );

    assert.match(action, /request\.post\(actionUrl\(config, action\)/);
    assert.match(action, /window\.location\.reload\(\)/);
    assert.match(synchronization, /runOptionAction\(root, button, 'sync'/);
    assert.match(synchronization, /Save manual changes before synchronizing options/);
    assert.match(refresh, /\[data-role="refresh-ergonode"\]/);
    assert.match(refresh, /refreshOptions\(root, button\)/);
    assert.match(refresh, /\[data-role="sync-ergonode"\]/);
    assert.match(refresh, /synchronizeOptions\(root, button\)/);
    assert.doesNotMatch(refresh, /auto-match|complete-missing/);
    assert.match(autoMatch, /request\.post\(actionUrl\(config, 'auto_match'\)/);
    assert.match(autoMatch, /applyAutoMatches\(root, response\.matches\)/);
    assert.doesNotMatch(autoMatch, /saveCurrentMappings|synchronizeOptions|hasUnsavedChanges/);
    assert.match(mappingSource, /Ergonode_CoreAdminUi\/js\/complete-missing/);
    assert.match(mappingSource, /completeMissing\(element\)/);
    assert.doesNotMatch(mappingSource, /getAttribute\(dataName\)|dataName = 'data-'/);
    assert.match(
        refreshController,
        /if \(\$mappingId <= 0\) \{[\s\S]*return \$result->setData\([\s\S]*Missing attribute mapping context/
    );
    assert.match(refreshController, /OptionSnapshotRefresh/);
    assert.match(refreshController, /optionSnapshotRefresh->execute\(\$mappingId\)/);
    assert.doesNotMatch(refreshController, /OptionSynchronizationProcessInterface/);
    assert.match(syncController, /OptionSynchronizationProcessInterface/);
    assert.match(syncController, /optionSynchronizationProcess->execute\(\$mappingId\)/);
});

test('attribute refresh updates only the snapshot and cannot mutate persisted cursor', () => {
    assert.match(attributeRefreshController, /AttributeSnapshotRefreshInterface/);
    assert.match(attributeRefreshController, /refreshSnapshot/);
    assert.doesNotMatch(attributeRefreshController, /option_mappings|OptionSynchronization/);
    assert.doesNotMatch(
        attributeRefreshController,
        /CursorStorage|PersistedBatchImportRunner|AttributeImportProcess|executeAutomatic|->reset\(/
    );
});

test('attribute and option views use the shared synchronization component and icon styles', () => {
    assert.match(attributeTemplate, /SynchronizationActions::class/);
    assert.match(attributeTemplate, /'has_cursor_actions' => true/);
    assert.match(mappingTemplate, /SynchronizationActions::class/);
    assert.doesNotMatch(mappingTemplate, /'has_cursor_actions' => true/);
    assert.match(attributeLayout, /Ergonode_CoreAdminUi::css\/ergonode-actions\.css/);
    assert.match(optionLayout, /Ergonode_CoreAdminUi::css\/ergonode-actions\.css/);
    assert.match(attributeMappingSource, /Ergonode_CoreAdminUi\/js\/synchronization-actions/);
    assert.match(attributeMappingSource, /synchronization_action: action/);
    assert.match(attributeSyncController, /synchronizationProcess->reset\(\)/);
    assert.match(
        attributeSyncController,
        /synchronizationProcess->executeUntilComplete\(\)/
    );
});

test('attribute refresh preserves and presents the aggregated snapshot outcome after reload', () => {
    const refresh = attributeMappingSource.slice(
        attributeMappingSource.indexOf('function initRefreshErgonode(root)'),
        attributeMappingSource.indexOf('function setAutoMatchAvailability(root')
    );

    assert.match(attributeMappingSource, /Ergonode_CoreAdminUi\/js\/attribute-refresh-result/);
    assert.match(refresh, /refreshResult\.empty\(\)/);
    assert.match(refresh, /onPage:[\s\S]*refreshResult\.merge\(summary, response\)/);
    assert.match(refresh, /refreshResult\.persist\(summary\)[\s\S]*window\.location\.reload\(\)/);
    assert.match(attributeMappingSource, /refreshResult\.show\(element\.veaContext\.message, refreshResult\.consume\(\)\)/);
    assert.match(attributeRefreshResult, /sessionStorage/);
    assert.match(attributeRefreshResult, /messageComponent\.show\('success'/);
    assert.doesNotMatch(attributeRefreshResult, /optionMappings|reviewRequired|wymaga przeglądu/);
});

test('option auto match delegates matching to the shared backend resolver without saving', () => {
    assert.match(autoMatchController, /OptionAutoMatchProcess/);
    assert.match(autoMatchController, /optionAutoMatchProcess->suggest\(/);
    assert.match(autoMatchController, /availableCodes\(\$payload\['ergonode_options'\]/);
    assert.match(autoMatchController, /availableCodes\(\$payload\['magento_options'\]/);
    assert.doesNotMatch(autoMatchController, /OptionMappingSaver|->save\(/);
    assert.doesNotMatch(mappingTemplate, /data-(?:ergo|magento)-attribute-(?:code|type)/);
});

test('draft actions state that saving is separate', () => {
    const buttons = mappingTemplate.slice(
        mappingTemplate.indexOf('data-role="auto-match"') - 120,
        mappingTemplate.indexOf('data-role="complete-missing"') + 500
    );

    assert.match(buttons, /data-role="auto-match"/);
    assert.match(buttons, /data-role="complete-missing"/);
    assert.match(mappingTemplate, /Automatically match option mappings without saving/);
    assert.match(mappingTemplate, /Complete missing option mappings without saving/);
    assert.doesNotMatch(mappingTemplate, /Automatically synchronize option mappings|Synchronize and complete/);
});
