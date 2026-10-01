'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {readMappingTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const mappingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Attribute/Mapping.php'),
    'utf8'
);
const mappingTemplate = readMappingTemplate(moduleRoot, 'attribute');
const autoMatchStory = fs.readFileSync(
    path.join(moduleRoot, 'Test/Storybook/AttributeAutoMatch.stories.js'),
    'utf8'
);
const autoMatchController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Attribute/AutoMatch.php'),
    'utf8'
);

function loadAttributeMapping() {
    let exported;

    vm.runInNewContext(mappingScript, {
        define(names, factory) {
            exported = factory(...Array.from(names, (name) => {
                if (name === 'Ergonode_CoreAdminUi/js/text') {
                    return {normalize: (value) => String(value || '').trim().toLowerCase()};
                }
                if (name === 'mage/translate') {
                    return (value) => value;
                }

                return {};
            }));
        }
    });

    return exported;
}

test('admin auto match requests backend suggestions and leaves persistence to save action', () => {
    const handler = mappingScript.slice(
        mappingScript.indexOf('function initAutoMatch(root)'),
        mappingScript.indexOf('function initOptionMappingActions(root)')
    );

    assert.match(handler, /request\.post\(config\.urls \? config\.urls\.auto_match : ''/);
    assert.match(handler, /payload: JSON\.stringify\(collectSavePayload\(root\)\)/);
    assert.match(handler, /applyAutoMatches\(root, response\.matches\)/);
    assert.doesNotMatch(handler, /saveMappings\(/);
    assert.doesNotMatch(mappingScript, /scoreAutoMatch|completeDraftAutoMatches|getAutoMatchCards/);
});

test('admin auto match is enabled only when the backend returned a suggestion', () => {
    const mapping = loadAttributeMapping();
    const attributes = {};
    const button = {
        disabled: false,
        getAttribute(name) {
            return attributes[name] || null;
        },
        setAttribute(name, value) {
            attributes[name] = value;
        }
    };
    const root = {
        querySelector(selector) {
            return selector === '[data-role="auto-match"]' ? button : null;
        }
    };

    assert.equal(mapping.setAutoMatchAvailability(root, [], false), 0);
    assert.equal(button.disabled, true);
    assert.equal(attributes['data-available-count'], '0');

    assert.equal(mapping.setAutoMatchAvailability(root, [{}, {}], false), 2);
    assert.equal(button.disabled, false);
    assert.equal(attributes['data-available-count'], '2');

    assert.equal(mapping.setAutoMatchAvailability(root, [{}, {}], true), 2);
    assert.equal(button.disabled, true);
});

test('admin auto match starts disabled and refreshes availability from the shared backend endpoint', () => {
    const handler = mappingScript.slice(
        mappingScript.indexOf('function setAutoMatchAvailability(root, matches, pending)'),
        mappingScript.indexOf('function initOptionMappingActions(root)')
    );

    assert.match(mappingTemplate, /data-role="auto-match"[\s\S]*?disabled/);
    assert.match(mappingScript, /data-available-count/);
    assert.match(handler, /refreshAutoMatchAvailability\(root, requestId\)/);
    assert.match(handler, /request\.post\(config\.urls \? config\.urls\.auto_match : ''/);
    assert.match(handler, /setAutoMatchAvailability\(root, response\.matches, false\)/);
    assert.match(autoMatchStory, /productionMapping\.setAutoMatchAvailability/);
    assert.match(autoMatchStory, /export const AllAvailabilityStates/);
});

test('type compatibility is configured from backend instead of duplicated in JavaScript', () => {
    assert.match(mappingBlock, /ProductAttributeMappingCompatibility/);
    assert.match(mappingBlock, /'attribute_type_compatibility'/);
    assert.match(mappingBlock, /'magento_attribute_type_constraints'/);
    assert.match(mappingScript, /configureAttributeTypeCompatibility\(config && config\.attribute_type_compatibility\)/);
    assert.doesNotMatch(mappingScript, /leftType === 'select'|leftType === 'numeric'|isTextLike/);
});

test('auto match endpoint delegates all business decisions to the shared mapper', () => {
    assert.match(autoMatchController, /AttributeAutoMatcher/);
    assert.match(autoMatchController, /attributeAutoMapper->suggest\(\$mappings, \$visibility\)/);
    assert.doesNotMatch(autoMatchController, /AttributeMappingSaver|->save\(/);
});
