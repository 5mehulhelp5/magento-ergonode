const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const backendRoot = path.resolve(moduleRoot, '../../..');
const read = (file) => fs.readFileSync(path.join(moduleRoot, file), 'utf8');

test('category attribute screen reuses the production attribute mapping view and behavior', () => {
    const layout = read('view/adminhtml/layout/ergonode_category_attribute_index.xml');
    const block = read('Block/Adminhtml/CategoryAttribute/Mapping.php');
    const provider = fs.readFileSync(path.join(
        backendRoot,
        'packages/ergonode/module-category-attribute/Model/Provider/MagentoCategoryAttributeProvider.php'
    ), 'utf8');
    const policy = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-category-attribute-consumer/Model/Config/CategoryAttributePolicy.php'
    ), 'utf8');
    const diXml = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-category-attribute-consumer/etc/di.xml'
    ), 'utf8');
    const productTemplate = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/templates/attribute/mapping.phtml'
    ), 'utf8');
    const mappingScript = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/attribute-mapping.js'
    ), 'utf8');
    const story = read('Test/Storybook/CategoryAttributeMapping.stories.js');
    const navigationProvider = fs.readFileSync(path.join(backendRoot, 'vendor/ergonode/module-category-attribute-consumer-admin-ui/Model/Navigation/CategoryAttributeNavigationItemProvider.php'), 'utf8');
    const moduleDi = fs.readFileSync(path.join(backendRoot, 'vendor/ergonode/module-category-attribute-consumer-admin-ui/etc/di.xml'), 'utf8');
    const workspaceStyles = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/ergonode-workspace.css'
    ), 'utf8');
    const mappingStyles = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/attribute-mapping.css'
    ), 'utf8');
    const optionModalStyles = fs.readFileSync(path.join(
        backendRoot,
        'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/option-mapping-modal.css'
    ), 'utf8');

    assert.match(layout, /Ergonode_CoreAdminUi::attribute\/mapping\.phtml/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/attribute-mapping\.css/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/option-mapping\.css/);
    assert.match(layout, /Ergonode_CoreAdminUi::css\/option-mapping-modal\.css/);
    assert.match(mappingStyles, /\.vea-unlink-mapping:focus-visible/);
    assert.doesNotMatch(mappingStyles, /\.vea-attribute-pair-row\.vea-status-tone-ok\s*\{/);
    assert.doesNotMatch(mappingStyles, /\.vea-pair-row\.vea-status-tone-ok:not\(\.vea-attribute-pair-row\)\s*\{/);
    assert.match(productTemplate, /Ergonode_CoreAdminUi\/js\/attribute-mapping/);
    assert.match(block, /ergonode-category-attribute-mapping/);
    assert.match(productTemplate, /\$block->canCreateMagentoAttributes\(\)/);
    assert.match(block, /canCreateMagentoAttributes\(\): bool\s*\{\s*return false;/);
    assert.match(productTemplate, /\$block->canSynchronizeAttributes\(\)/);
    assert.match(block, /canSynchronizeAttributes\(\): bool\s*\{\s*return false;/);
    assert.match(block, /'allow_magento_attribute_creation' => \$this->canCreateMagentoAttributes\(\)/);
    assert.match(mappingScript, /root\.veaConfig\.allow_magento_attribute_creation/);
    assert.doesNotMatch(block, /getExistingMagentoAttributeCodes/);
    assert.match(block, /attribute_type_compatibility/);
    assert.match(block, /category_attribute\/save/);
    assert.match(block, /category_option\/index/);
    assert.match(block, /category_option\/modal/);
    assert.match(optionModalStyles, /vea-option-mapping-modal/);
    assert.match(story, /createCoreModuleLoader\(\)\('Ergonode_CoreAdminUi\/js\/attribute-mapping'\)/);
    assert.match(story, /loadAmdModule/);
    assert.match(productTemplate, /\$block->getCurrentNavigationSection\(\)/);
    assert.match(block, /return 'category_attributes'/);
    assert.match(navigationProvider, /implements CategoryNavigationItemProviderInterface/);
    assert.match(navigationProvider, /ergonode\/category_attribute\/index/);
    assert.doesNotMatch(navigationProvider, /ergonode\/category_option\/index/);
    assert.match(navigationProvider, /category_attribute_mapping/);
    assert.match(moduleDi, /CategoryNavigationGroupProvider/);
    assert.match(moduleDi, /itemProviders/);
    assert.match(moduleDi, /CategoryAttributeNavigationItemProvider/);
    assert.match(workspaceStyles, /body\.ergonode-category_attribute-index/);
    assert.match(story, /createNavigation\(\{currentSection: 'category_attributes', categoryOptionsAvailable: true\}\)/);
    const navigationFixture = fs.readFileSync(path.join(
        backendRoot, 'dev/tools/ergonode-storybook/src/section-navigation.js'
    ), 'utf8');
    assert.match(navigationFixture, /const classPrefix = 'veui-split-button'/);
    assert.match(navigationFixture, /href: '#category-attributes', label: 'Attributes'/);
    assert.doesNotMatch(navigationFixture, /href: '#category-options', label: 'Options'/);
    assert.doesNotMatch(story, /veui-section-navigation-icon-circle-ellipsis-vertical/);
    assert.match(provider, /ALLOWED_NATIVE_CODES/);
    assert.match(provider, /attributePolicy->isMappable/);
    assert.match(policy, /excludedAttributeCodes/);
    assert.match(diXml, /<item name="default_sort_by"[^>]*>default_sort_by<\/item>/);
    assert.match(diXml, /<item name="is_anchor"[^>]*>is_anchor<\/item>/);
    assert.doesNotMatch(diXml, /<item name="name"[^>]*>name<\/item>/);
    assert.match(diXml, /<item name="url_key"[^>]*>url_key<\/item>/);
    assert.match(provider, /\$attribute->getIsRequired\(\)/);
    assert.match(provider, /attributePolicy->isRequiredMapping/);
    assert.match(provider, /\$attribute\['required'] \|\|/);
    assert.match(productTemplate, /data-mapping-required=/);
    assert.match(productTemplate, /veui-mapping-requirement-icon/);
    assert.match(story, /RequiredMissing/);
    assert.match(story, /ManualSystemAttributes/);
    assert.doesNotMatch(story, /code: 'is_anchor'/);
    assert.doesNotMatch(story, /code: 'default_sort_by'/);
    assert.match(story, /productionRequirements\.create\(root\)\.refresh\(\)/);
});

test('category attribute screen has its own ACL, route, menu and persistence owner', () => {
    const acl = fs.readFileSync(path.join(
        backendRoot,
        'packages/ergonode/module-category-attribute/etc/acl.xml'
    ), 'utf8');
    const schema = fs.readFileSync(path.join(
        backendRoot,
        'packages/ergonode/module-category-attribute/etc/db_schema.xml'
    ), 'utf8');
    const menu = read('etc/adminhtml/menu.xml');

    assert.match(acl, /category_attribute_mapping/);
    assert.match(acl, /category_attribute_refresh/);
    assert.match(acl, /category_attribute_save/);
    assert.match(schema, /ergonode_category_attribute_mapping/);
    assert.match(menu, /action="ergonode\/category_attribute\/index"/);
});
