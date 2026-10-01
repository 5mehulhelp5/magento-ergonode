'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const coreRoot = require('./module-contract.cjs').resolveModuleRoot('Ergonode_CoreAdminUi');
const template = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/templates/attribute/mapping.phtml'),
    'utf8'
);
const mappingScript = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/js/attribute-mapping.js'),
    'utf8'
);
const magentoAttributeProvider = fs.readFileSync(
    path.join(require('./module-contract.cjs').resolveModuleRoot('Ergonode_ProductAttribute'), 'Model/Provider/MagentoAttributeProvider.php'),
    'utf8'
);

test('Magento attribute configuration determines whether mapping is required', () => {
    assert.match(
        magentoAttributeProvider,
        /\$required = \(bool\)\$attribute->getIsRequired\(\)[\s\S]*?attributePolicy->isMappingRequired\(\$code\)/
    );
    assert.match(magentoAttributeProvider, /'required' => \$required/);
    assert.match(
        magentoAttributeProvider,
        /\$attribute\['active'\] = \$attribute\['required'\] \|\| \(\$activeMap\[\$attribute\['code'\]\] \?\? true\)/
    );
    assert.doesNotMatch(magentoAttributeProvider, /\['sku'\]|===\s*'sku'/);
});

test('required Magento attribute uses the shared mapping requirement instead of a visibility toggle', () => {
    assert.match(template, /\$isRequired = !empty\(\$attribute\['required'\]\)/);
    assert.match(template, /data-mapping-required="<\?= \$isRequired \? 'true' : 'false' \?>"/);
    assert.match(template, /<\?php if \(\$isRequired\): \?>[\s\S]*?class="veui-required-badge"/);
    assert.match(template, /class="veui-mapping-requirement-control"/);
    assert.match(template, /class="veui-mapping-requirement-icon"/);
    assert.match(template, /class="veui-mapping-requirement-tooltip"/);
    assert.match(template, /Wymagane mapowanie atrybutu/);
    assert.match(template, /Bez tego synchronizacja nie może się rozpocząć/);
    assert.match(
        template,
        /<\?php else: \?>[\s\S]*?data-role="attribute-active-toggle"[\s\S]*?<\?php endif; \?>/
    );
    assert.doesNotMatch(template, /\$attribute\['code'\]\s*===?\s*['"]sku['"]/);
});

test('attribute interactions continuously refresh the shared requirement state', () => {
    assert.match(mappingScript, /Ergonode_CoreAdminUi\/js\/mapping-requirements/);
    assert.match(mappingScript, /mappingRequirements\.create\(element\)/);
    assert.match(mappingScript, /requirements\.refresh\(\)/);
    assert.match(mappingScript, /new MutationObserver\(function \(\) \{[\s\S]*?requirements\.refresh\(\)/);
    assert.match(mappingScript, /attributeFilter: \['data-code'\]/);
    assert.match(mappingScript, /requirementObserver\.disconnect\(\)/);
});
