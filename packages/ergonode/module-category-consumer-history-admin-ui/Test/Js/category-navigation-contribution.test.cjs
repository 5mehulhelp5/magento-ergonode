'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(moduleRoot, file), 'utf8');

test('history is reached through the sidebar instead of a navigation contribution', () => {
    assert.equal(fs.existsSync(path.join(moduleRoot, 'etc/di.xml')), false);
    assert.equal(fs.existsSync(path.join(moduleRoot, 'etc/adminhtml/menu.xml')), false);
    const adminhtmlDi = read('etc/adminhtml/di.xml');
    assert.doesNotMatch(adminhtmlDi, /CategoryNavigationGroupProvider|itemProviders|sectionCodes/);
    assert.match(adminhtmlDi, /AdminHistoryActorProvider/);
    assert.match(read('view/adminhtml/templates/category-tree-history/index.phtml'), /'category_history'/);
});
