'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {readPairTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const adminUiRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const pairTemplate = readPairTemplate(moduleRoot, 'attribute');
const mappingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const mappingStyles = fs.readFileSync(
    path.join(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css'),
    'utf8'
);
const workspaceStyles = fs.readFileSync(
    path.join(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css'),
    'utf8'
);
const mappingStory = fs.readFileSync(
    path.join(moduleRoot, 'Test/Storybook/AttributeMappingPair.stories.js'),
    'utf8'
);

test('attribute pair renders one language-style unlink action between both cards', () => {
    const leftCard = pairTemplate.indexOf('$renderSlot($block, $leftSlot)');
    const unlinkAction = pairTemplate.indexOf('class="vea-link-indicator vea-unlink-mapping"');
    const rightCard = pairTemplate.indexOf('$renderSlot($block, $rightSlot)');

    assert.ok(leftCard >= 0);
    assert.ok(unlinkAction > leftCard);
    assert.ok(rightCard > unlinkAction);
    assert.equal((pairTemplate.match(/data-role="unlink-mapping"/g) || []).length, 1);
    assert.doesNotMatch(pairTemplate, /class="vea-unlink"/);
    assert.equal(pairTemplate.includes('data-role="mapping-status"'), false);
});

test('unlink action releases both cards and removes the complete mapping row', () => {
    const unlinkHandler = mappingScript.slice(
        mappingScript.indexOf('function initUnlinkButtons(root)'),
        mappingScript.indexOf('function initCreateMagentoAttribute(root)')
    );
    const unlinkMapping = mappingScript.slice(
        mappingScript.indexOf('function unlinkMappingRow(root, row)'),
        mappingScript.indexOf('function initDrag(root)')
    );

    assert.match(unlinkHandler, /button\.closest\('\[data-role="mapping-row"\]'\)/);
    assert.match(unlinkHandler, /unlinkMappingRow\(root, row\);/);
    assert.match(unlinkMapping, /querySelectorAll\('\[data-role="pair-slot"\]'\)/);
    assert.match(unlinkMapping, /setAttributeMapped\(root, payload\.source, payload\.code, false\);/);
    assert.match(unlinkMapping, /row\.remove\(\);/);
});

test('dynamic mapping rows receive the same central unlink action without card actions', () => {
    assert.match(mappingScript, /function unlinkMappingHtml\(\)/);
    assert.match(mappingScript, /emptySlotHtml\('ergo', ''\),[\s\S]*?unlinkMappingHtml\(\),[\s\S]*?emptySlotHtml\('magento', ''\)/);
    assert.doesNotMatch(mappingScript, /function unlinkButtonHtml|class="vea-unlink"/);
    assert.doesNotMatch(mappingScript, /slot\.classList\.add\('has-unlink-action'\);/);
    assert.match(mappingScript, /root\.veaWorkspace\.delegate\('click', '\[data-role="unlink-mapping"\]'/);
});

test('option mapping progress and action are inline inside the Magento card', () => {
    assert.match(
        pairTemplate,
        /'side' => 'magento'[\s\S]*?'after_template' => \$hasOptionMappingAction[\s\S]*?attribute\/option-progress\.phtml/
    );
    assert.match(pairTemplate, /<\?php endif; \?>[\s\S]*?\$renderExtension\(\$block, \$afterTemplate\)/);
    assert.match(pairTemplate, /class="vea-option-map-entry"[\s\S]*?class="vea-option-map-progress"[\s\S]*?data-role="option-mapping-action"/);
    assert.match(
        mappingStyles,
        /\.vea-option-map-entry\s*\{[\s\S]*?flex-direction:\s*row;/
    );
    assert.match(mappingStyles, /\.vea-pair-card\.has-option-mapping-action\s*\{[\s\S]*?padding-right:\s*84px;/);
    assert.match(mappingScript, /right\.classList\.toggle\('has-option-mapping-action', available\);/);
});

test('attribute unlink action has the same interactive affordance as language mapping', () => {
    assert.match(mappingStyles, /\.vea-unlink-mapping:hover,[\s\S]*?background:\s*var\(--vea-red-soft\);/);
    assert.match(mappingStyles, /\.vea-unlink-mapping:focus-visible\s*\{[\s\S]*?box-shadow:/);
});

test('complete mappings have no status-specific surface styles while warning and error tones remain exceptional', () => {
    assert.doesNotMatch(mappingStyles, /\.vea-attribute-pair-row\.vea-status-tone-ok\s*\{/);
    assert.doesNotMatch(mappingStyles, /\.vea-pair-row\.vea-status-tone-ok:not\(\.vea-attribute-pair-row\)\s*\{/);
    assert.match(mappingStyles, /\.vea-pair-row\.vea-status-tone-warning\s*\{[\s\S]*?background:\s*var\(--vea-c-fffdf5\);/);
    assert.match(mappingStyles, /\.vea-pair-row\.vea-status-tone-error,[\s\S]*?background:\s*var\(--vea-c-fff7f7\);/);
    assert.match(mappingStory, /tone: 'ok'/);
    assert.match(mappingStory, /tone: 'warning'/);
    assert.match(mappingStory, /tone: 'error'/);
});

test('Storybook covers the production pair layout and keyboard unlink behavior', () => {
    assert.match(mappingStory, /loadCore\('Ergonode_CoreAdminUi\/js\/attribute-mapping'\)/);
    assert.match(mappingStory, /productionMapping\.unlinkMappingRow/);
    assert.match(mappingStory, /export const AllStates/);
    assert.match(mappingStory, /export const KeyboardUnlink/);
});

test('attribute mapping rows do not render the shared left status stripe', () => {
    assert.match(
        mappingStyles,
        /\.vea-attribute-pair-row::before\s*\{[\s\S]*?display:\s*none;/
    );
});

test('attribute mapping options reuse the universal entity action menu', () => {
    assert.match(workspaceStyles, /\.veui-entity-options\s*\{[\s\S]*?position:\s*relative;/);
    assert.match(workspaceStyles, /\.veui-entity-options-menu\s*\{[\s\S]*?position:\s*absolute;/);
    assert.match(workspaceStyles, /\.veui-tools-inline\s*\{[\s\S]*?display:\s*flex;/);
    assert.match(workspaceStyles, /\.veui-tools-inline > \.veui-source-options\s*\{[\s\S]*?margin-left:\s*auto;/);
    assert.doesNotMatch(mappingStyles, /\.vea-mapping-options/);
});
