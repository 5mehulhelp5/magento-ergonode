'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {readMappingTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const attributeScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const optionScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);
const mappingBoardScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/mapping-board.js'),
    'utf8'
);
const mappingStyles = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi'), 'view/adminhtml/web/css/attribute-mapping.css'),
    'utf8'
);
const mappingStorybookFactory = fs.readFileSync(
    path.join(require('./module-contract.cjs').repositoryRoot, 'dev/tools/ergonode-storybook/src/mapping-workspace.js'),
    'utf8'
);
function readTemplate(entity) {
    return readMappingTemplate(moduleRoot, entity);
}

function assertSharedPanelHeading(template, label) {
    const panelHeadings = template.match(
        /<strong class="veui-panel-title">[\s\S]*?<\/strong>/g
    ) || [];

    assert.equal(panelHeadings.length, 2);

    for (const heading of panelHeadings) {
        assert.match(heading, new RegExp(`__\\('${label}'\\)`));
        assert.doesNotMatch(heading, /__\('(Ergonode|Magento 2)'\)/);
    }
}

test('attribute source panels use the shared Attributes heading', () => {
    assertSharedPanelHeading(readTemplate('attribute'), 'Attributes');
});

test('option source panels use the shared Options heading', () => {
    assertSharedPanelHeading(readTemplate('option'), 'Options');
});

