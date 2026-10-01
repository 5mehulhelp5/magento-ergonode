'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const projectRoot = path.resolve(__dirname, '../../../../..');
const appCodeRoot = path.join(projectRoot, 'app/code/Ergonode');
const packagesRoot = path.join(projectRoot, 'packages/ergonode');
const categoryAdminUiRoot = path.resolve(__dirname, '../..');
const consumerAdminUiRoot = fs.existsSync(path.join(packagesRoot, 'module-category-consumer-admin-ui/composer.json'))
    ? path.join(packagesRoot, 'module-category-consumer-admin-ui')
    : path.join(appCodeRoot, 'CategoryConsumerAdminUi');
const historyAdminUiRoot = fs.existsSync(path.join(packagesRoot, 'module-category-consumer-history-admin-ui/composer.json'))
    ? path.join(packagesRoot, 'module-category-consumer-history-admin-ui')
    : path.join(appCodeRoot, 'CategoryConsumerHistoryAdminUi');

test('publisher and neutral workspace have no dependency path to the category consumer', () => {
    const modules = new Map();
    for (const root of [appCodeRoot, packagesRoot]) {
        for (const name of fs.readdirSync(root)) {
            const file = path.join(root, name, 'composer.json');
            if (fs.existsSync(file)) {
                const metadata = JSON.parse(fs.readFileSync(file, 'utf8'));
                modules.set(metadata.name, metadata.require || {});
            }
        }
    }
    for (const start of ['category', 'category-admin-ui', 'category-publisher', 'category-publisher-admin-ui']) {
        const queue = ['ergonode/module-' + start], seen = new Set();
        while (queue.length) {
            const current = queue.pop();
            if (seen.has(current)) { continue; }
            seen.add(current);
            assert.doesNotMatch(current, /^ergonode\/module-category-consumer(?:-admin-ui)?$/);
            queue.push(...Object.keys(modules.get(current) || {}));
        }
    }
    const script = fs.readFileSync(path.join(categoryAdminUiRoot, 'view/adminhtml/web/js/category-tree-mapping.js'), 'utf8');
    assert.doesNotMatch(script, /Ergonode_CategoryConsumer|syncRun|urls\.sync/);
    assert.match(script, /post\(config\.urls\.auto_map/);
});

test('history uses the neutral assets directly without external symlinks', () => {
    for (const asset of ['css/category-tree-mapping.css', 'images/category-unmap.svg', 'images/pending-save-info.svg']) {
        assert.equal(fs.existsSync(path.join(consumerAdminUiRoot, 'view/adminhtml/web', asset)), false);
        assert.equal(fs.existsSync(path.join(categoryAdminUiRoot, 'view/adminhtml/web', asset)), true);
    }
    const historyLayout = fs.readFileSync(path.join(historyAdminUiRoot, 'view/adminhtml/layout/ergonode_category_tree_history_index.xml'), 'utf8');
    assert.match(historyLayout, /Ergonode_CategoryAdminUi::css\/category-tree-mapping\.css/);
    const composer = JSON.parse(fs.readFileSync(path.join(historyAdminUiRoot, 'composer.json'), 'utf8'));
    assert.ok(composer.require['ergonode/module-category-consumer-admin-ui']);
    const consumerComposer = JSON.parse(fs.readFileSync(path.join(consumerAdminUiRoot, 'composer.json'), 'utf8'));
    assert.ok(consumerComposer.require['ergonode/module-category-admin-ui']);
    assert.equal(composer.require['ergonode/module-category-admin-ui'], undefined);
    const moduleXml = fs.readFileSync(path.join(historyAdminUiRoot, 'etc/module.xml'), 'utf8');
    assert.match(moduleXml, /<module name="Ergonode_CategoryAdminUi"\/>/);
});

test('every relative URL in the neutral CSS resolves inside the neutral package', () => {
    const sharedCss = path.join(categoryAdminUiRoot, 'view/adminhtml/web/css/category-tree-mapping.css');
    const css = fs.readFileSync(sharedCss, 'utf8');
    const urls = Array.from(css.matchAll(/url\(['"]([^'"]+)['"]\)/g), match => match[1]);
    assert.ok(urls.length > 0);
    for (const url of urls) {
        assert.ok(fs.realpathSync(path.resolve(path.dirname(sharedCss), url))
            .startsWith(fs.realpathSync(path.join(categoryAdminUiRoot, 'view/adminhtml/web')) + path.sep));
    }
});
