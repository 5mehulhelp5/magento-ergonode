const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {readPairTemplate} = require('./template-contract.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const pairTemplate = readPairTemplate(moduleRoot, 'option');
const mappingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
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

test('option pair uses the central link indicator as its only unlink action', () => {
    const leftCard = pairTemplate.indexOf('$renderSlot($block, $leftSlot)');
    const unlinkAction = pairTemplate.indexOf('class="vea-link-indicator vea-unlink-mapping"');
    const rightCard = pairTemplate.indexOf('$renderSlot($block, $rightSlot)');

    assert.ok(leftCard >= 0);
    assert.ok(unlinkAction > leftCard);
    assert.ok(rightCard > unlinkAction);
    assert.equal((pairTemplate.match(/data-role="unlink-mapping"/g) || []).length, 1);
    assert.doesNotMatch(pairTemplate, /class="vea-unlink"/);
    assert.doesNotMatch(pairTemplate, /data-role="mapping-status"|class="vea-status/);
});

test('dynamic option rows render the same link action without a separate status', () => {
    assert.match(mappingScript, /function unlinkMappingHtml\(\)/);
    assert.match(mappingScript, /emptySlotHtml\('ergo', ''\),[\s\S]*?unlinkMappingHtml\(\),[\s\S]*?emptySlotHtml\('magento', ''\)/);
    assert.doesNotMatch(mappingScript, /class="vea-unlink"|data-role="mapping-status"|function getStatusLabel/);
});

test('option pair cards use the released row space and Storybook covers unlink inputs', () => {
    assert.match(styles, /\.vea-option-mapping \.vea-pair-row\s*\{[\s\S]*?padding-right:\s*10px;/);
    assert.match(story, /RozlaczenieMysza[\s\S]*?userEvent\.click/);
    assert.match(story, /RozlaczenieKlawiatura[\s\S]*?userEvent\.keyboard\('\{Enter\}'\)/);
});