test('option mapping header combines its context selector and actions', () => {
    const template = readTemplate('option');
    const headerStart = template.indexOf('class="veui-panel-head veui-panel-head-with-tools vea-panel-head vea-mapping-head"');
    const toolsStart = template.indexOf('data-role="mapping-search"', headerStart);
    const header = template.slice(headerStart, toolsStart);

    assert.match(header, /class="vea-option-context vea-option-context-select"/);
    assert.match(header, /class="vea-option-context-native"/);
    assert.doesNotMatch(header, /vea-color-filter|veui-count vea-count|data-role="mapping-count"/);
    assert.match(mappingStyles, /\.vea-option-context\s*\{[\s\S]*?width:\s*100%;/);
    assert.doesNotMatch(mappingStyles, /\.vea-color-filter\s*\{/);
    assert.doesNotMatch(optionScript, /mapping-color-filter|mapping-count|CoreAdminUi\/js\/counters/);
});

test('attribute source cards render their initial visibility before JavaScript mounts', () => {
    const template = readTemplate('attribute');

    assert.match(template, /\$mappedCodes = \['ergo' => \[\], 'magento' => \[\]\]/);
    assert.match(template, /\$mappedCodes\[\$source\]\[\$code\] = true/);
    assert.match(template, /\$isMapped \? ' is-mapped' : ''/);
    assert.match(template, /\$isActive \? '' : ' is-filter-hidden is-inactive'/);
    assert.match(template, /data-mapped="<\?= \$isMapped \? '1' : '0' \?>"/);
    assert.match(template, /<\?= \$isMapped \|\| !\$isActive \? 'hidden' : '' \?>/);
});

test('attribute mapping refreshes a side panel after runtime mapped-state changes', () => {
    assert.match(attributeScript, /function setAttributeMapped\(root, source, code, mapped\)/);
    assert.match(attributeScript, /if \(panel\) \{[\s\S]*updateSidePanel\(panel\)/);
    assert.doesNotMatch(attributeScript, /refreshPanel|markMappedAttributesFromRows/);
});

test('attribute mapping places save and central actions beside the mapping search', () => {
    const template = readTemplate('attribute');
    const toolbarStart = template.indexOf('class="veui-toolbar vea-viewbar"');
    const toolbar = template.slice(toolbarStart, template.indexOf('data-role="global-message"'));
    const mappingPanelStart = template.indexOf('class="veui-panel vea-panel vea-mapping-panel"');
    const toolsStart = template.indexOf('class="veui-panel-head-tools"', mappingPanelStart);
    const tools = template.slice(toolsStart, template.indexOf('class="vea-pairs-scroll"', toolsStart));
    const searchIndex = tools.indexOf('data-role="mapping-search"');
    const saveIndex = tools.indexOf('data-role="save-mapping"');
    const optionsIndex = tools.indexOf('data-role="entity-options"');

    assert.doesNotMatch(toolbar, /vea-back-action|data-role="mapping-back"/);
    assert.doesNotMatch(toolbar, /data-role="save-mapping"|data-role="complete-missing"/);
    assert.match(template, /class="veui-panel-head-tools"/);
    assert.ok(searchIndex >= 0 && searchIndex < saveIndex && saveIndex < optionsIndex);
    assert.match(
        tools,
        /class="veui-split-button veui-split-button-align-end veui-split-button-primary"/
    );
    assert.match(
        tools,
        /class="veui-button veui-button-primary veui-button-toolbar veui-split-button-main[\s\S]*?vea-view-action vea-save-action"/
    );
    assert.match(
        tools,
        /<details class="veui-entity-options veui-split-button-options" data-role="entity-options">/
    );
    assert.match(
        tools,
        /<summary class="veui-split-button-toggle"[\s\S]*aria-label="<\?= \$escaper->escapeHtmlAttr\(__\('Opcje mapowania'\)\) \?>"/
    );
    assert.match(tools, /class="veui-entity-options-menu veui-split-button-menu"/);
    assert.match(tools, /data-role="auto-match"[\s\S]*data-role="auto-match-label"[\s\S]*Auto Connect/);
    assert.match(tools, /data-role="complete-missing"[\s\S]*vea-complete-missing-icon[\s\S]*Uzupełnij/);
    assert.doesNotMatch(tools, /mapping-color-filter|data-filter-tone|Poprawne|Ostrzeżenia|Błędy/);
    assert.doesNotMatch(attributeScript, /mapping-color-filter|data-filter-tone|updateMappingColorFilter/);
    assert.doesNotMatch(mappingBoardScript, /mapping-color-filter|data-filter-tone|activeTones/);
    assert.equal((template.match(/data-role="auto-match"/g) || []).length, 1);

    assert.match(mappingStorybookFactory, /definition === definitions\.attribute/);
    assert.match(mappingStorybookFactory, /actions\.append\(save, options\)[\s\S]*tools\.append\(search, actions\)/);
    assert.match(mappingStorybookFactory, /data-role="auto-match"[\s\S]*data-role="complete-missing"/);
});

test('option mapping places save and Auto Connect in the middle-column tools', () => {
    const template = readTemplate('option');
    const leftStart = template.indexOf('data-source-panel="ergo"');
    const middleStart = template.indexOf('class="veui-panel vea-panel vea-mapping-panel"', leftStart);
    const rightStart = template.indexOf('data-source-panel="magento"', middleStart);
    const leftPanel = template.slice(leftStart, middleStart);
    const middlePanel = template.slice(middleStart, rightStart);
    const searchIndex = middlePanel.indexOf('data-role="mapping-search"');
    const saveIndex = middlePanel.indexOf('data-role="save-mapping"');
    const optionsIndex = middlePanel.indexOf('data-role="entity-options"');

    assert.doesNotMatch(leftPanel, /data-role="auto-match"|__\('Auto Connect'\)/);
    assert.match(middlePanel, /class="veui-panel-head-tools"/);
    assert.ok(searchIndex >= 0 && searchIndex < saveIndex && saveIndex < optionsIndex);
    assert.match(
        middlePanel,
        /class="veui-button veui-button-primary veui-button-toolbar veui-split-button-main[\s\S]*?vea-view-action vea-save-action"/
    );
    assert.match(
        middlePanel,
        /data-role="auto-match"[\s\S]*__\('Auto Connect'\)/
    );
    assert.match(template, /Automatically match option mappings without saving/);
    assert.match(middlePanel, /data-role="complete-missing"/);
    assert.equal((template.match(/data-role="auto-match"/g) || []).length, 1);
});

test('side panels expose source actions and sorting through the universal entity options component', () => {
    ['attribute', 'option'].forEach((entity) => {
        const template = readTemplate(entity);
        const sideTools = template.match(
            /<div class="veui-panel-head-tools vea-side-tools">[\s\S]*?<\/details>/g
        ) || [];

        assert.equal(sideTools.length, 2);
        sideTools.forEach((menu, index) => {
            assert.match(menu, /<details class="veui-entity-options veui-source-options"[\s\S]*?data-role="entity-options"/);
            assert.match(menu, new RegExp(`data-source-options="${index === 0 ? 'ergo' : 'magento'}"`));
            assert.match(menu, /<summary role="button"[\s\S]*aria-label=/);
            assert.equal((menu.match(/class="veui-entity-options-action"/g) || []).length, 2);
            assert.match(menu, /class="veui-entity-options-action veui-visibility-control"/);
            assert.match(menu, /data-role="visibility-toggle"[\s\S]*?__\('Excluded'\)/);
            assert.match(menu, /data-role="attribute-sort-direction-label"[\s\S]*Góra/);
            assert.match(menu, /data-role="attribute-sort-label"[\s\S]*Nazwa/);
        });
        assert.match(sideTools[0], new RegExp(`${entity === 'attribute' ? 'Attribute' : 'Option'} actions: Ergonode`));
        assert.match(sideTools[0], /class="veui-entity-options-action vea-refresh-ergonode"/);
        assert.match(sideTools[0], /data-role="refresh-ergonode"[\s\S]*vea-refresh-ergonode-icon[\s\S]*Odśwież/);
        assert.doesNotMatch(sideTools[0], /data-role="auto-match"|__\('Auto Connect'\)/);
        assert.ok(sideTools[0].indexOf('data-role="refresh-ergonode"') < sideTools[0].indexOf('data-role="attribute-sort-direction"'));
        assert.doesNotMatch(sideTools[1], /data-role="refresh-ergonode"/);
        assert.doesNotMatch(sideTools[1], /data-role="auto-match"/);
        assert.doesNotMatch(template, /vea-(?:mapping|sort)-options|class="vea-sort-control"/);
    });
});

test('attribute Storybook does not duplicate the universal entity options component', () => {
    assert.equal(fs.existsSync(path.join(
        moduleRoot,
        'Test/Storybook/AttributeMappingOptions.stories.js'
    )), false);
    assert.equal(fs.existsSync(path.join(
        moduleRoot,
        'Test/Storybook/AttributeSideSortOptions.stories.js'
    )), false);
});
