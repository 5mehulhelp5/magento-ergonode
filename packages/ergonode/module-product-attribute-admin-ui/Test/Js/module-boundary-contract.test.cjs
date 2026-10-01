'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const coreAdminUiRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const consumerAdminUiRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_ProductAttributeConsumerAdminUi');

function read(root, relativePath) {
    return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

test('module owns its Magento and Composer identities without requiring deployment enablement', () => {
    const composer = JSON.parse(read(moduleRoot, 'composer.json'));
    const registration = read(moduleRoot, 'registration.php');
    const moduleXml = read(moduleRoot, 'etc/module.xml');

    assert.equal(composer.name, 'ergonode/module-product-attribute-admin-ui');
    assert.equal(composer.autoload['psr-4']['Ergonode\\ProductAttributeAdminUi\\'], '');
    assert.equal(composer.extra.ergonode.lifecycle, 'development');
    assert.equal(composer.version, undefined);
    assert.match(registration, /'Ergonode_ProductAttributeAdminUi'/);
    assert.match(moduleXml, /<module name="Ergonode_ProductAttributeAdminUi">/);
});

test('module contributes Attributes without a separate Options navigation destination', () => {
    const menu = read(moduleRoot, 'etc/adminhtml/menu.xml');
    const di = read(moduleRoot, 'etc/di.xml');
    const attributeBlock = read(moduleRoot, 'Block/Adminhtml/Attribute/Mapping.php');
    const optionBlock = read(moduleRoot, 'Block/Adminhtml/Option/Mapping.php');
    const attributeTemplate = read(coreAdminUiRoot, 'view/adminhtml/templates/attribute/mapping.phtml');
    const optionTemplate = read(coreAdminUiRoot, 'view/adminhtml/templates/option/mapping.phtml');

    assert.match(menu, /title="Attributes"[\s\S]*parent="Ergonode_Core::products"/);
    assert.doesNotMatch(menu, /title="Options"[\s\S]*parent="Ergonode_Core::products"/);
    assert.match(di, /<item name="attributes" xsi:type="array">/);
    assert.doesNotMatch(di, /<item name="options" xsi:type="array">/);
    assert.match(di, /ergonode\/attribute\/index/);
    assert.doesNotMatch(di, /ergonode\/option\/index/);
    assert.doesNotMatch(di, /attribute_sync|option_sync/);
    assert.match(attributeBlock, /SectionNavigation::SECTION_ATTRIBUTES/);
    assert.match(optionBlock, /SectionNavigation::SECTION_OPTIONS/);
    assert.match(attributeTemplate, /\$block->getCurrentNavigationSection\(\)/);
    assert.match(optionTemplate, /\$block->getCurrentNavigationSection\(\)/);
});

test('optional inbound UI owns product attribute snapshot-removal endpoints', () => {
    const endpoints = [
        ['Block/Adminhtml/Attribute/Mapping.php', /attribute\/deleteSnapshot/],
        ['Block/Adminhtml/Option/Mapping.php', /option\/deleteSnapshot/],
    ];
    const controllers = [
        'Controller/Adminhtml/Attribute/DeleteSnapshot.php',
        'Controller/Adminhtml/Option/DeleteSnapshot.php',
    ];

    endpoints.forEach(([relativePath, pattern]) => {
        assert.match(read(moduleRoot, relativePath), pattern);
    });
    controllers.forEach((relativePath) => {
        const controller = read(consumerAdminUiRoot, relativePath);

        assert.match(controller, /implements HttpPostActionInterface/);
        assert.match(controller, /public const string ADMIN_RESOURCE/);
    });
});

test('module composes shared workspace assets and owns its option story', () => {
    const layouts = [
        read(moduleRoot, 'view/adminhtml/layout/ergonode_attribute_index.xml'),
        read(moduleRoot, 'view/adminhtml/layout/ergonode_option_index.xml'),
    ];
    const optionStory = read(moduleRoot, 'Test/Storybook/OptionMapping.stories.js');

    layouts.forEach((layout) => {
        const sharedIndex = layout.indexOf('Ergonode_CoreAdminUi::css/ergonode-workspace.css');
        const featureIndex = layout.indexOf('attribute-mapping.css');

        assert.ok(sharedIndex >= 0);
        assert.ok(featureIndex > sharedIndex);
    });
    assert.match(optionStory, /export const LadowanieKontekstu/);
    assert.equal(
        fs.existsSync(path.join(moduleRoot, 'Controller/Adminhtml/Attribute/Synchronize.php')),
        false
    );
});
