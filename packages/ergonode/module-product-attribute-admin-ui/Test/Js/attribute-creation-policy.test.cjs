'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {resolveModuleRoot} = require('./module-contract.cjs');
const {readPairTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const consumerRoot = resolveModuleRoot('Ergonode_ProductAttributeConsumer');
const readAdmin = (relativePath) => fs.readFileSync(path.join(moduleRoot, relativePath), 'utf8');
const readConsumerAdmin = (relativePath) => fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), relativePath), 'utf8'
);
const readConsumer = (relativePath) => fs.readFileSync(path.join(consumerRoot, relativePath), 'utf8');

test('identical-code creation is explicitly configured and disabled by default', () => {
    const systemXml = readConsumerAdmin('etc/adminhtml/system.xml');
    const defaultConfig = readConsumer('etc/config.xml');

    assert.match(systemXml, /<group id="mapping"/);
    assert.match(systemXml, /<field id="map_identical_codes"/);
    assert.match(systemXml, /Map Identical Codes/);
    assert.match(defaultConfig, /<map_identical_codes>0<\/map_identical_codes>/);
});

test('only the import process can trigger configured automatic Magento attribute creation', () => {
    const autoMapper = readConsumer('Model/Mapping/AttributeAutoMapper.php');
    const importProcess = readConsumer('Model/Import/AttributeImportProcess.php');
    const synchronizationBatch = readConsumer('Model/Sync/AttributeSynchronizationBatch.php');
    const mappingSaver = readConsumer('Model/Mapping/AttributeMappingSaver.php');
    const syncController = readConsumerAdmin('Controller/Adminhtml/Attribute/Sync.php');

    assert.match(autoMapper, /shouldMapIdenticalCodes\(\)/);
    assert.match(autoMapper, /attributeCreator->create\(\$mappings\)/);
    assert.match(readConsumer('Model/Mapping/IdenticalCodeAttributeCreator.php'), /createFromErgonodeAttribute\(\$left\)/);
    assert.match(importProcess, /synchronizationBatch->executeAutomatic\(\$cursor, \$pageSize, \$refreshDefinitions\)/);
    assert.match(synchronizationBatch, /executeAutomatic[\s\S]*?executeWithLock\(\$cursor, \$pageSize\)/);
    const snapshotRefresh = fs.readFileSync(path.join(
        resolveModuleRoot('Ergonode_AttributeConsumer'),
        'Model/Snapshot/AttributeSnapshotRefresh.php'
    ), 'utf8');
    assert.match(snapshotRefresh, /definitionSynchronization->synchronize\(true\)/);
    assert.match(snapshotRefresh, /snapshot->page\(\$cursor, \$pageSize\)/);
    assert.doesNotMatch(snapshotRefresh, /attributeAutoMapper|optionSynchronizationPool/);
    assert.match(synchronizationBatch, /\$this->attributeAutoMapper->synchronize\(\)/);
    assert.match(mappingSaver, /saveAdditions[\s\S]*?Automatic mapping cannot create Magento attributes/);
    assert.match(syncController, /AttributeSynchronizationProcessInterface/);
    assert.match(syncController, /synchronizationProcess->executeUntilComplete\(\)/);
});

test('attribute mapping Admin UI can prepare creation but only Save sends the mutation', () => {
    const mappingScript = fs.readFileSync(
        path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
        'utf8'
    );
    const pairTemplate = readPairTemplate(moduleRoot, 'attribute');

    assert.match(mappingScript, /pending_create: true/);
    assert.match(mappingScript, /Atrybut zostanie utworzony w Magento po zapisaniu mapowania/);
    assert.match(mappingScript, /data-role="save-mapping"/);
    assert.match(mappingScript, /request\.post\(config\.urls \? config\.urls\.save/);
    assert.match(pairTemplate, /data-role="create-magento-attribute"/);
});
