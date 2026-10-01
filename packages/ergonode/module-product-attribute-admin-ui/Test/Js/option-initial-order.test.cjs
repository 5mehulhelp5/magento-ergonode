const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Option/Mapping.php'),
    'utf8'
);
const mappingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/option-mapping.js'),
    'utf8'
);
const mappingTemplate = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/templates/option/mapping.phtml'),
    'utf8'
);

test('option lists arrive label-sorted and are not reordered during initial mount', () => {
    const ergonodeOptions = mappingBlock.slice(
        mappingBlock.indexOf('public function getErgonodeOptions()'),
        mappingBlock.indexOf('public function getMagentoOptions()')
    );
    const magentoOptions = mappingBlock.slice(
        mappingBlock.indexOf('public function getMagentoOptions()'),
        mappingBlock.indexOf('public function getDraftMappings()')
    );

    assert.match(ergonodeOptions, /attributeListSorter->sortByLabel\(/);
    assert.match(magentoOptions, /attributeListSorter->sortByLabel\(/);
    assert.match(mappingBlock, /\?AttributeListSorter \$attributeListSorter = null/);
    assert.doesNotMatch(mappingBlock, /hasAttributeContexts/);
    assert.doesNotMatch(mappingBlock, /get(?:Refresh|Save|Context)Url/);
    assert.match(mappingScript, /updateAllSidePanels\(element\);/);
    assert.doesNotMatch(mappingScript, /updateAllSidePanels\(element, true\);/);
    assert.doesNotMatch(mappingScript, /markInitialMappedOptions/);
});

test('option cards arrive with their mapped visibility from the backend', () => {
    assert.match(mappingTemplate, /\$mappedCodes = \['ergo' => \[\], 'magento' => \[\]\]/);
    assert.match(mappingTemplate, /\$mappedCodes\[\$source\]\[\$code\] = true/);
    assert.match(mappingTemplate, /\$isMapped \? ' is-mapped' : ''/);
    assert.match(mappingTemplate, /data-mapped="<\?= \$isMapped \? '1' : '0' \?>"/);
    assert.match(mappingTemplate, /<\?= \$isMapped \|\| !\$isActive \? 'hidden' : '' \?>/);
    assert.doesNotMatch(mappingTemplate, /data-(?:refresh|save|context)-url/);
});
