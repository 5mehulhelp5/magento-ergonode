'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const adminJs = fs.readFileSync(
    path.resolve(__dirname, '../../view/adminhtml/web/js/category-tree-mapping.js'),
    'utf8'
);
const template = fs.readFileSync(
    path.resolve(__dirname, '../../view/adminhtml/templates/category-tree-mapping/index.phtml'),
    'utf8'
);
const block = fs.readFileSync(
    path.resolve(__dirname, '../../Block/Adminhtml/CategoryTreeMapping/Index.php'),
    'utf8'
);
const provider = fs.readFileSync(
    path.resolve(__dirname, '../../Model/CategoryTreeMappingUiProvider.php'),
    'utf8'
);

test('mapping screen and requests use category_tree_id instead of raw root_category_id', () => {
    assert.match(adminJs, /category_tree_id/);
    assert.doesNotMatch(adminJs, /post\([^)]*root_category_id/);
    assert.match(template, /data-role="category-tree-configuration-list"/);
    assert.doesNotMatch(template, /category-tree-selector/);
    assert.doesNotMatch(template, /vec-root-select|Zakres mapowania/);
    assert.doesNotMatch(template, /category-tree-name/);
    assert.doesNotMatch(adminJs, /currentCategoryTree\.name/);
    assert.doesNotMatch(adminJs, /category-tree-selection-state|changeCategoryTree/);
    assert.doesNotMatch(template, /root-category-select/);
});

test('Category Tree list keeps mapping URLs as fallback and exposes the AJAX load endpoint', () => {
    assert.doesNotMatch(block, /'index'\s*=>/);
    assert.doesNotMatch(provider, /'categoryTrees'\s*=>/);
    assert.match(provider, /'category_trees'\s*=>\s*\$categoryTrees/);
    assert.match(block, /'mapping_url'/);
    assert.match(block, /'load'\s*=>\s*\$this->getUrl\('ergonode\/category_tree_mapping\/load'\)/);
    assert.match(
        block,
        /\['category_tree_id'\s*=>\s*\(int\)\$categoryTree\['category_tree_id'\]\]/
    );
    assert.equal(
        fs.existsSync(path.resolve(
            __dirname,
            '../../view/adminhtml/web/js/category-tree-selection-state.js'
        )),
        false
    );
});

test('category refresh and configuration selection replace backend models without a page reload', () => {
    assert.doesNotMatch(adminJs, /window\.location\.reload\(\)/);
    assert.match(adminJs, /replaceModels\(response, true\)/);
    assert.match(adminJs, /categories = normalizeCategories\(response\.categories/);
    assert.match(adminJs, /magentoCategories = normalizeMagentoCategories\(response\.magento_categories/);
    assert.match(template, /categoryTree\['mapping_url'\]/);
    assert.match(template, /data-role="category-tree-configuration-link"/);
    assert.match(adminJs, /url: config\.urls\.load[\s\S]*?type: 'GET',[\s\S]*?cache: false/);
    assert.match(adminJs, /if \(!config\.current_category_tree\)\s*\{\s*return;/);
    assert.match(adminJs, /applyCategoryTreeConfig\(response\.config\)/);
    assert.match(adminJs, /window\.history\.replaceState/);
    assert.doesNotMatch(adminJs, /config\.urls\.index|new URL\(/);
});

test('configuration selection clears the shared message without referencing a removed jQuery handle', () => {
    const applyCategoryTreeConfig = adminJs.match(
        /function applyCategoryTreeConfig\(nextConfig\)[\s\S]*?function updateSelectedCategoryTree/
    )[0];

    assert.match(applyCategoryTreeConfig, /messageBus\.hide\(\)/);
    assert.doesNotMatch(applyCategoryTreeConfig, /\$message/);
});
