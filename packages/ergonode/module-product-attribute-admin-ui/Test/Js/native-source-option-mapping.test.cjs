'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {resolveModuleRoot} = require('./module-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const consumerRoot = resolveModuleRoot('Ergonode_ProductAttributeConsumer');
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Option/Mapping.php'),
    'utf8'
);
const mappingTemplate = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/templates/option/mapping.phtml'),
    'utf8'
);
const refreshController = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), 'Controller/Adminhtml/Option/Refresh.php'),
    'utf8'
);
const syncController = fs.readFileSync(
    path.join(resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi'), 'Controller/Adminhtml/Option/Sync.php'),
    'utf8'
);
const mappingProvider = fs.readFileSync(
    path.join(consumerRoot, 'Model/Mapping/AttributeMappingProvider.php'),
    'utf8'
);
const optionSyncer = fs.readFileSync(
    path.join(consumerRoot, 'Model/Sync/MagentoOptionSyncer.php'),
    'utf8'
);

test('native Magento source attributes remain available in option mapping contexts', () => {
    const optionContexts = mappingProvider.slice(
        mappingProvider.indexOf('public function getOptionAttributeContexts(): array'),
        mappingProvider.indexOf('public function getMappingRow(int $mappingId): ?array')
    );

    assert.doesNotMatch(
        optionContexts,
        /if \(!empty\(\$right\['has_custom_source'\]\)\)/
    );
});

test('native Magento options use the canonical process only from the explicit sync action', () => {
    assert.match(refreshController, /OptionSnapshotRefresh/);
    assert.doesNotMatch(refreshController, /OptionSynchronizationProcessInterface/);
    assert.match(syncController, /OptionSynchronizationProcessInterface/);
    assert.match(syncController, /optionSynchronizationProcess->execute\(\$mappingId\)/);
    assert.match(optionSyncer, /loadNativeOptions\(\$magentoAttributeCode, \$attributeMapping\)/);
    assert.match(optionSyncer, /\$this->resource->syncMapping\(/);
    assert.doesNotMatch(refreshController, /magento_has_custom_source|MagentoOptionSyncer|OptionBatchImporter/);
});

test('native Magento options can be selected but new ones cannot be created', () => {
    assert.match(
        mappingBlock,
        /'magento_has_custom_source' => !empty\(\$context\['right'\]\['has_custom_source'\]\)/
    );
    assert.match(
        mappingTemplate,
        /\$magentoHasCustomSource = !empty\(\$attributeContext\['magento_has_custom_source'\]\)/
    );
    assert.match(mappingTemplate, /&& !\$magentoHasCustomSource/);
    assert.match(mappingTemplate, /data-can-create-magento-option=/);
});
