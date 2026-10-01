'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {resolveModuleRoot} = require('./module-contract.cjs');
const vm = require('node:vm');
const {readMappingTemplate, readPairTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const baseModuleRoot = resolveModuleRoot('Ergonode_ProductAttributeConsumer');
const pairTemplate = readPairTemplate(moduleRoot, 'attribute');
const mappingTemplate = readMappingTemplate(moduleRoot, 'attribute');
const optionMappingTemplate = readMappingTemplate(moduleRoot, 'option');
const mappingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Attribute/Mapping.php'),
    'utf8'
);
const progressProvider = fs.readFileSync(
    path.join(baseModuleRoot, 'Model/Mapping/AttributeMappingProvider.php'),
    'utf8'
);

function loadNavigation(confirm) {
    let exported;
    const fileName = path.join(
        coreRoot,
        'view/adminhtml/web/js/attribute-option-navigation.js'
    );
    const sandbox = {
        define(names, factory) {
            assert.deepEqual(Array.from(names), [
                'Magento_Ui/js/modal/confirm',
                'mage/translate'
            ]);
            exported = factory(confirm, (value) => value);
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

function loadAttributeMapping() {
    let exported;
    const fileName = path.join(
        coreRoot,
        'view/adminhtml/web/js/attribute-mapping.js'
    );
    const mappingBoard = {
        getSlotSide() {},
        getOppositeSlot() {},
        getSlotType() {},
        hasSlotValue() {},
        getStatusTone() {},
        updateRowSearchData() {},
        showRowMessage() {},
        hideRowMessage() {},
        isRowEmpty() {}
    };
    const textHelpers = {
        normalize(value) {
            return String(value || '').trim().toLowerCase();
        }
    };
    const dependencies = {
        'Ergonode_CoreAdminUi/js/mapping-board': mappingBoard,
        'Ergonode_CoreAdminUi/js/text': textHelpers,
        'mage/translate': (value) => value
    };
    const sandbox = {
        define(names, factory) {
            exported = factory(...Array.from(names, (name) => dependencies[name] || {}));
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

test('saved option-compatible pairs expose progress and an accessible navigation action', () => {
    assert.match(pairTemplate, /\$mapping\['option_progress'\]/);
    assert.match(pairTemplate, /class="vea-option-map-progress"/);
    assert.doesNotMatch(pairTemplate, /data-role="option-mapping-progress"/);
    assert.match(pairTemplate, /data-role="option-mapping-action"/);
    assert.match(pairTemplate, /data-url="<\?= \$escaper->escapeUrl\(\$url\) \?>"/);
    assert.match(pairTemplate, /data-modal-url="<\?= \$escaper->escapeUrl\(\$modalUrl\) \?>"/);
    assert.match(pairTemplate, /aria-label="<\?= \$escaper->escapeHtmlAttr\(\$label\) \?>"/);
});

test('option progress is loaded in batches for option-compatible attribute contexts', () => {
    assert.match(progressProvider, /getOptionAttributeContexts\(\)/);
    assert.match(progressProvider, /ergonodeOptionProvider->getOptionCounts/);
    assert.match(progressProvider, /mappingReader->getCompleteOptionCounts/);
    assert.doesNotMatch(progressProvider, /fetchPairs|ResourceConnection/);
});

test('editing a saved pair hides its stale option-mapping action', () => {
    assert.match(mappingScript, /function updateOptionMappingAction\(row\)/);
    assert.match(mappingScript, /data-saved-ergo-code/);
    assert.match(mappingScript, /data-saved-magento-code/);
    assert.match(mappingScript, /entry\.hidden = !available/);
    assert.match(mappingScript, /window\.location\.assign\(url\)/);
});

test('saved option actions can open the embedded option workspace', () => {
    assert.match(mappingScript, /Ergonode_CoreAdminUi\/js\/option-mapping-modal/);
    assert.match(mappingScript, /button\.getAttribute\('data-modal-url'\)/);
    assert.match(mappingScript, /optionMappingModal\.open\(root\.veaWorkspace, root, modalUrl\)/);
    assert.match(optionMappingTemplate, /\$embedded \? ' vea-option-mapping-embedded' : ''/);
    assert.match(optionMappingTemplate, /if \(!\$embedded\)/);
});

test('clean attribute mappings navigate to options without a confirmation', () => {
    let confirmCalls = 0;
    let navigations = 0;
    const navigation = loadNavigation(() => {
        confirmCalls += 1;
    });

    navigation.request({
        dirty: false,
        navigate() {
            navigations += 1;
        }
    });

    assert.equal(confirmCalls, 0);
    assert.equal(navigations, 1);
});

test('dirty attribute mappings require save confirmation before navigation', () => {
    let config;
    let navigations = 0;
    let saves = 0;
    const navigation = loadNavigation((options) => {
        config = options;
    });

    navigation.request({
        dirty: true,
        navigate() {
            navigations += 1;
        },
        saveAndNavigate() {
            saves += 1;
        }
    });

    assert.equal(navigations, 0);
    assert.equal(saves, 0);
    assert.equal(config.title, 'Zapisz zmiany przed przejściem');
    assert.match(config.content, /najpierw je zapisz/);
    assert.deepEqual(Array.from(config.buttons, (button) => button.text), [
        'Anuluj',
        'Zapisz i przejdź'
    ]);

    config.actions.confirm();
    assert.equal(saves, 1);
});

test('attribute mapping tracks its initial payload through the save action', () => {
    assert.match(mappingScript, /function hasUnsavedChanges\(root\)/);
    assert.match(mappingScript, /workspaceContext\.create\(scope, element, \{/);
    assert.match(mappingScript, /serialize: function \(\) \{[\s\S]*?collectSavePayload\(element\)/);
    assert.match(mappingScript, /root\.veaContext\.dirty\.isDirty\(\)/);
    assert.match(mappingScript, /root\.veaContext\.dirty\.capture\(\)/);
    assert.doesNotMatch(mappingScript, /veaInitialSavePayload|serializeSavePayload/);
    assert.match(mappingScript, /button\.disabled = !dirty/);
    assert.match(mappingTemplate, /data-role="save-mapping"[\s\S]*?disabled/);
    assert.doesNotMatch(mappingTemplate, /data-role="unsaved-state"|Niezapisane zmiany/);
    assert.doesNotMatch(mappingScript, /data-role="unsaved-state"|has-unsaved-changes/);
    assert.match(mappingScript, /optionNavigation\.request\(\{/);
    assert.match(mappingScript, /saveMappings\(root\)\.then\(navigate\)/);
});

test('attribute type compatibility is exported for optional mapping extensions', () => {
    assert.match(mappingScript, /initAttributeMapping\.canMapAttributeTypes = canMapAttributeTypes/);
    assert.match(
        mappingScript,
        /initAttributeMapping\.configureAttributeTypeCompatibility = configureAttributeTypeCompatibility/
    );
    assert.match(
        mappingScript,
        /initAttributeMapping\.configureMagentoAttributeTypeConstraints = configureMagentoAttributeTypeConstraints/
    );
    assert.match(mappingBlock, /magento_attribute_type_constraints/);
    assert.match(mappingTemplate, /->setData\('type_compatible', \$block->isTypeCompatible\(\$mapping\)\)/);
    assert.doesNotMatch(pairTemplate, /\$isTypeCompatible = static function/);
    assert.match(mappingScript, /return initAttributeMapping/);
});

test('empty attribute slots expose every compatible target type', () => {
    const mapping = loadAttributeMapping();

    mapping.configureAttributeTypeCompatibility({
        multiselect: ['multiselect', 'text', 'textarea'],
        select: ['select', 'text', 'textarea', 'multiselect', 'boolean'],
        text: ['text', 'textarea', 'select', 'multiselect'],
        textarea: ['textarea', 'text', 'select', 'multiselect']
    });

    assert.deepEqual(
        Array.from(mapping.compatibleAttributeTypes('ergo', 'multiselect')),
        ['multiselect', 'select', 'text', 'textarea']
    );
    assert.deepEqual(
        Array.from(mapping.compatibleAttributeTypes('magento', 'select')),
        ['select', 'boolean', 'multiselect', 'text', 'textarea']
    );
    assert.match(mappingScript, /class="vea-compatible-types" data-role="slot-hint"/);
    assert.match(mappingScript, /\$t\('Zgodne typy'\)/);
    assert.doesNotMatch(mappingScript, /mappingElements\.typeBadgeHtml\(requiredType, 'slot-hint'\)/);
});

test('system attribute targets expose only their supported Ergonode source type', () => {
    const mapping = loadAttributeMapping();

    mapping.configureAttributeTypeCompatibility({
        file: ['file', 'text', 'textarea'],
        multiselect: ['multiselect', 'text', 'textarea'],
        price: ['price', 'decimal', 'text', 'textarea'],
        select: ['select', 'text', 'textarea', 'multiselect', 'boolean'],
        text: ['text', 'textarea', 'select', 'multiselect'],
        textarea: ['textarea', 'text', 'select', 'multiselect']
    });
    mapping.configureMagentoAttributeTypeConstraints({
        name: ['text'],
        url_key: ['text'],
        price: ['price']
    });

    assert.deepEqual(
        Array.from(mapping.compatibleAttributeTypes('ergo', 'text', 'name')),
        ['text']
    );
    assert.deepEqual(
        Array.from(mapping.compatibleAttributeTypes('ergo', 'text', 'url_key')),
        ['text']
    );
    assert.deepEqual(
        Array.from(mapping.compatibleAttributeTypes('ergo', 'price', 'price')),
        ['price']
    );
    assert.deepEqual(
        Array.from(mapping.compatibleAttributeTypes('ergo', 'text', 'description_short')),
        ['text', 'file', 'multiselect', 'price', 'select', 'textarea']
    );
    assert.equal(mapping.canMapAttributeTypes('textarea', 'text', 'name'), false);
    assert.equal(mapping.canMapAttributeTypes('text', 'text', 'name'), true);
    assert.equal(mapping.canMapAttributeTypes('price', 'price', 'price'), true);
});
