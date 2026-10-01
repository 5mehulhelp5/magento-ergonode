'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {modulePath} = require('./module-paths.cjs');

const coreRoot = path.resolve(__dirname, '../..');

function read(root, relativePath) {
    return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function readModule(relativePath) {
    const [moduleName, ...segments] = relativePath.split('/');
    return read(modulePath(moduleName), path.join(...segments));
}

const entityOptions = read(coreRoot, 'view/adminhtml/web/js/entity-options.js');
const snapshotRemoval = read(coreRoot, 'view/adminhtml/web/js/snapshot-removal.js');
const styles = read(coreRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
const attributeStyles = read(coreRoot, 'view/adminhtml/web/css/attribute-mapping.css');
const mappingElements = read(coreRoot, 'view/adminhtml/web/js/mapping-elements.js');
const language = readModule('LanguageAdminUi/view/adminhtml/web/js/language-mapping.js');
const template = readModule('TemplateAdminUi/view/adminhtml/web/js/template-admin.js');
const templateConsumer = readModule('TemplateConsumerAdminUi/view/adminhtml/web/js/template-consumer.js');
const attribute = readModule('CoreAdminUi/view/adminhtml/web/js/attribute-mapping.js');
const option = readModule('CoreAdminUi/view/adminhtml/web/js/option-mapping.js');
const category = readModule('CategoryAdminUi/view/adminhtml/web/js/category-tree-mapping.js');
const categoryStyles = readModule('CategoryAdminUi/view/adminhtml/web/css/category-tree-mapping.css');
const categoryStory = readModule('CategoryConsumerAdminUi/Test/Storybook/CategoryTreeMapping.stories.js');

function loadSnapshotRemoval(entityOptionsStub) {
    let exported;

    vm.runInNewContext(snapshotRemoval, {
        define: (dependencies, factory) => {
            assert.deepEqual(Array.from(dependencies), [
                'Ergonode_CoreAdminUi/js/request',
                'Ergonode_CoreAdminUi/js/buttons',
                'Ergonode_CoreAdminUi/js/entity-options',
                'mage/translate'
            ]);
            exported = factory({}, {}, entityOptionsStub, (value) => value);
        }
    });

    return exported;
}

test('entity options are a shared accessible card action primitive', () => {
    assert.match(entityOptions, /data-role', 'entity-options'/);
    assert.match(entityOptions, /summary\.setAttribute\('role', 'button'\)/);
    assert.match(entityOptions, /scope\.delegate\('keydown', '\[data-role="entity-options"\] > summary'/);
    assert.match(entityOptions, /event\.key !== 'Enter' && event\.key !== ' '/);
    assert.match(entityOptions, /closeOthers\(root, menu\)[\s\S]*menu\.setAttribute\('open', ''\)/);
    assert.match(entityOptions, /event\.key !== 'Escape'/);
    assert.match(entityOptions, /close\(menu, true\)/);
    assert.match(entityOptions, /createAction: buildAction/);
    assert.match(entityOptions, /options\.attributes/);
    assert.match(entityOptions, /return active \? \$t\('Exclude'\) : \$t\('Include'\)/);
    assert.doesNotMatch(entityOptions, /Wyklucz z mapowania|Uwzględnij w mapowaniu/);
    assert.match(entityOptions, /setActiveState/);
    assert.match(entityOptions, /setActionsEnabled/);
    assert.match(styles, /\.veui-entity-options > summary/);
    assert.match(styles, /\.veui-entity-options-menu/);
    assert.match(styles, /\.veui-entity-options-menu > \.veui-entity-options-action:disabled/);
    assert.match(styles, /\.veui-entity-options-delete-icon/);
    assert.doesNotMatch(attributeStyles, /\.veui-entity-options\s*\{/);
    assert.match(mappingElements, /\[data-role="entity-options"\]/);
});

test('snapshot removal is a shared entity-options domain action', () => {
    assert.match(snapshotRemoval, /Ergonode_CoreAdminUi\/js\/entity-options/);
    assert.match(snapshotRemoval, /function enhance\(card, options\)/);
    assert.match(snapshotRemoval, /function mappingAction\(options\)/);
    assert.match(snapshotRemoval, /entityOptions\.enhance/);
    assert.match(snapshotRemoval, /entityOptions\.bind/);
    assert.match(snapshotRemoval, /setActiveState: entityOptions\.setActiveState/);
    assert.match(snapshotRemoval, /role \|\| 'entity-delete-snapshot'/);
    assert.match(snapshotRemoval, /Remove from list/);
    assert.doesNotMatch(snapshotRemoval, /Remove from (?:the local )?snapshot/);
    assert.match(snapshotRemoval, /config\.urls && config\.urls\.delete_snapshot/);
    assert.match(snapshotRemoval, /Save mapping changes before removing this item/);
    assert.match(snapshotRemoval, /function requestRemoval\(card\)/);
    assert.match(snapshotRemoval, /event\.stopImmediatePropagation\(\)/);
});

test('snapshot removal can be requested through the existing card action', () => {
    let clicked = 0;
    const button = {
        click: () => {
            clicked += 1;
        },
        disabled: false
    };
    const card = {
        querySelector: (selector) => {
            assert.equal(selector, '[data-role="entity-delete-snapshot"]');

            return button;
        }
    };
    const facade = loadSnapshotRemoval({});

    assert.equal(facade.requestRemoval(card), true);
    assert.equal(clicked, 1);
    button.disabled = true;
    assert.equal(facade.requestRemoval(card), false);
    assert.equal(facade.requestRemoval(null), false);
    assert.equal(clicked, 1);
});

test('snapshot removal menu action is omitted when the domain endpoint is unavailable', () => {
    const received = [];
    const facade = loadSnapshotRemoval({
        enhance: (card, options) => {
            received.push(options.actions);

            return card;
        }
    });

    const snapshot = {code: 'color', label: 'Color'};

    facade.enhance({}, {mappingAction: true, removable: false, actions: [], snapshot});
    facade.enhance({}, {mappingAction: true, actions: [], snapshot});

    assert.deepEqual(Array.from(received[0], (action) => action.role), ['entity-add-to-mapping']);
    assert.deepEqual(Array.from(received[1], (action) => action.role), [
        'entity-add-to-mapping', 'entity-delete-snapshot'
    ]);
});

test('snapshot facade composes and binds the shared entity options primitive', () => {
    let enhancedOptions;
    let bound = false;
    const card = {
        getAttribute: (name) => ({
            'data-code': 'color',
            'data-label': 'Kolor'
        })[name] || ''
    };
    const entityOptionsStub = {
        bind: () => {
            bound = true;
        },
        enhance: (target, options) => {
            assert.equal(target, card);
            enhancedOptions = options;

            return 'menu';
        },
        setActiveState: () => {}
    };
    const facade = loadSnapshotRemoval(entityOptionsStub);
    const menu = facade.enhance(card, {
        active: false,
        actions: [{role: 'entity-create-in-magento'}],
        mappingAction: true,
        menuLabel: 'Opcje atrybutu: Kolor'
    });

    assert.equal(menu, 'menu');
    assert.equal(enhancedOptions.actions.length, 3);
    assert.equal(enhancedOptions.actions[0].role, 'entity-add-to-mapping');
    assert.equal(enhancedOptions.actions[0].active, false);
    assert.equal(enhancedOptions.actions[2].role, 'entity-delete-snapshot');
    assert.equal(enhancedOptions.actions[2].attributes['data-code'], 'color');
    assert.equal(enhancedOptions.active, false);
    assert.equal(enhancedOptions.menuLabel, 'Opcje atrybutu: Kolor');

    facade.bind({claim: () => false}, {}, {}, {});
    assert.equal(bound, true);
});

test('left Ergonode columns use entity options with domain actions', () => {
    [language, attribute, option].forEach((source) => {
        assert.doesNotMatch(source, /entityOptions\.(enhance|create)\(/);
        assert.match(source, /Ergonode_CoreAdminUi\/js\/snapshot-removal/);
        assert.match(source, /snapshotRemoval\.enhance/);
        assert.match(source, /snapshotRemoval\.bind/);
        assert.doesNotMatch(source, /snapshotRemoval\.action/);
        assert.match(source, /mappingAction: true/);
        assert.doesNotMatch(source, /label: \$t\('Dodaj do mapowania'\)/);
    });

    assert.match(language, /entityOptions\.bind\(scope, root\)/, 'Viewer source menus retain shared behavior');
    assert.doesNotMatch(language, /entity-create-magento/);
    assert.match(attribute, /entity-create-magento-attribute/);
    assert.match(option, /role: 'entity-create-magento-option'/);
    assert.match(template, /Ergonode_CoreAdminUi\/js\/entity-options/);
    assert.match(template, /entityOptions\.enhance/);
    assert.match(template, /entityOptions\.bind\(scope, element\)/);
    assert.doesNotMatch(template, /Ergonode_CoreAdminUi\/js\/snapshot-removal/);
    assert.match(templateConsumer, /Ergonode_CoreAdminUi\/js\/snapshot-removal/);
    assert.match(templateConsumer, /snapshotRemoval\.bind\(scope, element, config/);
    assert.match(templateConsumer, /snapshotRemoval\.enhance/);
    assert.match(templateConsumer, /mappingAction: true/);
    assert.match(templateConsumer, /scope\.listen\(element, 'ergonode:template-rendered', enhanceSnapshots\)/);
    assert.doesNotMatch(template, /role: 'entity-create-magento-attribute-set'/);
    assert.match(template, /createPendingAttributeSet\(draftId\)/);
    assert.match(attribute, /root\.veaConfig\.allow_magento_attribute_creation/);
    assert.match(attribute, /create-magento-attribute/);
    assert.match(option, /row\.querySelector\('\[data-role="create-magento-option"\]'\)/);

});

test('mapping views share the neutral entity card role', () => {
    const sources = [language, attribute, option, template];

    sources.forEach((source) => {
        assert.match(source, /entity-card/);
        assert.doesNotMatch(source, /\[data-role="(?:source-card|attribute-card)"\]/);
    });
});

test('snapshot removal endpoints are exposed by every Ergonode source view', () => {
    const endpoints = [
        ['LanguageAdminUi/Block/Adminhtml/Language/Mapping.php', /language\/deleteSnapshot/],
        [
            'CategoryAttributeConsumerAdminUi/Plugin/MappingCapabilitiesPlugin.php',
            /delete_snapshot[\s\S]*category_' \. \$kind \. '\/deleteSnapshot/
        ],
        ['TemplateConsumerAdminUi/Block/Adminhtml/Template/Actions.php', /template\/deleteSnapshot/],
    ];
    const controllers = [
        'LanguageAdminUi/Controller/Adminhtml/Language/DeleteSnapshot.php',
        'CategoryAttributeConsumerAdminUi/Controller/Adminhtml/Category/Attribute/DeleteSnapshot.php',
        'CategoryAttributeConsumerAdminUi/Controller/Adminhtml/Category/Option/DeleteSnapshot.php',
        'TemplateConsumerAdminUi/Controller/Adminhtml/Template/DeleteSnapshot.php',
    ];

    for (const [view, kind] of [['CategoryAttribute', 'attribute'], ['CategoryOption', 'option']]) {
        assert.match(
            readModule(`CategoryAttributeAdminUi/Block/Adminhtml/${view}/Mapping.php`),
            new RegExp(`capabilities->getConfig\\('${kind}'\\)`)
        );
    }

    endpoints.forEach(([path, pattern]) => {
        assert.match(readModule(path), pattern);
    });

    controllers.forEach((path) => {
        const controller = readModule(path);

        assert.match(controller, /implements HttpPostActionInterface/);
        assert.match(controller, /public const string ADMIN_RESOURCE/);
    });
});

test('category consumer runtime and story reuse the shared entity options implementation', () => {
    assert.match(category, /Ergonode_CoreAdminUi\/js\/entity-options/);
    assert.match(category, /entityOptions\.bind\(scope, element\)/);
    assert.match(category, /return \$\(entityOptions\.create\(/);
    assert.doesNotMatch(categoryStyles, /\.veui-entity-options/);
    assert.match(categoryStory, /entity-options\.js\?raw/);
    assert.match(categoryStory, /productionEntityOptions\.create\(/);
    assert.match(categoryStory, /productionEntityOptions\.createAction\(/);
});
