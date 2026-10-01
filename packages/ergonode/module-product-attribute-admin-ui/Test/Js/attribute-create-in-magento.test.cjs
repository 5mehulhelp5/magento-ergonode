'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {readMappingTemplate, readPairTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Attribute/Mapping.php'),
    'utf8'
);
const mappingTemplate = readMappingTemplate(moduleRoot, 'attribute');
const pairTemplate = readPairTemplate(moduleRoot, 'attribute');
const mappingScriptPath = path.join(
    coreRoot,
    'view/adminhtml/web/js/attribute-mapping.js'
);
const mappingScript = fs.readFileSync(mappingScriptPath, 'utf8');
const mappingStyles = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi'), 'view/adminhtml/web/css/attribute-mapping.css'),
    'utf8'
);
const mappingStory = fs.readFileSync(
    path.join(moduleRoot, 'Test/Storybook/AttributeCreateInMagento.stories.js'),
    'utf8'
);

function loadAttributeMapping() {
    let exported;
    const text = {
        normalize: (value) => String(value || '').trim().toLowerCase(),
        escapeHtml: (value) => String(value || '')
    };
    const dependencies = {
        'Ergonode_CoreAdminUi/js/text': text,
        'mage/translate': (value) => value
    };

    vm.runInNewContext(mappingScript, {
        define(names, factory) {
            exported = factory(...Array.from(names, (name) => dependencies[name] || {}));
        }
    }, {filename: mappingScriptPath});

    return exported;
}

test('existing Magento codes are included in the attribute mapping UI config', () => {
    assert.match(mappingBlock, /getExistingMagentoAttributeCodes\(\): array/);
    assert.match(mappingBlock, /getAttributeMap\(true\)/);
    assert.match(mappingBlock, /canCreateMagentoAttributes\(\): bool/);
    assert.match(mappingBlock, /'allow_magento_attribute_creation' => \$this->canCreateMagentoAttributes\(\)/);
    assert.match(mappingBlock, /'existing_magento_attribute_codes' => \$this->getExistingMagentoAttributeCodes\(\)/);
    assert.match(mappingTemplate, /\$block->canCreateMagentoAttributes\(\)/);
    assert.match(mappingTemplate, /\$block->getExistingMagentoAttributeCodes\(\)/);
    assert.match(mappingTemplate, /setData\('magento_attribute_exists', \$magentoAttributeExists\)/);
});

test('Magento attribute creation requires an explicit screen capability', () => {
    const mapping = loadAttributeMapping();
    const enabledRoot = {veaConfig: {allow_magento_attribute_creation: true}};
    const disabledRoot = {veaConfig: {allow_magento_attribute_creation: false}};

    assert.equal(mapping.canCreateMagentoAttribute(enabledRoot, 'select'), true);
    assert.equal(mapping.canCreateMagentoAttribute(enabledRoot, 'gallery'), false);
    assert.equal(mapping.canCreateMagentoAttribute(disabledRoot, 'select'), false);
    assert.equal(mapping.canCreateMagentoAttribute({}, 'select'), false);
});

test('create action is unavailable and explains the exact existing Magento code', () => {
    const mapping = loadAttributeMapping();
    const root = {
        veaConfig: {existing_magento_attribute_codes: ['color']}
    };
    const card = {
        getAttribute: (name) => name === 'data-code' ? 'COLOR' : ''
    };
    const action = mapping.createMagentoAttributeAction(root, card, true);

    assert.equal(mapping.hasExistingMagentoAttribute(root, ' Color '), true);
    assert.equal(action.available, false);
    assert.equal(action.iconClass, 'vea-existing-magento-attribute-icon');
    assert.equal(action.role, 'entity-create-magento-attribute');
    assert.match(action.title, /COLOR/);
    assert.match(action.title, /już istnieje/);
    assert.match(action.ariaLabel, /Utwórz w Magento/);
});

test('create action remains available when Magento does not contain the code', () => {
    const mapping = loadAttributeMapping();
    const root = {
        veaConfig: {existing_magento_attribute_codes: ['size']}
    };
    const card = {
        getAttribute: (name) => name === 'data-code' ? 'color' : ''
    };
    const action = mapping.createMagentoAttributeAction(root, card, true);

    assert.equal(mapping.hasExistingMagentoAttribute(root, 'color'), false);
    assert.equal(action.available, true);
    assert.equal(action.iconClass, 'vea-create-option-icon');
});

test('draft mapping action renders the same disabled information state', () => {
    assert.match(pairTemplate, /\$magentoAttributeExists = \(bool\)\$block->getData\('magento_attribute_exists'\)/);
    assert.match(pairTemplate, /\? 'disabled' : ''/);
    assert.match(pairTemplate, /vea-existing-magento-attribute-icon/);
    assert.match(pairTemplate, /Atrybut Magento o kodzie „%1” już istnieje\./);
    assert.match(mappingScript, /hasExistingMagentoAttribute\(root, attributeCode\)/);
    assert.match(mappingScript, /attributeExists \? ' disabled' : ''/);
    assert.match(mappingStyles, /\.vea-existing-magento-attribute-icon/);
});

test('Storybook uses production mapping state and entity options implementations', () => {
    assert.match(mappingStory, /loadCore\('Ergonode_CoreAdminUi\/js\/attribute-mapping'\)/);
    assert.match(mappingStory, /entity-options\.js\?raw/);
    assert.match(mappingStory, /productionMapping\.createMagentoAttributeAction/);
    assert.match(mappingStory, /export const AllStates/);
    assert.match(mappingStory, /export const ExistingCodeKeyboard/);
});
