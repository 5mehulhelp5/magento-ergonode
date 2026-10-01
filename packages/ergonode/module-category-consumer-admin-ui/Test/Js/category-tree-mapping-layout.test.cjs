'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const {projectRoot, moduleRoot: findModuleRoot} = require('./project-paths.cjs');
const moduleRoot = findModuleRoot('CategoryAdminUi', 'module-category-admin-ui');
const consumerAdminUiRoot = path.resolve(__dirname, '../..');
const consumerScript = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/web/js/category-mapping-consumer.js'), 'utf8');
const consumerLayout = fs.readFileSync(path.resolve(__dirname, '../../view/adminhtml/layout/ergonode_category_tree_mapping_edit.xml'), 'utf8');

function loadMappingState() {
    let exported;
    const fileName = path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping-state.js');
    const sandbox = {
        define(names, factory) {
            assert.deepEqual(Array.from(names), []);
            exported = factory();
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

function loadSubtreeMapping() {
    let exported;
    const fileName = path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-subtree-mapping.js');
    const sandbox = {
        define(names, factory) {
            assert.deepEqual(Array.from(names), []);
            exported = factory();
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

function loadNativeDrag($) {
    let exported;
    const fileName = path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-native-drag.js');
    const sandbox = {
        define(names, factory) {
            assert.deepEqual(Array.from(names), ['jquery']);
            exported = factory($);
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

function loadConfigurationOrder() {
    let exported;
    const fileName = path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-configuration-order.js');
    const sandbox = {
        define(names, factory) {
            assert.deepEqual(Array.from(names), []);
            exported = factory();
        }
    };

    vm.runInNewContext(fs.readFileSync(fileName, 'utf8'), sandbox, {filename: fileName});

    return exported;
}

function createDragHarness() {
    const handlers = {};
    const dropZones = [];

    function createElement(dataValues) {
        return {
            attributes: {},
            classes: new Set(),
            dataValues,
            jqueryUiDraggable: false,
            addClass(name) {
                this.classes.add(name);
                return this;
            },
            attr(name, value) {
                if (value === undefined) {
                    return this.attributes[name];
                }
                this.attributes[name] = String(value);
                return this;
            },
            draggable() {
                this.jqueryUiDraggable = true;
                return this;
            },
            data(name) {
                return this.dataValues[name];
            },
            removeClass(name) {
                this.classes.delete(name);
                return this;
            }
        };
    }

    const source = createElement({
        'drag-type': 'category',
        'drag-value': 'chairs',
        code: 'chairs'
    });
    const target = createElement({
        'drop-zone': 'magento-target',
        'magento-id': 12
    });
    const root = {
        find() {
            return {
                removeClass(name) {
                    dropZones.forEach((zone) => zone.removeClass(name));
                }
            };
        },
        on(eventName, selector, handler) {
            handlers[eventName] = {handler, selector};
            return this;
        }
    };
    const $ = (element) => element;

    dropZones.push(target);
    $.contains = () => false;

    return {handlers, root, source, target, $};
}

function createDragEvent(dataTransfer) {
    return {
        defaultPrevented: false,
        propagationStopped: false,
        originalEvent: {dataTransfer, relatedTarget: null},
        preventDefault() {
            this.defaultPrevented = true;
        },
        stopPropagation() {
            this.propagationStopped = true;
        }
    };
}

test('category tree mapping view has Ergonode, Magento and settings columns in order', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const synchronizationActions = fs.readFileSync(
        path.resolve(
            projectRoot,
            'vendor/ergonode/module-core-admin-ui/view/adminhtml/templates/synchronization/actions.phtml'
        ),
        'utf8'
    );
    const sourceIndex = template.indexOf('data-column-role="source"');
    const targetIndex = template.indexOf('data-column-role="target"');
    const settingsIndex = template.indexOf('data-column-role="settings"');
    const bulkOptionsSource = script.slice(
        script.indexOf('function buildBulkOptions(source)'),
        script.indexOf('function buildEditConfigurationAction(')
    );

    assert.equal((template.match(/<section\b[^>]*data-column-role=/g) || []).length, 2);
    assert.ok(sourceIndex >= 0);
    assert.ok(targetIndex > sourceIndex);
    assert.ok(settingsIndex > targetIndex);
    assert.ok(template.indexOf('data-role="ergo-list"', sourceIndex) < targetIndex);
    assert.doesNotMatch(template, /data-role="refresh-categories"/);
    assert.equal((script.match(/role: 'refresh-categories'/g) || []).length, 1);
    assert.match(template, /getChildHtml\('actions'\)/);
    assert.match(consumerLayout, /CategorySynchronizationActions/);
    const categoryActions = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'view/adminhtml/templates/synchronization/actions.phtml'), 'utf8'
    );
    assert.match(categoryActions, /data-role="sync-category-trees"/);
    assert.match(categoryActions, /data-synchronization-scope="all"/);
    assert.match(categoryActions, /\$block->getGroups\(\)/);
    assert.match(consumerScript, /data-synchronization-scope/);
    assert.doesNotMatch(script, /buildSyncAction|data-can-sync-category-trees/);
    assert.doesNotMatch(script, /buildResetCursorAction/);
    assert.doesNotMatch(bulkOptionsSource, /buildSyncAction|sync-category-trees/);
    assert.match(script, /source === 'ergo' \? buildRefreshAction\(\) : null/);
    assert.ok(template.indexOf('data-role="magento-list"', targetIndex) > targetIndex);
    assert.ok(template.indexOf('data-role="category-tree-configuration-list"', settingsIndex) > settingsIndex);
    assert.match(template, /data-role="category-tree-configuration"/);
    assert.match(template, /draggable="true"/);
    assert.doesNotMatch(template, /data-role="move-category-tree-(?:up|down)"/);
    assert.doesNotMatch(template, /vec-configuration-order-actions/);
    assert.match(template, /data-role="configuration-options-slot"/);
    assert.match(script, /role: 'open-edit-mapping'/);
    assert.match(template, /\$configurationCardClass = 'vec-configuration-card'[\s\S]*?' is-disabled'/);
    assert.match(template, /class="vec-configuration-disabled-info"/);
    assert.match(template, /class="vec-mapping-hint vec-configuration-disabled-tooltip"/);
    assert.match(template, /Categories from this tree will not be synchronized with Magento/);
    assert.match(template, /if \(\$hasSelection\)/);
    assert.doesNotMatch(template, /name="sync_after_import"/);
    assert.doesNotMatch(template, /name="remove_missing"/);
    assert.doesNotMatch(template, /category-tree-sync-metadata|settings-stream-cursor|settings-last-synced-at/);
    assert.doesNotMatch(template, /vec-settings-sync-action/);
    assert.match(
        template.slice(template.indexOf('class="veui-toolbar'), template.indexOf('class="veui-message')),
        /getChildHtml\('actions'\)/
    );
    assert.match(synchronizationActions, /data-role="reset-sync-cursor"/);
    assert.match(synchronizationActions, /data-role="reset-sync-cursor-and-sync"/);
    assert.doesNotMatch(template, /Synchronizacja z Ergonode/);
    assert.doesNotMatch(template, /vec-settings-sync-|Globalna pozycja ostatnio pobranej zmiany/);
    assert.doesNotMatch(template, /Czas zapisany po pobraniu ostatniej zmiany\./);
    assert.doesNotMatch(template, /data-role="mapping-sync-metadata"/);
    assert.doesNotMatch(template, /class="vec-settings-form"/);
    assert.match(template, /class="veui-workspace veui-workspace-viewbar vec-admin"/);
    assert.match(template, /class="veui-toolbar veui-viewbar vec-toolbar"/);
    assert.match(template, /class="veui-layout vec-layout"/);
    assert.equal((template.match(/class="veui-panel vec-panel/g) || []).length, 3);
    assert.equal((template.match(/class="veui-panel-head veui-panel-head-with-tools vec-panel-head"/g) || []).length, 2);
    assert.match(template, /class="veui-panel-head-tools vec-source-tools"/);
    assert.match(template, /class="veui-panel-head-tools vec-target-tools"/);
    assert.equal((template.match(/class="veui-search veui-search-expandable"/g) || []).length, 2);
    assert.doesNotMatch(template, /veui-count|ergo-count/);
    assert.doesNotMatch(script, /ergo-count/);
    assert.match(template, /veui-brand-ergonode/);
    assert.match(template, /veui-brand-magento/);
    assert.equal(template.includes('data-role="workspace-tree"'), false);
    assert.equal(template.includes('data-role="workspace-search"'), false);
});

test('category save and Magento options share the primary split button after search', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );
    const toolbarStart = template.indexOf('class="veui-toolbar veui-viewbar vec-toolbar"');
    const toolbarEnd = template.indexOf('data-role="message"', toolbarStart);
    const toolbar = template.slice(toolbarStart, toolbarEnd);
    const targetToolsStart = template.indexOf('class="veui-panel-head-tools vec-target-tools"');
    const targetToolsEnd = template.indexOf('data-role="magento-list"', targetToolsStart);
    const targetTools = template.slice(targetToolsStart, targetToolsEnd);
    const searchIndex = targetTools.indexOf('data-role="magento-search"');
    const saveIndex = targetTools.indexOf('data-role="save-categories"');

    assert.doesNotMatch(toolbar, /data-role="save-categories"/);
    assert.ok(searchIndex >= 0 && searchIndex < saveIndex);
    assert.match(
        targetTools,
        /class="veui-button veui-button-primary veui-button-toolbar\s+veui-split-button-main vec-button"/
    );
    assert.match(targetTools, /class="veui-split-button veui-split-button-align-end veui-split-button-primary"/);
    assert.match(script, /\[data-role="category-mapping-actions"\]'\)\.append\(buildBulkOptions\('magento'\)\)/);
    assert.match(story, /actions\.append\(save\)/);
    assert.match(story, /export const ZapisZMenuOpcji/);
});

test('category tree action buttons use the shared Ergonode toolbar component', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const categoryStyles = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const coreStyles = fs.readFileSync(
        path.resolve(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/ergonode-workspace.css'),
        'utf8'
    );
    const coreActionStyles = fs.readFileSync(
        path.resolve(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/ergonode-actions.css'),
        'utf8'
    );
    const synchronizationActions = fs.readFileSync(
        path.resolve(
            projectRoot,
            'vendor/ergonode/module-core-admin-ui/view/adminhtml/templates/synchronization/actions.phtml'
        ),
        'utf8'
    );
    const layout = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/layout/ergonode_category_tree_mapping_edit.xml'),
        'utf8'
    );

    assert.match(coreStyles, /\.veui-button\s*\{[\s\S]*?font-weight: 800/);
    assert.match(
        coreStyles,
        /\.veui-toolbar \.veui-button,\s*\.veui-button-toolbar\s*\{[\s\S]*?border-radius: 8\.5px[\s\S]*?font-size: 11\.05px[\s\S]*?height: 33\.15px[\s\S]*?padding: 0 11\.9px/
    );
    assert.equal((template.match(/class="veui-button/g) || []).length, 6);
    assert.equal((template.match(/veui-button-primary/g) || []).length, 3);
    assert.doesNotMatch(categoryStyles, /\.vec-button(?:-primary|-secondary)?\s*\{/);
    assert.doesNotMatch(categoryStyles, /\.vec-settings-sync-/);
    assert.ok(
        layout.indexOf('Ergonode_CoreAdminUi::css/ergonode-workspace.css') <
        layout.indexOf('Ergonode_CategoryAdminUi::css/category-tree-mapping.css')
    );
    assert.ok(
        layout.indexOf('Ergonode_CoreAdminUi::css/ergonode-workspace.css') <
        layout.indexOf('Ergonode_CoreAdminUi::css/ergonode-actions.css')
    );
    assert.ok(
        layout.indexOf('Ergonode_CoreAdminUi::css/ergonode-actions.css') <
        layout.indexOf('Ergonode_CategoryAdminUi::css/category-tree-mapping.css')
    );
    assert.doesNotMatch(template, /data-can-sync-category-trees/);
    assert.match(consumerLayout, /CategorySynchronizationActions/);
    assert.match(synchronizationActions, /data-synchronization-action="sync"[\s\S]*?veui-sync-ergonode-icon/);
    assert.match(synchronizationActions, /data-role="reset-sync-cursor"[\s\S]*?veui-reset-cursor-icon/);
    assert.match(coreActionStyles, /\.veui-sync-ergonode-icon\s*\{[\s\S]*?mask: url\('\.\.\/images\/sync-ergonode\.svg'\) center \/ 14px 14px no-repeat/);
    assert.match(coreActionStyles, /\.veui-reset-cursor-icon\s*\{[\s\S]*?mask: url\('\.\.\/images\/reset-cursor\.svg'\) center \/ 14px 14px no-repeat/);
    assert.match(template, /getViewFileUrl\('Ergonode_CoreAdminUi::images\/refresh-ergonode\.svg'\)/);
    assert.match(template, /<img class="vec-icon vec-icon-refresh"[\s\S]*?width="14"[\s\S]*?height="14"/);
    assert.match(categoryStyles, /\.vec-icon-refresh\s*\{[\s\S]*?height: 14px;[\s\S]*?width: 14px;/);
    assert.match(coreStyles, /\.veui-panel-head-tools\s*\{[\s\S]*?display: flex;[\s\S]*?gap: 8px;/);
    assert.match(coreStyles, /\.veui-panel-head-tools > \.veui-search\s*\{[\s\S]*?flex: 1 1 auto;/);
    assert.doesNotMatch(template, /vec-source-refresh|data-role="refresh-categories"/);
    assert.match(script, /function buildRefreshAction\(\)[\s\S]*?role: 'refresh-categories',[\s\S]*?iconClass: 'vec-icon vec-icon-refresh',[\s\S]*?label: \$t\('Refresh'\)/);
    assert.match(template, /data-refresh-icon-url="<\?= \$escaper->escapeUrl\(\$refreshIconUrl\) \?>"/);
    assert.match(script, /image\.src = String\(\$root\.attr\('data-refresh-icon-url'\) \|\| ''\)/);
    assert.match(script, /refreshCategories\(\$\(this\)\)/);
    assert.match(consumerScript, /synchronizeCategoryTrees\(\$\(this\), false\)/);
    assert.match(consumerScript, /synchronizeCategoryTrees\(\$\(this\), true\)/);
    assert.match(consumerScript, /resetCategoryTreeSyncCursor\(\$\(this\)\)/);
    assert.doesNotMatch(categoryStyles, /\.vec-icon-refresh:before/);
    assert.match(template, /getViewFileUrl\('Ergonode_CoreAdminUi::images\/save\.svg'\)/);
    assert.match(template, /<img class="vec-icon vec-icon-save"[\s\S]*?width="14"[\s\S]*?height="14"/);
    assert.match(categoryStyles, /\.vec-icon-save\s*\{[\s\S]*?height: 14px;[\s\S]*?width: 14px;/);
    assert.doesNotMatch(categoryStyles, /\.vec-icon-save:(?:before|after)/);
});

test('legacy mapping workflow is removed from templates, JavaScript, styles and layout', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );

    for (const source of [template, css, script]) {
        assert.doesNotMatch(source, /vec-mapping-flow|vec-workflow-bar|vec-flow-|mapping-context|tree-label|root-label/);
    }
    assert.equal(fs.existsSync(path.join(moduleRoot, 'Block/Adminhtml/CategoryTree/Edit/Workflow.php')), false);
    assert.equal(
        fs.existsSync(path.join(moduleRoot, 'view/adminhtml/templates/category-tree/edit/workflow.phtml')),
        false
    );
    assert.equal(
        fs.existsSync(path.join(moduleRoot, 'view/adminhtml/layout/ergonode_category_tree_edit.xml')),
        false
    );
    assert.equal(
        fs.existsSync(path.join(moduleRoot, 'view/adminhtml/ui_component/ergonode_category_tree_form.xml')),
        false
    );
});

test('category mapping uses the same bounded workspace geometry as other mapping pages', () => {
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const coreStyles = fs.readFileSync(
        path.resolve(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/ergonode-workspace.css'),
        'utf8'
    );

    assert.match(coreStyles, /body\.ergonode-category_tree_mapping-edit/);
    assert.match(coreStyles, /\.veui-toolbar\s*\{[\s\S]*?max-width:\s*1800px;/);
    assert.match(coreStyles, /\.veui-layout\s*\{[\s\S]*?height:\s*calc\(100% - 44px\);[\s\S]*?max-width:\s*1800px;/);
    assert.match(coreStyles, /\.veui-workspace-viewbar \.veui-layout\s*\{[\s\S]*?height:\s*calc\(100% - 78px\);/);
    assert.match(coreStyles, /\.veui-panel\s*\{[\s\S]*?border-radius:\s*12px;/);
    assert.match(css, /\.vec-side-tree\s*\{[\s\S]*?flex:\s*1 1 auto;[\s\S]*?min-height:\s*0;/);
    assert.match(css, /\.vec-settings-body\s*\{[\s\S]*?align-content:\s*start;/);
    assert.doesNotMatch(css, /\.vec-settings-form\s*\{/);
    assert.doesNotMatch(css, /--vec-content-width|--vec-tree-height|^\.vec-layout\s*\{|^\.vec-panel\s*\{/m);
    assert.match(
        coreStyles.replace(/\s+/g, ' '),
        /grid-template-columns: minmax\(280px, 1\.06fr\) minmax\(500px, 1\.46fr\) minmax\( 280px, 1\.06fr \);/
    );
    assert.match(coreStyles, /\.veui-panel-head\s*\{[\s\S]*?height:\s*58px;[\s\S]*?min-height:\s*58px;/);
    assert.doesNotMatch(css, /\.vec-admin > \.vec-layout\s*\{/);
    assert.doesNotMatch(css, /\.vec-admin \.vec-panel-head\s*\{/);
    assert.match(css, /\.vec-side-tree \.vec-node-card\s*\{[\s\S]*?border:\s*0;/);
});

test('disabled Category Tree configurations use a neutral state and an accessible explanation', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const ordering = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-configuration-order.js'),
        'utf8'
    );
    const settingsHead = template.slice(
        template.indexOf('class="veui-panel-head vec-panel-head vec-settings-head"'),
        template.indexOf('<?php if ($categoryTrees !== []): ?>')
    );

    assert.doesNotMatch(settingsHead, /veui-panel-actions|open-new-mapping/);
    assert.match(css, /\.vec-configuration-card\.is-disabled,[\s\S]*?background:\s*var\(--veui-line\);[\s\S]*?box-shadow:\s*none;/);
    assert.match(css, /\.vec-configuration-disabled-info:focus-visible\s*\{[\s\S]*?outline:/);
    assert.match(css, /\.vec-configuration-disabled-info:hover \.vec-configuration-disabled-tooltip,[\s\S]*?visibility:\s*visible;/);
    assert.doesNotMatch(css, /\.vec-configuration-order-actions\b/);
    assert.doesNotMatch(ordering, /move-category-tree-(?:up|down)/);
});

test('Category Tree mapping cards share the Operations card treatment', () => {
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );

    assert.match(css, /\.vec-settings-body\s*\{[\s\S]*?gap:\s*8px;[\s\S]*?padding:\s*10px 12px;/);
    assert.match(css, /\.vec-configuration-list\s*\{[\s\S]*?gap:\s*8px;/);
    assert.match(
        css,
        /\.vec-configuration-card\s*\{[\s\S]*?border-radius:\s*8px;[\s\S]*?gap:\s*6px;[\s\S]*?line-height:\s*1\.45;[\s\S]*?padding:\s*11px 12px;/
    );
    assert.match(
        css,
        /\.vec-configuration-card:hover\s*\{[\s\S]*?background:\s*var\(--veui-surface-hover\);[\s\S]*?border-color:\s*var\(--veui-border-strong\);/
    );
    assert.match(
        css,
        /\.vec-configuration-card\.is-selected\s*\{[^}]*background:\s*var\(--veui-blue-softer\);[^}]*border-color:\s*var\(--veui-blue-border\);/
    );
    assert.match(css, /\.vec-configuration-card:focus-within\s*\{[\s\S]*?outline:\s*2px solid var\(--veui-blue\);/);
});

test('both category trees share card geometry, typography and hover treatment', () => {
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );

    assert.match(css, /\.vec-side-tree \.vec-node-card\s*\{[\s\S]*?border:\s*0;[\s\S]*?min-height:\s*38px;[\s\S]*?padding:\s*4px 6px;/);
    assert.match(css, /\.vec-side-tree \.vec-card-copy strong\s*\{[\s\S]*?font-size:\s*11px;/);
    assert.match(css, /\.vec-side-tree \.vec-card-copy span\s*\{[\s\S]*?font-size:\s*9\.5px;/);
    assert.match(css, /\.vec-card-copy \.vec-card-label\s*\{[\s\S]*?font-size:\s*11px;[\s\S]*?font-weight:\s*400;/);
    assert.match(css, /\.vec-magento-card\s*\{[\s\S]*?gap:\s*7px;[\s\S]*?min-height:\s*38px;/);
    assert.match(
        css,
        /\.vec-magento-card\.has-bulk-selection\s*\{[\s\S]*?grid-template-columns:\s*18px minmax\(140px, 1fr\) auto 28px;/
    );
    assert.match(css, /\.vec-magento-mapping\s*\{[\s\S]*?min-height:\s*28px;[\s\S]*?padding:\s*0 5px;/);
    assert.match(css, /\.vec-tree-children,\s*\.vec-node-children\s*\{[\s\S]*?display:\s*grid;[\s\S]*?gap:\s*0;/);
    assert.match(css, /\.vec-tree-node\s*\{[\s\S]*?margin:\s*0 0 1px;/);
    assert.match(css, /\.vec-card:hover,[\s\S]*?\.vec-node-card:hover,[\s\S]*?background:\s*var\(--veui-surface-hover\);[\s\S]*?box-shadow:\s*none;/);
    assert.doesNotMatch(css, /\.vec-(?:source|target)-panel \.vec-node-card\s*\{/);
});

test('Magento category names use a regular label element while Ergonode names keep their emphasis', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );
    const magentoCard = script.match(
        /function buildMagentoCard\(category\)[\s\S]*?function getMagentoMappingPresentation/
    )[0];

    assert.match(magentoCard, /\$\('<span\/>', \{class: 'vec-card-label'\}\)\.text\(category\.label\)/);
    assert.doesNotMatch(magentoCard, /\$\('<strong\/>'\)\.text\(category\.label\)/);
    assert.match(story, /document\.createElement\(role === 'target' \? 'span' : 'strong'\)/);
    assert.match(story, /className: role === 'target' \? 'vec-card-label' : ''/);
});

test('connected categories use the shared connection icon with the existing copy in its hint', () => {
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );
    const connectedIcon = fs.readFileSync(
        path.join(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/images/auto-match.svg'),
        'utf8'
    );

    assert.match(css, /\.vec-mapping-indicator\s*\{[\s\S]*?color:\s*var\(--veui-success\);[\s\S]*?cursor:\s*pointer;[\s\S]*?height:\s*28px;/);
    const sharedCss = fs.readFileSync(path.join(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/ergonode-workspace.css'), 'utf8');
    assert.match(sharedCss, /\.veui-connected-icon\s*\{[\s\S]*?mask: url\('\.\.\/images\/auto-match\.svg'\) center \/ contain no-repeat;/);
    assert.match(css, /\.vec-magento-mapping\.is-mapped\s*\{[\s\S]*?background:\s*transparent;[\s\S]*?color:\s*var\(--veui-success\);/);
    assert.match(css, /\.vec-mapping-hint\s*\{[\s\S]*?opacity:\s*0;[\s\S]*?visibility:\s*hidden;/);
    assert.match(css, /\.vec-mapping-indicator:hover,[\s\S]*?\.vec-mapping-indicator:focus\s*\{[\s\S]*?z-index:\s*300;/);
    assert.match(css, /\.vec-mapping-indicator:hover \.vec-mapping-hint,[\s\S]*?\.vec-mapping-indicator:focus \.vec-mapping-hint\s*\{[\s\S]*?opacity:\s*1;[\s\S]*?visibility:\s*visible;/);
    assert.match(script, /function buildMappingIndicator\(className, label, code, statusLabel, statusMessage\)[\s\S]*?\[label, code, statusLabel, statusMessage\][\s\S]*?'data-mapping-label':\s*label,[\s\S]*?'data-mapping-code':\s*code,[\s\S]*?'aria-label':\s*hint,[\s\S]*?'aria-describedby':\s*hintId/);
    assert.match(script, /class:\s*'vec-mapping-hint',[\s\S]*?role:\s*'tooltip'[\s\S]*?\$\('<strong\/>'\)\.text\(label\)[\s\S]*?\$\('<span\/>'\)\.text\(code\)/);
    assert.match(script, /buildMappingIndicator\('vec-source-mapping', magento\.label, '#' \+ magento\.id\)/);
    assert.match(script, /buildMappingIndicator\([\s\S]*?'vec-magento-mapping is-mapped',[\s\S]*?mappedCategory\.label,[\s\S]*?mappedCategory\.code/);
    assert.match(story, /function createMappingIndicator\(className, label, code, statusLabel = '', statusMessage = ''\)[\s\S]*?tooltip\.className = 'vec-mapping-hint';[\s\S]*?className: 'vec-mapping-status-icon veui-connected-icon'/);
    assert.match(story, /export const WskaznikiZmapowanychKategorii = \{[\s\S]*?sourceIndicator\.focus\(\)[\s\S]*?expect\(sourceHint\)\.toBeVisible\(\)[\s\S]*?targetIndicator\.focus\(\)[\s\S]*?expect\(targetHint\)\.toBeVisible\(\)/);
    assert.doesNotMatch(css + script + story, /cursor:\s*help|title:\s*hint|indicator\.title/);
    assert.doesNotMatch(css + script + story, /vec-map-pill|vec-mapping-copy/);
    assert.match(connectedIcon, /viewBox="0 0 24 24"/);
    assert.doesNotMatch(css, /badge-check\.svg|puzzle\.svg/);
});

test('mapped Magento cards distinguish active, disabled and synchronization error states', () => {
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );

    assert.match(script, /function getMagentoMappingPresentation\(category, mappedCategory\)/);
    assert.match(script, /mappedCategory\.sync_status[\s\S]*?=== 'error'/);
    assert.match(script, /state: 'active'[\s\S]*?\$t\('This element is already mapped\.'\)/);
    assert.match(script, /state: 'disabled'[\s\S]*?\$t\('Mapping disabled'\)/);
    assert.match(script, /state: 'error'[\s\S]*?\$t\('Mapping error'\)/);
    assert.match(script, /'data-mapping-state'\] = mappingPresentation\.state/);
    assert.match(css, /--vec-c-fda4af:\s*#fda4af;/);
    assert.match(css, /--vec-c-fff1f2:\s*#fff1f2;/);
    assert.match(css, /\.vec-side-tree \.vec-magento-card\.is-mapped\s*\{[\s\S]*?background: var\(--veui-surface-hover\);[\s\S]*?border: 0;/);
    assert.match(css, /\.vec-side-tree \.vec-magento-card\.is-mapped:not\(\.has-mapping-error\) \.vec-card-copy\s*\{[\s\S]*?color: var\(--veui-muted\);/);
    assert.match(css, /\.vec-side-tree \.vec-magento-card\.is-mapped\.is-mapping-disabled\s*\{[\s\S]*?background: var\(--veui-surface-muted\);[\s\S]*?border-color: var\(--veui-border-strong\);/);
    assert.match(css, /\.vec-side-tree \.vec-magento-card\.is-mapped\.has-mapping-error\s*\{[\s\S]*?background: var\(--vec-c-fff1f2\);[\s\S]*?border-color: var\(--vec-c-fda4af\);/);
    assert.match(story, /export const StanyMapowanychKategoriiMagento = \{[\s\S]*?data-mapping-state="active"[\s\S]*?data-mapping-state="disabled"[\s\S]*?data-mapping-state="error"/);
});

test('both category trees render accessible child toggles', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );

    assert.match(script, /buildTreeNodeRow\([\s\S]*?'source'/);
    assert.match(script, /buildTreeNodeRow\([\s\S]*?'magento'/);
    assert.match(script, /'data-role': 'toggle-children'/);
    assert.match(script, /'aria-expanded': expanded \? 'true' : 'false'/);
    assert.match(script, /'aria-label': label/);
    assert.match(css, /\.vec-node-children\[hidden\]\s*\{\s*display:\s*none;/);
});

test('both category trees restore and persist their collapsed branches in browser storage', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );

    assert.match(script, /Ergonode_CategoryAdminUi\/js\/category-tree-collapse-storage/);
    assert.match(script, /collapseStorage\.load\(categoryTreeId, browserStorage\)/);
    assert.match(script, /collapseStorage\.save\(categoryTreeId, \{[\s\S]*?source: collapsedSourceNodes,[\s\S]*?magento: collapsedMagentoNodes/);
    assert.match(script, /treeState\.toggle\(collapsedSourceNodes, key\);\s*saveCollapsedNodes\(\);/);
    assert.match(script, /treeState\.toggle\(collapsedMagentoNodes, key\);\s*saveCollapsedNodes\(\);/);
    assert.match(script, /retainCollapsedNodes\(storedState\.source, sourceBranches\)/);
    assert.match(script, /retainCollapsedNodes\(storedState\.magento, magentoBranches\)/);
    assert.match(script, /return window\.localStorage \|\| null;[\s\S]*?catch \(error\) \{\s*return null;/);
});

test('configured Ergonode tree is rendered as a collapsible root mapped to the Magento root', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const ergonodeCard = script.match(
        /function buildErgonodeCard\(category, isMappedParent\)[\s\S]*?function buildErgonodeCategoryOptions/
    )[0];
    const ergonodeRootCard = script.match(
        /function buildErgonodeRootCard\(configuredRoot, isMappedParent\)[\s\S]*?function buildMagentoCard/
    )[0];
    const magentoCard = script.match(
        /function buildMagentoCard\(category\)[\s\S]*?function getConfiguredRootMapping/
    )[0];
    const magentoOptions = script.match(
        /function buildMagentoCategoryOptions\(category, mappedCategory, isConfiguredRoot\)[\s\S]*?function buildCategoryOptions/
    )[0];
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );

    assert.match(script, /function getConfiguredRootMapping\(\)/);
    assert.match(script, /key: '__configured_root__:' \+ code/);
    assert.match(script, /buildErgonodeRootCard\(configuredRoot, hideMappedErgonode\)/);
    assert.match(ergonodeRootCard, /vec-configured-root-card is-mapped has-bulk-selection/);
    assert.match(
        ergonodeRootCard,
        /buildBulkCategorySelection\('ergo', configuredRoot\.key, configuredRoot\.label\)/
    );
    assert.doesNotMatch(ergonodeRootCard, /vec-tree-root-mark/);
    assert.match(script, /configuredRoot\.magentoId === category\.id/);
    assert.match(script, /category\.active && !isConfiguredRoot && !mappedCategory/);
    assert.doesNotMatch(ergonodeCard, /vec-unmap/);
    assert.doesNotMatch(magentoCard, /'data-role': 'unmap-category'|buildActiveToggle\('magento'/);
    assert.match(magentoCard, /\$mapping,[\s\S]*?buildMagentoCategoryOptions\(category, mappedCategory, isConfiguredRoot\)/);
    assert.match(magentoOptions, /if \(mappedCategory && !isConfiguredRoot\)/);
    assert.match(magentoOptions, /className: 'vec-unmap'/);
    assert.doesNotMatch(magentoOptions, /role: 'delete-snapshot-category'/);
    assert.match(magentoOptions, /buildActiveToggle\('magento', category\.id, category\.active\)/);
    assert.doesNotMatch(css, /\.vec-source-panel \.vec-unmap/);
    assert.doesNotMatch(css, /\.vec-target-panel \.vec-unmap/);
    assert.doesNotMatch(css, /\.vec-tree-root-mark/);
    assert.match(css, /\.vec-configured-root-card\s*\{/);
    assert.match(css, /\.vec-magento-card\.is-configured-root \.vec-magento-mapping/);
    assert.match(css, /\.vec-magento-card\.is-configured-root\s*\{[\s\S]*?font-weight: 700;/);
});

test('unmapped Ergonode categories do not expose manual Magento creation in the left tree', () => {
    const block = fs.readFileSync(
        path.join(moduleRoot, 'Block/Adminhtml/CategoryTreeMapping/Index.php'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const coreStyles = fs.readFileSync(
        path.resolve(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/css/ergonode-workspace.css'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );

    assert.doesNotMatch(block, /create_magento|createMagento/);
    assert.doesNotMatch(script, /role: 'create-magento-category'/);
    assert.doesNotMatch(script, /function createMagentoCategory\(code, \$button\)/);
    assert.doesNotMatch(script, /config\.urls\.create_magento/);
    assert.doesNotMatch(script, /Create in Magento|Create category in Magento/);
    assert.doesNotMatch(css, /vec-create-magento/);
    assert.doesNotMatch(story, /vec-create-magento|Utwórz kategorię w Magento/);
    assert.match(story, /export const BrakTworzeniaKategoriiMagentoWLewymDrzewie/);
    assert.match(script, /function buildErgonodeCategoryOptions\(category\)/);
    assert.match(script, /Ergonode_CoreAdminUi\/js\/entity-options/);
    assert.match(script, /entityOptions\.create\(/);
    assert.match(coreStyles, /\.veui-entity-options-menu\s*\{/);
    assert.match(coreStyles, /\.veui-entity-options-menu > \.veui-entity-options-action/);
    assert.doesNotMatch(css, /entity-options/);
});

test('only Ergonode categories can be removed from the local snapshot through the options menu', () => {
    const block = fs.readFileSync(
        path.join(moduleRoot, 'Block/Adminhtml/CategoryTreeMapping/Index.php'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const ergonodeOptions = script.match(
        /function buildErgonodeCategoryOptions\(category\)[\s\S]*?function buildMagentoCategoryOptions/
    )[0];
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );

    assert.match(block, /'delete_snapshot' => .*category_tree_mapping\/deleteSnapshot/);
    assert.doesNotMatch(block, /delete_magento|deleteMagento/);
    assert.match(script, /role: 'delete-snapshot-category'/);
    assert.match(
        ergonodeOptions,
        /role: 'delete-snapshot-category'[\s\S]*?'data-code': category\.code[\s\S]*?'data-label': category\.label/
    );
    assert.doesNotMatch(ergonodeOptions, /if \(magento\)/);
    const magentoOptions = script.match(
        /function buildMagentoCategoryOptions\(category, mappedCategory, isConfiguredRoot\)[\s\S]*?function buildCategoryOptions/
    )[0];

    assert.doesNotMatch(magentoOptions, /role: 'delete-snapshot-category'/);
    assert.match(magentoOptions, /role: 'unmap-category'/);
    assert.match(script, /function deleteSnapshotCategory\(code, \$button\)/);
    assert.match(script, /The Magento category will not be deleted/);
    assert.match(script, /post\(config\.urls\.delete_snapshot/);
    assert.doesNotMatch(script, /config\.urls\.delete_magento/);
    assert.match(css, /\.vec-delete-snapshot-category\s*\{/);
    assert.match(css, /\.vec-delete-snapshot-icon\s*\{/);
});

test('mapping starts collapsed and exposes blocking plus automatic matching controls', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );
    const categoryUnmapIcon = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/images/category-unmap.svg'),
        'utf8'
    );

    assert.doesNotMatch(template, /data-role="auto-map-categories"/);
    assert.match(template, /data-auto-match-icon-url=/);
    assert.match(script, /role: 'auto-map-categories'/);
    assert.match(script, /label: \$t\('Auto Connect'\)/);
    assert.match(script, /source === 'magento' \? \(config.mapping_actions/);
    assert.doesNotMatch(script, /source === 'ergo' \? buildAutoMapAction\(\) : null/);
    assert.doesNotMatch(template, /__\('Auto-mapuj'\)/);
    assert.doesNotMatch(template, /data-role="sync-categories"|>Synchronizuj</);
    assert.match(template, /getViewFileUrl\('Ergonode_CoreAdminUi::images\/auto-match\.svg'\)/);
    assert.match(script, /image\.className = 'vec-auto-map-icon'/);
    assert.match(script, /data-auto-match-icon-url/);
    assert.doesNotMatch(template, /data-role="visibility-toggle"/);
    assert.match(script, /role: 'visibility-toggle'/);
    assert.match(script, /role: 'mapped-visibility-toggle'/);
    assert.match(script, /label: \$t\('Excluded'\)/);
    assert.match(script, /label: \$t\('Connected'\)/);
    assert.match(script, /getErgonodeEmptyMessage\(query, configuredRoot\)/);
    assert.match(script, /\$t\('No categories\. All categories are already mapped\.'\)/);
    assert.match(script, /function buildErgonodeEmptyState\(query, configuredRoot\)/);
    assert.match(script, /class: 'veui-button veui-visibility-control vec-empty-mapped-toggle'/);
    assert.match(script, /'data-role': 'mapped-visibility-toggle'/);
    assert.match(css, /\.vec-empty-row\s*\{[\s\S]*?gap:\s*8px;/);
    assert.match(script, /buildErgonodeCard\(category, isMappedParent\)/);
    assert.match(script, /buildErgonodeRootCard\(configuredRoot, hideMappedErgonode\)/);
    assert.match(script, /isMappedParent \? ' is-mapped-parent' : ''/);
    assert.match(css, /\.vec-node-card\.is-blocked,\s*\.vec-node-card\.is-mapped-parent\s*\{/);
    assert.match(script, /'data-show-hint': \$t\('Pokaż kategorie pominięte w mapowaniu'\)/);
    assert.match(script, /'data-hide-hint': \$t\('Ukryj kategorie pominięte w mapowaniu'\)/);
    assert.doesNotMatch(css, /--vec-eye-|\.vec-eye-icon|\.vec-blocked-filter/);
    assert.match(css, /\.vec-auto-map-icon\s*\{[\s\S]*?height: 14px;[\s\S]*?width: 14px;/);
    assert.doesNotMatch(template, /vec-auto-map-symbol/);
    assert.match(script, /initializeCollapsedNodes\(\);/);
    assert.match(script, /Ergonode_CoreAdminUi\/js\/visibility-toggle/);
    assert.match(script, /showBlocked\[source\] = visibilityToggle\.toggle\(this\)/);
    assert.match(script, /sourceDefaults\[category\.code\] = true/);
    assert.match(script, /magentoDefaults\[String\(category\.id\)\] = true/);
    assert.match(script, /data-role': 'category-active-toggle'/);
    assert.match(script, /active \? \$t\('Exclude'\) : \$t\('Include'\)/);
    assert.doesNotMatch(script, /Zablokuj kategorię w mapowaniu|Włącz kategorię do mapowania/);
    assert.doesNotMatch(script, /vec-drop-target-icon/);
    assert.match(script, /Upuść kategorię Ergonode, aby ją zmapować/);
    assert.doesNotMatch(css, /vec-drop-target-icon|category-drop-target\.svg/);
    assert.match(css, /\.vec-ergo-card\[draggable='true'\]\s*\{\s*cursor:\s*grab;/);
    assert.match(script, /nativeDrag\.enableSource\(\$card\)/);
    assert.match(story, /card\.draggable = role === 'source';/);
    assert.doesNotMatch(css + script + story, /vec-drag-handle|ergonode-category-drag\.svg/);
    assert.match(css, /\.vec-unmap-icon\s*\{[\s\S]*?mask:\s*url\('\.\.\/images\/category-unmap\.svg'\) center \/ 16px 16px no-repeat/);
    assert.doesNotMatch(css, /\.vec-unmap-icon:(?:before|after)/);
    assert.match(categoryUnmapIcon, /viewBox="0 0 24 24"/);
    assert.match(categoryUnmapIcon, /<path d="M10\.5,17\.696/);
    assert.match(script, /role: 'unmap-category',[\s\S]*?label: \$t\('Disconnect'\)/);
    assert.doesNotMatch(script, /Usuń mapowanie Magento/);
    assert.doesNotMatch(template, /veui-panel-subtitle/);
    assert.match(template, /__\('Category Tree'\)/);
    assert.match(script, /visibility: collectVisibility\(\)/);
    assert.doesNotMatch(script, /autoMatcher|category-auto-matcher|generated_code.*===/);
    assert.match(script, /draft_mappings:/);
    assert.match(script, /persistedMappings\[category\.code\] !== category\.magento_category_id/);
    assert.match(script, /category\.mapping_source === 'database'/);
    assert.match(script, /draft_visibility: collectVisibility\(\)/);
    assert.match(script, /payload: JSON\.stringify\(mappingDraft\(\)\)/);
    assert.match(script, /categories = normalizeCategories\(response\.categories \|\| \[\]\)/);
    assert.match(script, /magentoCategories = normalizeMagentoCategories\(response\.magento_categories \|\| \[\]\)/);
    assert.match(script, /\.always\(function \(\) \{[\s\S]*?setBusy\(\$button, false\)/);
});

test('both category trees expose selection menus with independent visibility toggles', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );
    const magentoCard = script.match(
        /function buildMagentoCard\(category\)[\s\S]*?function getMagentoMappingPresentation/
    )[0];
    const branchSelection = script.match(
        /function setBulkBranchSelected\(source, identifier, checked\)[\s\S]*?function clearBulkSelection/
    )[0];
    const selectionPruning = script.match(
        /function pruneBulkSelection\(\)[\s\S]*?function updateBulkControls/
    )[0];

    assert.match(template, /class="veui-panel-head-tools vec-source-tools"/);
    assert.match(template, /class="veui-panel-head-tools vec-target-tools"/);
    assert.match(script, /function initializeBulkOptions\(\)/);
    assert.match(script, /buildBulkOptions\('ergo'\)/);
    assert.match(script, /buildBulkOptions\('magento'\)/);
    assert.match(script, /data-role': 'bulk-category-select'/);
    assert.doesNotMatch(script, /role: 'select-visible-categories'/);
    assert.doesNotMatch(script, /role: 'clear-category-selection'/);
    assert.doesNotMatch(script, /role: 'include-selected-categories'/);
    assert.doesNotMatch(script, /role: 'exclude-selected-categories'/);
    assert.match(script, /role: 'visibility-toggle'/);
    assert.match(script, /role: 'unmap-selected-categories'/);
    assert.match(script, /label: \$t\('Excluded'\)/);
    assert.match(script, /label: \$t\('Disconnect'\)/);
    assert.doesNotMatch(script, /Include selected in mapping|Exclude selected from mapping|Disconnect selected mappings/);
    assert.match(script, /function unmapSelectedCategories\(source\)[\s\S]*?mappingIndex\.assign\(mappedCategory\.code, null\)/);
    assert.match(script, /function setBulkBranchSelected\(source, identifier, checked\)/);
    assert.match(script, /getBulkSelection: function \(source\)/);
    assert.match(script, /clearBulkSelection: clearBulkSelection/);
    assert.match(script, /ergonode:category-mapping:selection-changed/);
    assert.match(script, /subtreeMapping\.branchIdentifiers\([\s\S]*?'code',[\s\S]*?'source_parent_code'/);
    assert.match(script, /subtreeMapping\.branchIdentifiers\([\s\S]*?'id',[\s\S]*?'parent_id'/);
    assert.doesNotMatch(branchSelection, /findByMagentoId/);
    assert.match(branchSelection, /configuredRoot && identifier === configuredRoot\.key/);
    assert.match(branchSelection, /\[configuredRoot\.key\]\.concat\(categories\.map/);
    assert.match(magentoCard, /vec-magento-card has-bulk-selection/);
    assert.match(magentoCard, /buildBulkCategorySelection\('magento', category\.id, category\.label\)/);
    assert.doesNotMatch(magentoCard, /isBulkSelectable/);
    assert.match(selectionPruning, /magentoCategories\.forEach\([\s\S]*?magentoIds\[String\(Number\(category\.id\)\)\] = true/);
    assert.match(selectionPruning, /sourceCodes\[configuredRoot\.key\] = true/);
    assert.match(story, /export const ZaznaczanieCalegoDrzewaErgonode/);
    assert.match(
        script,
        /if \(!sourceCategory \|\| \(magentoId && \(!sourceCategory\.active \|\| !targetCategory \|\| !targetCategory\.active\)\)\)/
    );
    assert.doesNotMatch(css, /\.vec-(source|target)-tools\s*\{/);
    assert.match(css, /\.vec-bulk-options > summary\s*\{[\s\S]*?height:\s*34px;/);
    assert.match(css, /\.vec-bulk-category-select input\s*\{[\s\S]*?accent-color:/);
    assert.doesNotMatch(css, /\.vec-bulk-select-icon/);
    assert.doesNotMatch(css, /\.vec-bulk-clear-icon/);
    assert.doesNotMatch(story, /role: 'select-visible-categories'/);
    assert.doesNotMatch(story, /role: 'clear-category-selection'/);
    assert.match(story, /export const NiezaleznePrzelacznikiWykluczonychKategorii/);
    assert.match(story, /export const DomyslnieUkryteZmapowaneKategorieErgonode/);
    assert.match(story, /export const KaskadoweZaznaczaniePoObuStronach/);
    assert.match(story, /export const ZaznaczanieCalegoDrzewaMagento/);
    assert.match(story, /export const MasoweOdlaczanieMapowanErgonode/);
    assert.match(story, /export const MasoweOdlaczanieMapowanMagento/);
    assert.match(story, /export const DostepnoscAkcjiDlaZaznaczenia/);
    assert.match(story, /export const AutoConnectWMenuSrodkowejKolumny/);
    assert.match(script, /Ergonode_CategoryAdminUi\/js\/category-tree-bulk-actions/);
    assert.match(script, /bulkActions\.resolve\(/);
    assert.match(script, /function syncVisibilityControl\(source, button\)/);
    assert.match(script, /function syncMappedVisibilityControl\(button\)/);
    assert.match(script, /category\.active === false/);
    assert.match(script, /button\.disabled = !available/);
    assert.match(script, /showBlocked\.ergo/);
    assert.match(script, /showBlocked\.magento/);
    assert.match(script, /var hideMappedErgonode = true;/);
    assert.match(script, /hideMappedErgonode && Boolean\(category\.magento_category_id\)/);
    assert.match(script, /hideMappedErgonode && hasVisibleRootChildren/);
    assert.match(script, /prop\('disabled', !availability\.disconnect\)/);
});

test('disconnecting a persisted mapping exposes an accessible pending-save tooltip', () => {
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const pendingIcon = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/images/pending-save-info.svg'),
        'utf8'
    );

    assert.match(script, /function buildPendingSaveIndicator\(\)/);
    assert.match(script, /\$t\('Mapowanie oczekuje na zapis'\)/);
    assert.match(script, /class: 'vec-pending-save'/);
    assert.match(script, /'data-role': 'pending-mapping-save'/);
    assert.match(script, /'aria-describedby': tooltipId/);
    assert.match(script, /class: 'vec-pending-save-tooltip'[\s\S]*?role: 'tooltip'/);
    assert.doesNotMatch(script, /title: message/);
    assert.match(script, /function hasPendingMappingRemoval\(category\)/);
    assert.match(script, /function findPendingMappingRemovalByMagentoId\(magentoId\)/);
    assert.match(script, /else if \(hasPendingMappingRemoval\(category\)\)/);
    assert.match(script, /pendingMappingRemoval = !mappedCategory/);
    assert.match(script, /if \(pendingMappingRemoval \|\| hasPendingMappingAddition\(mappedCategory\)\) \{\s+\$mapping = buildPendingSaveIndicator\(\)/);
    assert.doesNotMatch(script, /var \$pendingSave|\$pendingSave,/);
    assert.match(
        script,
        /dirty = false;[\s\S]*?persistedMappings = collectPersistedMappings\(categories\.filter\([\s\S]*?renderAll\(\);/
    );
    assert.match(css, /\.vec-pending-save\s*\{[\s\S]*?color: var\(--veui-amber\);/);
    assert.match(css, /pending-save-info\.svg/);
    assert.match(css, /\.vec-pending-save-tooltip\s*\{[\s\S]*?z-index: 300;/);
    assert.match(css, /\.vec-pending-save:hover \.vec-pending-save-tooltip,[\s\S]*?visibility: visible;/);
    assert.match(pendingIcon, /viewBox="0 0 24 24"/);
    assert.match(pendingIcon, /M7 3\.34A10 10 0 1 1 3\.34 7/);
});

test('excluded Magento categories retain flat cards and expose their disabled explanation', () => {
    const css = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/css/category-tree-mapping.css'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const story = fs.readFileSync(
        path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'),
        'utf8'
    );
    const translations = fs.readFileSync(
        path.join(moduleRoot, 'i18n/pl_PL.csv'),
        'utf8'
    );
    const magentoCard = script.match(
        /function buildMagentoCard\(category\)[\s\S]*?function getMagentoMappingPresentation/
    )[0];

    assert.match(script, /function buildExcludedCategoryInfo\(\)/);
    assert.match(script, /class: 'vec-configuration-disabled-info'/);
    assert.match(script, /class: 'vec-information-icon'/);
    assert.match(script, /class: 'vec-mapping-hint vec-configuration-disabled-tooltip'/);
    assert.match(script, /role: 'img'/);
    assert.match(script, /role: 'tooltip'/);
    assert.match(magentoCard, /\(category\.active \? '' : ' is-disabled'\)/);
    assert.match(magentoCard, /if \(!category\.active\) \{\s+\$mapping = buildExcludedCategoryInfo\(\)/);
    assert.match(
        css,
        /\.vec-side-tree \.vec-magento-card\.is-disabled,[^{}]*\{\s*background: transparent;\s*border: 0;\s*box-shadow: none;\s*opacity: 1;\s*\}/
    );
    assert.match(css, /\.vec-magento-card\.is-disabled \.vec-card-copy strong[\s\S]*?var\(--veui-muted\)/);
    assert.match(story, /function syncMagentoExcludedPresentation\(card, excluded\)/);
    assert.match(story, /getByRole\('img',[\s\S]*?Kategoria jest wykluczona z mapowania/);
    assert.match(translations, /"Category is excluded from mapping","Kategoria jest wykluczona z mapowania"/);
});

test('category mapping protects dirty navigation with the shared Ergonode guard', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const categorySwitch = script.match(
        /function loadCategoryTree\(nextCategoryTreeId, mappingUrl\)[\s\S]*?function applyCategoryTreeConfig/
    )[0];

    assert.match(script, /Ergonode_CoreAdminUi\/js\/unsaved-navigation/);
    assert.match(script, /unsavedNavigation\.bind\(scope, \{/);
    assert.match(script, /isDirty: function \(\) \{\s+return dirty;/);
    assert.match(script, /save: saveBeforeNavigation/);
    assert.match(script, /shouldHandleLink: function \(anchor\)/);
    assert.match(script, /function saveBeforeNavigation\(\)[\s\S]*?response && response\.success/);
    assert.match(categorySwitch, /unsavedChangesGuard\.request\(\{/);
    assert.match(categorySwitch, /navigate: function \(\) \{\s+requestCategoryTree/);
    assert.match(categorySwitch, /onCancel: updateSelectedCategoryTree/);
    assert.match(categorySwitch, /onSaveError: updateSelectedCategoryTree/);
    assert.doesNotMatch(categorySwitch, /window\.confirm/);
});

test('optional modules can add pending categories through a neutral mapping extension API', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );

    assert.match(script, /element\.veaCategoryMappingApi\s*=\s*\{/);
    assert.match(script, /getCategories:/);
    assert.match(script, /getMagentoCategories:/);
    assert.match(script, /getCategoryTreeId:/);
    assert.match(script, /addAndMapCategory:\s*addAndMapCategory/);
    assert.match(script, /removeCategory:\s*removeCategory/);
    assert.match(script, /!mappingCategory\.active \|\| !targetCategory \|\| !targetCategory\.active/);
    assert.match(script, /setCategoryParent:\s*setCategoryParent/);
    assert.match(script, /saveLayout:/);
    assert.match(script, /markCategoryRemotePrepared:\s*markCategoryRemotePrepared/);
    assert.match(script, /markCategoryPublished:\s*markCategoryPublished/);
    assert.match(script, /markDirty:\s*markDirty/);
    assert.match(script, /Ergonode_CoreAdminUi\/js\/workspace-context/);
    assert.match(script, /var showMessage = messageBus\.show/);
    assert.match(script, /notify:\s*showMessage/);
    assert.doesNotMatch(script, /function showMessage\(/);
    assert.match(script, /render:\s*renderAll/);
    assert.match(script, /!excluded\[category\.code\]/);
    assert.doesNotMatch(script, /replaceModels\(response, true, true\)/);
    assert.match(script, /ergonode:category-mapping:ready/);
    assert.match(script, /extension_data:\s*\$\.extend/);
    assert.match(script, /ergonode:category-mapping:saved/);
    assert.doesNotMatch(script, /Ergonode_CategoryPublisherAdminUi/);
});

test('save keeps current tree models and refresh rejects an empty replacement', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );

    assert.match(script, /function hasUsableModels\(response\)/);
    assert.match(script, /response\.categories\.length > 0/);
    assert.match(script, /response\.magento_categories\.length > 0/);
    assert.match(script, /if \(!hasUsableModels\(response\)\)/);
    assert.doesNotMatch(
        script,
        /function saveLayout\(excludedCodes, beforeRender\)[\s\S]*?replaceModels\(response[\s\S]*?function markCategoryRemotePrepared/
    );
});

test('non-draggable category controls do not invoke the jQuery UI draggable widget', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );
    const entityOptions = fs.readFileSync(
        path.resolve(projectRoot, 'vendor/ergonode/module-core-admin-ui/view/adminhtml/web/js/entity-options.js'),
        'utf8'
    );
    const categoryOptions = script.match(
        /function buildErgonodeCategoryOptions\(category\)[\s\S]*?function buildErgonodeRootCard/
    )[0];

    assert.doesNotMatch(script, /\bdraggable:\s*'false'/);
    assert.doesNotMatch(categoryOptions, /\.attr\('draggable', 'false'\)/);
    assert.match(entityOptions, /action\.setAttribute\('draggable', 'false'\)/);
    assert.match(entityOptions, /menu\.setAttribute\('draggable', 'false'\)/);
    assert.match(entityOptions, /summary\.setAttribute\('draggable', 'false'\)/);
});

test('missing Category Tree uses the settings empty state without an obsolete global message link', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );

    assert.doesNotMatch(template, /data-role="configuration-link"/);
    assert.match(template, /Brak skonfigurowanych drzew kategorii\./);
    assert.match(template, /Nowe mapowanie/);
    assert.doesNotMatch(script, /configuration_required|\$configurationLink|showConfigurationLink/);
});

test('mapping view keeps the configuration list in the third shared panel without a separate context banner', () => {
    const template = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'),
        'utf8'
    );
    const block = fs.readFileSync(
        path.join(moduleRoot, 'Block/Adminhtml/CategoryTreeMapping/Index.php'),
        'utf8'
    );

    assert.equal(
        template.includes(
            '/** @var \\Ergonode\\CategoryAdminUi\\Block\\Adminhtml\\CategoryTreeMapping\\Index $block */'
        ),
        true
    );
    assert.doesNotMatch(template, /mapping-context|tree-label|root-label|Ustawienia drzewa/);
    assert.match(template, /data-column-role="settings"/);
    assert.match(template, /Konfiguracje drzew/);
    assert.match(template, /class="veui-panel vec-panel vec-settings-panel"/);
    assert.match(template, /\$canEditCategoryTree = \$block->canEditCategoryTree\(\)/);
    assert.match(template, /if \(\$canEditCategoryTree\)/);
    assert.doesNotMatch(block, /getCategoryTreeUrl/);
    assert.doesNotMatch(block, /getCategoryTreeEditUrl/);
    assert.match(block, /Ergonode_CategoryConsumer::category_tree_save/);
    assert.match(block, /Ergonode_CategoryConsumer::category_tree_manage/);
});

test('assigning an Ergonode category keeps Magento targets unique and supports unmapping', () => {
    const mappingState = loadMappingState();
    const categories = [
        {code: 'chairs', magento_category_id: 12},
        {code: 'tables', magento_category_id: null}
    ];

    assert.equal(mappingState.assign(categories, 'tables', 12), true);
    assert.equal(categories[0].magento_category_id, null);
    assert.equal(categories[1].magento_category_id, 12);
    assert.equal(mappingState.findByMagentoId(categories, 12).code, 'tables');
    assert.equal(mappingState.count(categories), 1);

    assert.equal(mappingState.assign(categories, 'tables', null), true);
    assert.equal(categories[1].magento_category_id, null);
    assert.equal(mappingState.findByMagentoId(categories, 12), null);
    assert.equal(mappingState.count(categories), 0);
});

test('mapping state resolves Magento categories by normalized identifier', () => {
    const mappingState = loadMappingState();
    const categories = [{id: 12, label: 'Chairs'}];

    assert.equal(mappingState.findCategoryById(categories, '12').label, 'Chairs');
    assert.equal(mappingState.findCategoryById(categories, 0), null);
    assert.equal(mappingState.findCategoryById(categories, 99), null);
});

test('removing a draft category reparents its children and removes the category', () => {
    const mappingState = loadMappingState();
    const categories = [
        {code: 'furniture', parent_code: null, source_parent_code: null},
        {code: 'chairs', parent_code: 'furniture', source_parent_code: 'furniture'},
        {code: 'office-chairs', parent_code: 'chairs', source_parent_code: 'chairs'}
    ];

    assert.equal(mappingState.remove(categories, 'chairs'), true);
    assert.deepEqual(Array.from(categories, (category) => category.code), ['furniture', 'office-chairs']);
    assert.equal(categories[1].parent_code, 'furniture');
    assert.equal(categories[1].source_parent_code, 'furniture');
    assert.equal(mappingState.remove(categories, 'missing'), false);
});

test('native dragstart, dragover and drop map an Ergonode category onto a Magento target', () => {
    const mappingState = loadMappingState();
    const harness = createDragHarness();
    const nativeDrag = loadNativeDrag(harness.$);
    const categories = [{code: 'chairs', magento_category_id: null}];
    const transferValues = {};
    const dataTransfer = {
        dropEffect: 'none',
        effectAllowed: 'none',
        getData(type) {
            return transferValues[type] || '';
        },
        setData(type, value) {
            transferValues[type] = value;
        }
    };
    let dirty = false;

    nativeDrag.enableSource(harness.source);
    nativeDrag.bind(harness.root, (code, magentoId) => {
        dirty = mappingState.assign(categories, code, magentoId);
    });

    const dragStart = createDragEvent(dataTransfer);
    const dragOver = createDragEvent(dataTransfer);
    const drop = createDragEvent(dataTransfer);

    assert.equal(harness.source.attr('draggable'), 'true');
    assert.equal(harness.source.jqueryUiDraggable, false);
    assert.equal(
        harness.handlers['dragstart.ergonodeCategoryDrag'].selector,
        '[draggable="true"][data-drag-type]'
    );
    harness.handlers['dragstart.ergonodeCategoryDrag'].handler.call(harness.source, dragStart);
    harness.handlers['dragover.ergonodeCategoryDrag'].handler.call(harness.target, dragOver);
    harness.handlers['drop.ergonodeCategoryDrag'].handler.call(harness.target, drop);

    assert.match(transferValues['application/json'], /"type":"category"/);
    assert.equal(dataTransfer.effectAllowed, 'move');
    assert.equal(dataTransfer.dropEffect, 'move');
    assert.equal(dragOver.defaultPrevented, true);
    assert.equal(drop.defaultPrevented, true);
    assert.equal(drop.propagationStopped, true);
    assert.equal(dirty, true);
    assert.equal(categories[0].magento_category_id, 12);
    assert.equal(mappingState.count(categories), 1);
});

test('subtree mapping finds every descendant without including the dragged parent', () => {
    const subtreeMapping = loadSubtreeMapping();
    const categories = [
        {code: 'furniture', source_parent_code: null},
        {code: 'chairs', source_parent_code: 'furniture'},
        {code: 'office-chairs', source_parent_code: 'chairs'},
        {code: 'tables', source_parent_code: 'furniture'},
        {code: 'outlet', source_parent_code: null}
    ];

    assert.deepEqual(
        Array.from(subtreeMapping.descendants(categories, 'furniture')),
        ['chairs', 'tables', 'office-chairs']
    );
    assert.deepEqual(Array.from(subtreeMapping.descendants(categories, 'outlet')), []);
});

test('branch identifiers include the selected node and all descendants for either tree schema', () => {
    const subtreeMapping = loadSubtreeMapping();
    const magentoCategories = [
        {id: 2, parent_id: 0},
        {id: 3, parent_id: 2},
        {id: 4, parent_id: 3},
        {id: 5, parent_id: 2},
        {id: 6, parent_id: 0}
    ];

    assert.deepEqual(
        Array.from(subtreeMapping.branchIdentifiers(magentoCategories, 2, 'id', 'parent_id')),
        ['2', '3', '5', '4']
    );
    assert.deepEqual(
        Array.from(subtreeMapping.branchIdentifiers(magentoCategories, 6, 'id', 'parent_id')),
        ['6']
    );
});

test('changing Ergonode branch visibility applies the same state to every descendant', () => {
    const subtreeMapping = loadSubtreeMapping();
    const categories = [
        {code: 'furniture', source_parent_code: null, active: true},
        {code: 'chairs', source_parent_code: 'furniture', active: true},
        {code: 'office-chairs', source_parent_code: 'chairs', active: true},
        {code: 'tables', source_parent_code: 'furniture', active: true},
        {code: 'outlet', source_parent_code: null, active: true}
    ];

    subtreeMapping.setBranchActive(categories, 'furniture', false);

    assert.deepEqual(
        Array.from(categories, ({code, active}) => [code, active]),
        [
            ['furniture', false],
            ['chairs', false],
            ['office-chairs', false],
            ['tables', false],
            ['outlet', true]
        ]
    );

    subtreeMapping.setBranchActive(categories, 'furniture', true);

    assert.equal(categories.slice(0, 4).every(({active}) => active), true);
    assert.equal(categories[4].active, true);
});

test('the Ergonode visibility action delegates branch selection to subtree state', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );

    assert.match(script, /subtreeMapping\.setBranchActive\(categories, identifier, !item\.active\)/);
});

test('subtree mapping applies backend assignments only to descendants in scope', () => {
    const subtreeMapping = loadSubtreeMapping();
    const categories = [
        {code: 'furniture', magento_category_id: 20, active: true},
        {code: 'chairs', magento_category_id: null, active: true, sync_status: 'pending'},
        {code: 'tables', magento_category_id: 99, active: true, sync_status: 'pending'},
        {code: 'outlet', magento_category_id: 13, active: true, sync_status: 'synced'}
    ];
    const suggestions = [
        {code: 'furniture', magento_category_id: 20, mapping_source: 'draft'},
        {code: 'chairs', magento_category_id: 21, mapping_source: 'name', sync_status: 'pending'},
        {code: 'tables', magento_category_id: null, mapping_source: 'unmatched', sync_status: 'pending'},
        {code: 'outlet', magento_category_id: 77, mapping_source: 'name', sync_status: 'pending'}
    ];

    const stats = subtreeMapping.applyAssignments(categories, suggestions, ['chairs', 'tables']);

    assert.deepEqual({...stats}, {mapped: 1, unmatched: 1});
    assert.equal(categories[0].magento_category_id, 20);
    assert.equal(categories[1].magento_category_id, 21);
    assert.equal(categories[1].mapping_source, 'name');
    assert.equal(categories[2].magento_category_id, null);
    assert.equal(categories[3].magento_category_id, 13);
});

test('dropping a parent delegates descendant matching to the backend preview mechanism', () => {
    const script = fs.readFileSync(
        path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'),
        'utf8'
    );

    assert.match(script, /nativeDrag\.bind\(\$root, mapDroppedCategory, scope\)/);
    assert.match(script, /function mapDroppedCategory\(code, magentoId\)/);
    assert.match(script, /subtreeMapping\.descendants\(categories, code\)/);
    assert.match(script, /previewCategories\([\s\S]*?descendants[\s\S]*?\);/);
    assert.match(script, /post\(config\.urls\.auto_map/);
    assert.doesNotMatch(consumerScript, /post\(config\.urls\.auto_map/);
    assert.match(script, /subtreeMapping\.applyAssignments\(/);
});

test('configuration order module reports the visible Category Tree order', () => {
    const configurationOrder = loadConfigurationOrder();
    const list = {
        querySelectorAll() {
            return [
                {getAttribute: () => '9'},
                {getAttribute: () => '4'},
            ];
        }
    };

    assert.deepEqual(Array.from(configurationOrder.collect(list)), [9, 4]);
});


test('configuration navigation separates links from actions and empty drop hints keep readable text', () => {
    const template = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml'), 'utf8');
    const script = fs.readFileSync(path.join(moduleRoot, 'view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');
    const story = fs.readFileSync(path.join(consumerAdminUiRoot, 'Test/Storybook/CategoryTreeMapping.stories.js'), 'utf8');
    assert.match(template, /<div class="<\?= \$escaper->escapeHtmlAttr\(\$configurationCardClass\) \?>"[\s\S]*?role="listitem"/);
    assert.match(template, /data-role="category-tree-configuration-list"\s+role="list"/);
    assert.match(template, /data-role="category-tree-configuration-link"\s+aria-current=/);
    assert.match(script, /\.attr\('aria-current', selected \? 'page' : 'false'\)/);
    assert.match(story, /const card = document\.createElement\('div'\);[\s\S]*?card\.setAttribute\('role', 'listitem'\)/);
    const placeholder = script.match(/class: 'vec-magento-mapping is-empty',[\s\S]*?\n                \);/)[0];
    assert.doesNotMatch(placeholder, /'aria-label'/);
    assert.match(placeholder, /class: 'vec-visually-hidden'/);
});
