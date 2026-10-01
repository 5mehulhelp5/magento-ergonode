const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {readMappingTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const layout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_option_index.xml'),
    'utf8'
);
const styles = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/css/option-mapping.css'),
    'utf8'
);
const story = fs.readFileSync(
    path.join(moduleRoot, 'Test/Storybook/OptionMapping.stories.js'),
    'utf8'
);
const template = readMappingTemplate(moduleRoot, 'option');
const attributeScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);

test('option toolbar uses its production stylesheet in runtime and Storybook', () => {
    assert.match(layout, /Ergonode_CoreAdminUi::css\/option-mapping\.css/);
    assert.match(story, /view\/adminhtml\/web\/css\/option-mapping\.css/);
});

test('option actions use the shared compact toolbar geometry and icon size', () => {
    assert.doesNotMatch(styles, /\.vea-option-mapping \.vea-viewbar > \.veui-button/);
    assert.match(
        template,
        /class="veui-split-button veui-split-button-align-end veui-split-button-primary"/
    );
    assert.match(
        template,
        /class="veui-button veui-button-primary veui-button-toolbar veui-split-button-main[\s\S]*?vea-view-action vea-save-action"/
    );
    assert.match(styles, /\.vea-option-mapping \.vea-complete-missing-icon\s*\{[\s\S]*?height:\s*14px;[\s\S]*?width:\s*14px;/);
    assert.match(story, /vea-auto-match-icon/);
    assert.match(story, /veui-visibility-icon/);
    assert.match(story, /vea-complete-missing-icon/);
    assert.match(story, /vea-save-icon/);
    assert.doesNotMatch(story, /full-view|Full View/);
});

test('option completion action belongs to the middle mapping options menu', () => {
    const toolbarStart = template.indexOf('class="veui-toolbar vea-viewbar"');
    const toolbarEnd = template.indexOf('class="veui-message veui-global-message"', toolbarStart);
    const toolbar = template.slice(toolbarStart, toolbarEnd);
    const mappingPanelStart = template.indexOf('class="veui-panel vea-panel vea-mapping-panel"');
    const middleToolsStart = template.indexOf('class="veui-panel-head-tools"', mappingPanelStart);
    const middleToolsEnd = template.indexOf('class="vea-pairs-scroll"', middleToolsStart);
    const middleTools = template.slice(middleToolsStart, middleToolsEnd);

    const searchIndex = middleTools.indexOf('data-role="mapping-search"');
    const saveIndex = middleTools.indexOf('data-role="save-mapping"');
    const optionsIndex = middleTools.indexOf('data-role="entity-options"');

    assert.doesNotMatch(toolbar, /data-role="save-mapping"|data-role="complete-missing"/);
    assert.ok(searchIndex >= 0 && searchIndex < saveIndex && saveIndex < optionsIndex);
    assert.match(
        middleTools,
        /class="veui-button veui-button-primary veui-button-toolbar veui-split-button-main[\s\S]*?vea-view-action vea-save-action"/
    );
    assert.match(middleTools, /<details class="veui-entity-options veui-split-button-options" data-role="entity-options">/);
    assert.match(middleTools, /aria-label="<\?= \$escaper->escapeHtmlAttr\(__\('Opcje mapowania'\)\) \?>"/);
    assert.match(
        middleTools,
        /class="veui-entity-options-action veui-split-button-option[\s\S]*?vea-complete-missing"/
    );
    assert.match(middleTools, /data-role="complete-missing"/);
    assert.match(story, /\['auto-match', 'Auto Connect'/);
    assert.match(story, /OpcjeMapowaniaMysza/);
});

test('option auto-match action belongs to the middle mapping options menu', () => {
    const toolbarStart = template.indexOf('class="veui-toolbar vea-viewbar"');
    const toolbarEnd = template.indexOf('class="veui-message veui-global-message"', toolbarStart);
    const toolbar = template.slice(toolbarStart, toolbarEnd);
    const sourceToolsStart = template.indexOf('class="veui-panel-head-tools vea-side-tools"');
    const sourceToolsEnd = template.indexOf('</details>', sourceToolsStart);
    const sourceTools = template.slice(sourceToolsStart, sourceToolsEnd);
    const mappingPanelStart = template.indexOf('class="veui-panel vea-panel vea-mapping-panel"');
    const middleToolsStart = template.indexOf('class="veui-panel-head-tools"', mappingPanelStart);
    const middleToolsEnd = template.indexOf('class="vea-pairs-scroll"', middleToolsStart);
    const middleTools = template.slice(middleToolsStart, middleToolsEnd);

    assert.doesNotMatch(toolbar, /data-role="auto-match"/);
    assert.doesNotMatch(sourceTools, /class="veui-entity-options-action vea-auto-match"|data-role="auto-match"/);
    assert.match(middleTools, /class="veui-entity-options-action veui-split-button-option vea-auto-match"/);
    assert.match(middleTools, /data-role="auto-match"/);
    assert.ok(middleTools.indexOf('data-role="auto-match"') < middleTools.indexOf('data-role="complete-missing"'));
    assert.doesNotMatch(story, /\.vea-viewbar > \[data-role="auto-match"\]/);
    assert.match(story, /createSourceOptions\(panel\.dataset\.sourcePanel/);
    assert.match(story, /AutoDopasowanieWOpcjachMapowania/);
});

test('option visibility actions belong to both source menus and use the language label', () => {
    const toolbarStart = template.indexOf('class="veui-toolbar vea-viewbar"');
    const toolbarEnd = template.indexOf('class="veui-message veui-global-message"', toolbarStart);
    const toolbar = template.slice(toolbarStart, toolbarEnd);
    const visibilityActions = template.match(/data-role="visibility-toggle"/g) || [];

    assert.doesNotMatch(toolbar, /data-role="visibility-toggle"|toggle-full-view|vea-full-view/);
    assert.equal(visibilityActions.length, 2);
    assert.match(template, /class="veui-entity-options-action veui-visibility-control"/);
    assert.match(template, /class="veui-visibility-icon"/);
    assert.match(template, /__\('Excluded'\)/);
    assert.match(story, /label: 'Excluded'/);
    assert.match(story, /WidocznoscWykluczonychOpcji/);
});

test('option toolbar no longer exposes the redundant back action', () => {
    assert.doesNotMatch(template, /vea-back-action|data-role="mapping-back"/);
    assert.doesNotMatch(story, /data-role="mapping-back"/);
    assert.doesNotMatch(attributeScript, /mapping-back|initBackAction/);
});
