'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const mappingScript = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/ergonode-category-publisher-mapping.js'),
    'utf8'
);
const styles = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/css/ergonode-category-publisher.css'),
    'utf8'
);

test('tree creation uses an anchored accessible form instead of hidden native prompts', () => {
    assert.match(mappingScript, /vec-create-ergonode-tree/);
    assert.match(mappingScript, /veui-create-ergonode-icon/);
    assert.match(mappingScript, /function createTreeDialog\(config, \$root\)/);
    assert.match(mappingScript, /dialog\.setAttribute\('role', 'dialog'\)/);
    assert.match(mappingScript, /'aria-haspopup': 'dialog'/);
    assert.match(mappingScript, /'aria-expanded': 'false'/);
    assert.match(mappingScript, /pattern="\[A-Za-z0-9_-\]\+"/);
    assert.match(mappingScript, /jsonPost\.post\(config\.urls\.create_tree, config/);
    assert.doesNotMatch(mappingScript, /window\.prompt/);
    assert.match(mappingScript, /\$button\.get\(0\)\.addEventListener\('click', handleCreateTreeClick\)/);
    assert.doesNotMatch(mappingScript, /\$root\.on\('click', '\[data-role="create-ergonode-tree"\]'/);
    assert.match(mappingScript, /trigger\.closest\('\[data-role="new-mapping-modal"\]'\)/);
    assert.match(mappingScript, /Nie udało się utworzyć drzewa/);
    assert.match(styles, /\.vec-create-ergonode-tree-dialog\s*\{[\s\S]*?position:\s*fixed;[\s\S]*?z-index:\s*1100;/);
    assert.match(mappingScript, /left = buttonRect\.left - dialogRect\.width - gap/);
});
