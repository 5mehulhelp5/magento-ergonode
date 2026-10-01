'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const modulePath = path.resolve(
    __dirname,
    '../../view/adminhtml/web/js/template-source-options.js'
);

function loadModule(entityOptions, visibilityToggle) {
    let sourceOptions;

    vm.runInNewContext(fs.readFileSync(modulePath, 'utf8'), {
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), [
                'Ergonode_CoreAdminUi/js/entity-options',
                'Ergonode_CoreAdminUi/js/visibility-toggle',
                'mage/translate'
            ]);
            sourceOptions = factory(entityOptions, visibilityToggle, (value) => value);
        }
    });

    return sourceOptions;
}

function fixture() {
    const createdActions = [];
    const menus = [];
    const buttonAttributes = {'aria-pressed': 'false'};
    const button = {
        disabled: false,
        getAttribute(name) {
            return buttonAttributes[name] || null;
        },
        setAttribute(name, value) {
            buttonAttributes[name] = String(value);
        }
    };
    function createPanel(menuAttribute, label, attributes = {}) {
        const placeholder = {
            removed: false,
            remove() {
                this.removed = true;
            }
        };
        const tools = {
            child: null,
            appendChild(child) {
                this.child = child;
            },
            querySelector(selector) {
                if (selector.includes(menuAttribute)) {
                    return this.child;
                }

                return selector.includes('entity-options-placeholder') ? placeholder : null;
            }
        };
        const panelAttributes = Object.assign({'data-source-options-label': label}, attributes);

        return {
            panel: {
                getAttribute(name) {
                    return panelAttributes[name] || null;
                },
                querySelector(selector) {
                    return selector === '.vet-side-tools' ? tools : null;
                }
            },
            placeholder
        };
    }

    const template = createPanel(
        'data-template-source-options',
        'Template actions: Ergonode',
        {
            'data-show-excluded-hint': 'Show excluded',
            'data-hide-excluded-hint': 'Hide excluded'
        }
    );
    const attributeSet = createPanel(
        'data-attribute-set-source-options',
        'Attribute set actions: Magento'
    );
    const root = {
        querySelector(selector) {
            if (selector.includes('data-template-source-options') && selector.includes('visibility-toggle')) {
                return button;
            }
            if (selector.includes('template-drop-source')) {
                return template.panel;
            }
            if (selector.includes('attribute-set-drop-source')) {
                return attributeSet.panel;
            }

            return null;
        }
    };
    const entityOptions = {
        create(options) {
            const menuClasses = [];
            const menuAttributes = {};
            const menu = {
                classList: {
                    add(name) {
                        menuClasses.push(name);
                    }
                },
                setAttribute(name, value) {
                    menuAttributes[name] = String(value);
                }
            };

            menu.options = options;
            menu.classes = menuClasses;
            menu.attributes = menuAttributes;
            menus.push(menu);

            return menu;
        },
        createAction(options) {
            createdActions.push(options);

            return {options};
        }
    };
    const visibilityCalls = [];
    const visibilityToggle = {
        initialize(element) {
            visibilityCalls.push(['initialize', element]);
        },
        setVisible(element, visible) {
            element.setAttribute('aria-pressed', visible ? 'true' : 'false');
            visibilityCalls.push(['setVisible', element, visible]);
        }
    };

    return {
        attributeSet,
        button,
        createdActions,
        menus,
        root,
        sourceOptions: loadModule(entityOptions, visibilityToggle),
        template,
        visibilityCalls
    };
}

test('base source options expose visibility and sorting beside both searches', () => {
    const state = fixture();
    const menu = state.sourceOptions.initialize(state.root, true);

    assert.equal(menu, state.menus[0]);
    assert.equal(state.menus.length, 2);
    assert.deepEqual(
        state.createdActions.map((action) => action.role),
        [
            'visibility-toggle',
            'source-sort-direction',
            'source-sort-toggle',
            'source-sort-direction',
            'source-sort-toggle'
        ]
    );
    assert.deepEqual(
        state.createdActions.map((action) => action.label),
        ['Excluded', 'Góra', 'Nazwa', 'Góra', 'Nazwa']
    );
    assert.equal(state.menus[0].options.menuLabel, 'Template actions: Ergonode');
    assert.equal(state.menus[1].options.menuLabel, 'Attribute set actions: Magento');
    state.menus.forEach((sourceMenu) => {
        assert.deepEqual(sourceMenu.classes, ['veui-source-options', 'vet-source-options']);
    });
    assert.equal(state.menus[0].attributes['data-template-source-options'], '1');
    assert.equal(state.menus[1].attributes['data-attribute-set-source-options'], '1');
    assert.equal(state.template.placeholder.removed, true);
    assert.equal(state.attributeSet.placeholder.removed, true);
    assert.equal(state.button.disabled, false);
});

test('Excluded is disabled and reset when every source is included', () => {
    const state = fixture();

    state.sourceOptions.initialize(state.root, false);

    assert.equal(state.button.disabled, true);
    assert.deepEqual(state.visibilityCalls.at(-1), [
        'setVisible',
        state.button,
        false
    ]);
    assert.equal(state.button.getAttribute('aria-pressed'), 'false');
});
