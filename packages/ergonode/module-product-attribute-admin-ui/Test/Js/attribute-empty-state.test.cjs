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
const mappingBlock = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Attribute/Mapping.php'),
    'utf8'
);
const layout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_attribute_index.xml'),
    'utf8'
);
const stylesheet = fs.readFileSync(
    path.join(coreRoot, 'view/adminhtml/web/css/attribute-empty-state.css'),
    'utf8'
);

test('Ergonode attribute refresh lives in source options and remains available in the empty state', () => {
    const sourcePanelStart = template.indexOf('data-source-panel="ergo"');
    const sourceToolsStart = template.indexOf('class="veui-panel-head-tools vea-side-tools"', sourcePanelStart);
    const sourceListStart = template.indexOf('class="vea-attribute-list"', sourceToolsStart);

    assert.doesNotMatch(template.slice(sourcePanelStart, sourceToolsStart), /data-role="refresh-ergonode"/);
    assert.match(
        template.slice(sourceToolsStart, sourceListStart),
        /\$ergonodeAttributes !== \[\][\s\S]*?class="veui-entity-options-action vea-refresh-ergonode"[\s\S]*?data-role="refresh-ergonode"/
    );
    assert.match(template, /\$ergonodeAttributes === \[\]/);
    assert.match(template, /data-role="attribute-empty-state"/);
    assert.match(template, /data-role="attribute-empty-actions"/);
    assert.doesNotMatch(template, /\$snapshotAvailable/);
    assert.match(template, /Brak pobranych atrybutów Ergonode/);
    assert.match(template, /data-role="attribute-empty-actions"[\s\S]*?data-role="refresh-ergonode"[\s\S]*?Odśwież/);
});

test('attribute empty state stylesheet is scoped and loaded by the mapping page', () => {
    assert.match(layout, /Ergonode_CoreAdminUi::css\/attribute-empty-state\.css/);
    assert.match(stylesheet, /\.vea-attribute-empty-state/);
    assert.match(stylesheet, /\.vea-attribute-empty-actions/);
});

test('refresh has a neutral fetch URL when the inbound snapshot UI is absent', () => {
    assert.match(
        mappingBlock,
        /'refresh' => \$this->getData\('snapshot_available'\)[\s\S]*?'ergonode\/attribute\/refresh'[\s\S]*?'ergonode\/attribute\/fetch'/
    );
});
