'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {modulePath} = require('./module-paths.cjs');

const systemXml = fs.readFileSync(
    path.resolve(__dirname, '../../etc/adminhtml/system.xml'),
    'utf8'
);
const attributeConsumerRoot = modulePath(
    'CategoryAttributeConsumer',
    'module-category-attribute-consumer'
);
const attributeDefaultConfigXml = fs.readFileSync(
    path.join(attributeConsumerRoot, 'etc/config.xml'),
    'utf8'
);
const baseRoot = modulePath('CategoryConsumer', 'module-category-consumer');
const baseConfigXml = fs.readFileSync(path.join(baseRoot, 'etc/config.xml'), 'utf8');
const baseCrontabXml = fs.readFileSync(path.join(baseRoot, 'etc/crontab.xml'), 'utf8');

test('category attribute configuration extends Categories without duplicating tree settings', () => {
    const categoryTreeFields = [
        'sync_tree_options',
        'tree_code',
        'root_category_id',
        'page_size',
        'sync_after_import',
        'remove_missing',
        'delete_missing_magento'
    ];

    for (const fieldId of categoryTreeFields) {
        assert.doesNotMatch(systemXml, new RegExp(`<field id="${fieldId}"`));
    }

    assert.doesNotMatch(systemXml, /<section id="ergonode_category_attributes"/);
    assert.match(systemXml, /<section id="ergonode_categories"/);
    assert.match(systemXml, /<group id="synchronization"/);
    assert.doesNotMatch(systemXml, /<field id="category_trees_link"/);
    assert.doesNotMatch(systemXml, /SynchronizationButton|<button_url>/);
});

test('managed category attributes expose mapping and manual ownership configuration', () => {
    assert.match(systemXml, /<group id="data_cron"[\s\S]*?<label>Attributes<\/label>/);
    assert.match(systemXml, /<field id="status" translate="label">/);
    assert.match(systemXml, /<field id="name_mode"/);
    assert.match(systemXml, /<field id="include_in_menu_mode"/);
    assert.match(systemXml, /<field id="include_in_menu_default"/);
    assert.match(systemXml, /<field id="is_active_mode"/);
    assert.match(systemXml, /<field id="is_active_default"/);
    assert.match(systemXml, /CategoryAttributeMode/);
    assert.match(systemXml, /CategoryNameMode/);
    assert.match(systemXml, /uses the default only when creating a category and never overwrites it later/);
    assert.match(systemXml, /Name &gt; Update in Settings takes priority/);
    assert.match(
        systemXml,
        /<field id="include_in_menu_default"[\s\S]*?<field id="include_in_menu_mode">manual<\/field>/
    );
    assert.match(
        systemXml,
        /<field id="is_active_default"[\s\S]*?<field id="is_active_mode">manual<\/field>/
    );
    assert.match(attributeDefaultConfigXml, /<name_mode>manual<\/name_mode>/);
    assert.match(attributeDefaultConfigXml, /<include_in_menu_mode>mapping<\/include_in_menu_mode>/);
    assert.match(
        baseConfigXml,
        /<synchronization>[\s\S]*?<status>1<\/status>/
    );
    assert.match(attributeDefaultConfigXml, /<include_in_menu_default>1<\/include_in_menu_default>/);
    assert.match(attributeDefaultConfigXml, /<is_active_mode>mapping<\/is_active_mode>/);
    assert.match(attributeDefaultConfigXml, /<is_active_default>1<\/is_active_default>/);
});

test('the base owns one data cron while the extension contributes presentation and a text target', () => {
    assert.equal(fs.existsSync(path.join(attributeConsumerRoot, 'etc/crontab.xml')), false);
    assert.match(baseCrontabXml, /name="ergonode_category_attribute_import"/);
    assert.match(baseCrontabXml, /Ergonode\\CategoryConsumer\\Cron\\ImportCategoryData/);
    assert.match(baseCrontabXml, /ergonode_category_attributes\/cron\/schedule/);
    assert.match(systemXml, /<group id="data_cron"[\s\S]*?<label>Attributes<\/label>/);
    assert.match(systemXml, /<field id="name_attribute"[\s\S]*?<field id="name_mode">attribute<\/field>/);
    assert.match(systemXml, /CategoryNameAttribute/);
});
