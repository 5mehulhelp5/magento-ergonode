const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Attribute/Mapping.php'),
    'utf8'
);
const mappingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const mappingTemplate = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/templates/attribute/mapping.phtml'),
    'utf8'
);

test('attribute lists arrive label-sorted and are not reordered during initial mount', () => {
    const ergonodeAttributes = mappingBlock.slice(
        mappingBlock.indexOf('public function getErgonodeAttributes()'),
        mappingBlock.indexOf('public function getMagentoAttributes()')
    );
    const magentoAttributes = mappingBlock.slice(
        mappingBlock.indexOf('public function getMagentoAttributes()'),
        mappingBlock.indexOf('public function getExistingMagentoAttributeCodes()')
    );

    assert.match(ergonodeAttributes, /attributeListSorter->sortByLabel\(/);
    assert.match(magentoAttributes, /attributeListSorter->sortByLabel\(/);
    assert.match(mappingScript, /updateSidePanel\(panel\);/);
    assert.match(mappingScript, /updateSidePanel\(currentPanel, true\);/);
    assert.doesNotMatch(mappingScript, /markMappedAttributesFromRows/);
});

test('attribute cards arrive with their mapped visibility from the backend', () => {
    assert.match(mappingTemplate, /\$mappedCodes = \['ergo' => \[\], 'magento' => \[\]\]/);
    assert.match(mappingTemplate, /\$mappedCodes\[\$source\]\[\$code\] = true/);
    assert.match(mappingTemplate, /\$isMapped \? ' is-mapped' : ''/);
    assert.match(mappingTemplate, /data-mapped="<\?= \$isMapped \? '1' : '0' \?>"/);
    assert.match(mappingTemplate, /<\?= \$isMapped \|\| !\$isActive \? 'hidden' : '' \?>/);
});
