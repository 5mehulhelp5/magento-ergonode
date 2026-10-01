const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const modulePath = path.resolve(
    __dirname,
    '../../view/adminhtml/web/js/language-source-options.js'
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

function fixture({canRefresh = true, excluded = true, storeViewExcluded = true} = {}) {
    const createdActions = [];
    const menus = [];
    const createButton = () => {
        const attributes = {'aria-pressed': 'false'};

        return {
            attributes,
            disabled: false,
            getAttribute(name) {
                return attributes[name] || null;
            },
            setAttribute(name, value) {
                attributes[name] = String(value);
            }
        };
    };
    const button = createButton();
    const autoMatchAttributes = {};
    const autoMatchButton = {
        children: [],
        disabled: false,
        ownerDocument: {
            createElement() {
                const attributes = {};

                return {
                    className: '',
                    hidden: false,
                    textContent: '',
                    getAttribute(name) {
                        return attributes[name] || null;
                    },
                    setAttribute(name, value) {
                        attributes[name] = String(value);
                    }
                };
            }
        },
        querySelector(selector) {
            return selector === '[data-role="auto-match-count"]'
                ? this.children[0] || null
                : null;
        },
        appendChild(child) {
            this.children.push(child);
        },
        getAttribute(name) {
            return autoMatchAttributes[name] || null;
        },
        setAttribute(name, value) {
            autoMatchAttributes[name] = String(value);
        }
    };
    const storeViewButton = createButton();
    const makeTools = () => {
        const placeholder = {
            removed: false,
            remove() {
                this.removed = true;
            }
        };
        const tools = {
            child: null,
            placeholder,
            appendChild(child) {
                this.child = child;
            },
            querySelector(selector) {
                return selector === '[data-source-options]' ? this.child : this.placeholder;
            }
        };

        return tools;
    };
    const sourceTools = makeTools();
    const storeViewTools = makeTools();
    const makePanel = (source, tools, visibilityButton, hasExcluded) => {
        const attributes = {
            'data-source-panel': source,
            'data-can-refresh': source === 'ergo' && canRefresh ? '1' : '0',
            'data-source-options-label': source === 'ergo'
                ? 'Language actions: Ergonode'
                : 'Language actions: Magento',
            'data-show-excluded-hint': 'Show excluded',
            'data-hide-excluded-hint': 'Hide excluded'
        };
        const toggle = {
            getAttribute() {
                return hasExcluded ? 'false' : 'true';
            }
        };

        return {
            getAttribute(name) {
                return attributes[name] || null;
            },
            querySelector(selector) {
                if (selector === '.vel-side-tools') {
                    return tools;
                }

                return selector.includes('visibility-toggle') ? visibilityButton : null;
            },
            querySelectorAll() {
                return [toggle];
            }
        };
    };
    const panel = makePanel('ergo', sourceTools, button, excluded);
    const storeViewPanel = makePanel('magento', storeViewTools, storeViewButton, storeViewExcluded);
    const mappingPanel = {
        querySelector(selector) {
            return selector === '[data-role="auto-match"]' ? autoMatchButton : null;
        }
    };
    const root = {
        querySelector(selector) {
            if (selector === '[data-role="source-panel"][data-source-panel="ergo"]') {
                return panel;
            }
            if (selector === '[data-role="mapping-panel"] [data-role="auto-match"]') {
                return mappingPanel.querySelector('[data-role="auto-match"]');
            }

            return null;
        },
        querySelectorAll(selector) {
            return selector === '[data-role="source-panel"]'
                ? [panel, storeViewPanel]
                : [];
        }
    };
    const entityOptions = {
        create(options) {
            const classes = [];
            const attributes = {};
            const menu = {
                attributes,
                classes,
                options,
                classList: {
                    add(name) {
                        classes.push(name);
                    }
                },
                setAttribute(name, value) {
                    attributes[name] = String(value);
                }
            };

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
        autoMatchAttributes,
        autoMatchButton,
        button,
        createdActions,
        menus,
        placeholders: [sourceTools.placeholder, storeViewTools.placeholder],
        root,
        sourceOptions: loadModule(entityOptions, visibilityToggle),
        storeViewButton,
        visibilityCalls
    };
}

test('source options keep Auto Connect out of the left language column', () => {
    const state = fixture();
    const menu = state.sourceOptions.initialize(state.root);

    assert.equal(menu, state.menus[0]);
    assert.deepEqual(
        state.createdActions.map((action) => action.role),
        ['refresh-ergonode', 'visibility-toggle', 'visibility-toggle']
    );
    assert.deepEqual(
        state.createdActions.map((action) => action.label),
        ['Refresh', 'Excluded', 'Excluded']
    );
    assert.equal(state.menus[0].options.menuLabel, 'Language actions: Ergonode');
    assert.equal(state.menus[1].options.menuLabel, 'Language actions: Magento');
    state.menus.forEach((sourceMenu) => {
        assert.deepEqual(sourceMenu.classes, ['veui-source-options', 'vel-source-options']);
    });
    assert.equal(state.menus[0].attributes['data-source-options'], 'ergo');
    assert.equal(state.menus[0].attributes['data-language-source-options'], '1');
    assert.equal(state.menus[1].attributes['data-source-options'], 'magento');
    assert.equal(state.button.disabled, false);
    assert.equal(state.storeViewButton.disabled, false);
    assert.equal(state.autoMatchButton.disabled, true);
    assert.equal(state.autoMatchAttributes['data-available-count'], '0');
    assert.deepEqual(state.placeholders.map((placeholder) => placeholder.removed), [true, true]);
});

test('Auto Connect exposes its available combinations and disables itself at zero', () => {
    const state = fixture();

    state.sourceOptions.initialize(state.root);
    state.sourceOptions.sync(state.root, 4);

    assert.equal(state.autoMatchButton.disabled, false);
    assert.equal(state.autoMatchAttributes['data-available-count'], '4');
    assert.equal(state.autoMatchAttributes['aria-label'],
        'Automatically connect languages to Store View locales: 4');
    assert.equal(state.autoMatchButton.children[0].textContent, '4');
    assert.equal(state.autoMatchButton.children[0].hidden, false);

    state.sourceOptions.sync(state.root, 0);

    assert.equal(state.autoMatchButton.disabled, true);
    assert.equal(state.autoMatchButton.children[0].hidden, true);
});

test('Excluded is disabled when only another source panel has excluded items', () => {
    const state = fixture({excluded: false});

    state.sourceOptions.initialize(state.root);

    assert.equal(state.button.disabled, true);
    assert.deepEqual(state.visibilityCalls.at(-1), [
        'setVisible',
        state.button,
        false
    ]);
    assert.equal(state.button.getAttribute('aria-pressed'), 'false');
    assert.equal(state.storeViewButton.disabled, false);
});

test('Store View visibility is disabled independently when the right panel has no excluded items', () => {
    const state = fixture({excluded: true, storeViewExcluded: false});

    state.sourceOptions.initialize(state.root);

    assert.equal(state.button.disabled, false);
    assert.equal(state.storeViewButton.disabled, true);
    assert.deepEqual(state.visibilityCalls.at(-1), [
        'setVisible',
        state.storeViewButton,
        false
    ]);
});

test('Refresh is omitted when the administrator lacks refresh permission', () => {
    const state = fixture({canRefresh: false});

    state.sourceOptions.initialize(state.root);

    assert.deepEqual(
        state.createdActions.map((action) => action.role),
        ['visibility-toggle', 'visibility-toggle']
    );
});
