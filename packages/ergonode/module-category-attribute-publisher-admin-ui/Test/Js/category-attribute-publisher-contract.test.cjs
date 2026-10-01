'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const vendorRoot = path.resolve(process.cwd(), 'vendor/ergonode');
const publisherAdminUiRoot = path.join(vendorRoot, 'module-publisher-admin-ui');
const categoryAttributePublisherRoot = path.join(vendorRoot, 'module-category-attribute-publisher');
const attributePublisherAdminUiRoot = path.join(vendorRoot, 'module-attribute-publisher-admin-ui');
const composerJson = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'composer.json'), 'utf8'));
const moduleXml = fs.readFileSync(path.join(moduleRoot, 'etc/module.xml'), 'utf8');
const diXml = fs.readFileSync(path.join(moduleRoot, 'etc/adminhtml/di.xml'), 'utf8');
const layout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_category_attribute_index.xml'),
    'utf8'
);
const plugin = fs.readFileSync(
    path.join(moduleRoot, 'Plugin/CategoryAttributeMappingSaverPlugin.php'),
    'utf8'
);
const creator = fs.readFileSync(
    path.join(moduleRoot, 'Model/ErgonodeCategoryAttributeCreator.php'),
    'utf8'
);
const stateBuilder = fs.readFileSync(
    path.join(categoryAttributePublisherRoot, 'Model/Provider/CategoryAttributeSourceStateBuilder.php'),
    'utf8'
);
const batchController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/CategoryAttribute/Batch/Create.php'),
    'utf8'
);
const sharedBatchController = fs.readFileSync(
    path.join(publisherAdminUiRoot, 'Controller/Adminhtml/AbstractBatchCreate.php'),
    'utf8'
);
const publisherScript = fs.readFileSync(
    path.join(
        attributePublisherAdminUiRoot,
        'view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js'
    ),
    'utf8'
);
const publisherStory = fs.readFileSync(
    path.join(moduleRoot, 'Test/Storybook/CategoryAttributePublisher.stories.js'),
    'utf8'
);

test('category attribute mapping screen receives the shared Ergonode publisher action', () => {
    assert.equal(composerJson.require['ergonode/module-attribute-publisher-admin-ui'], 'dev-main@dev');
    assert.match(moduleXml, /<module name="Ergonode_AttributePublisherAdminUi"\/>/);
    assert.match(layout, /target_selector[^>]*xsi:type="string">#ergonode-category-attribute-mapping/);
    assert.match(layout, /name="mode"[^>]*>attribute</);
    assert.match(layout, /name="entity_kind"[^>]*>category_attribute</);
    assert.match(layout, /ergonode_manual\/category_attribute_batch\/create/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/bulk-publish-progress\.css/);
    assert.match(layout, /Ergonode_AttributePublisherAdminUi::css\/pending-type-picker\.css/);
});

test('empty category attribute source keeps refresh without an Ergonode creation action', () => {
    assert.doesNotMatch(publisherScript, /create-ergonode-attribute-empty/);
    assert.doesNotMatch(publisherScript, /vea-empty-create-ergonode/);
    assert.match(publisherStory, /data-role="attribute-empty-state"/);
    assert.match(publisherStory, /data-role="refresh-ergonode"/);
    assert.match(publisherStory, /export const EmptyErgonodeSource/);
    assert.match(publisherStory, /queryByRole\('button',[\s\S]*?Utwórz atrybut w Ergonode z Magento/);
});

test('category attribute batches use the category ACL and expose retry progress', () => {
    assert.match(batchController, /ADMIN_RESOURCE = 'Ergonode_CategoryConsumer::category_attribute_save'/);
    assert.match(batchController, /CategoryAttributeBatchPublisher/);
    assert.match(batchController, /extends AbstractBatchCreate/);
    assert.match(sharedBatchController, /retry_after_seconds/);
});

test('pending category attributes are published and registered before local mapping validation', () => {
    assert.match(diXml, /CategoryAttributeMappingSaverPlugin/);
    assert.match(plugin, /if \(!\$left \|\| empty\(\$left\['pending_create'\]\)\)/);
    assert.match(plugin, /attributeCreator->synchronizeFromMagento/);
    assert.ok(plugin.indexOf('attributeCreator->synchronizeFromMagento') < plugin.indexOf("$mapping['left'] ="));
    assert.match(creator, /AttributeSynchronizerInterface/);
    assert.match(creator, /CategoryAttributeRegistrySynchronizerInterface/);
});

test('category source state uses canonical Ergonode types and stable option codes', () => {
    assert.match(stateBuilder, /AttributeTypeResolverInterface/);
    assert.match(stateBuilder, /typeResolver->resolve/);
    assert.match(stateBuilder, /'option_' \. \$id/);
    assert.match(stateBuilder, /\['select', 'multi_select'\]/);
});
