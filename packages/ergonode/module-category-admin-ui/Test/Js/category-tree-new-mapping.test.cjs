'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const moduleRoot = path.resolve(__dirname, '../..');

function read(file) {
    return fs.readFileSync(path.join(moduleRoot, file), 'utf8');
}

test('settings column keeps mapping actions while tree refresh belongs to the popup', () => {
    const template = read('view/adminhtml/templates/category-tree-mapping/index.phtml');
    const block = read('Block/Adminhtml/CategoryTreeMapping/Index.php');
    const modalStart = template.indexOf('class="vec-new-mapping-modal"');
    const settings = template.slice(template.indexOf('data-column-role="settings"'), modalStart);
    const settingsHead = settings.slice(0, settings.indexOf('<?php if ($categoryTrees !== []): ?>'));
    const modal = template.slice(modalStart);

    assert.doesNotMatch(settings, /data-role="refresh-tree-options"/);
    assert.match(settings, /data-role="open-new-mapping"/);
    assert.match(settings, /data-role="configuration-options-slot"/);
    assert.match(read('view/adminhtml/web/js/category-tree-mapping.js'), /role: 'open-edit-mapping'/);
    assert.doesNotMatch(settingsHead, /data-role="open-new-mapping"/);
    assert.doesNotMatch(settingsHead, /class="veui-panel-actions"/);
    assert.match(modal, /data-role="refresh-tree-options"/);
    assert.match(template, /data-role="category-tree-configuration"[\s\S]*?data-tree-code=/);
    assert.match(template, /data-root-category-id=/);
    assert.doesNotMatch(template, /data-sync-after-import=/);
    assert.doesNotMatch(template, /data-stream-cursor=/);
    assert.doesNotMatch(template, /data-last-synced-at=/);
    assert.match(template, /data-role="configuration-option-label"/);
    assert.match(block, /'tree_options_refresh'\s*=>/);
    assert.doesNotMatch(block, /'sync_cursor_reset'\s*=>/);
    assert.match(block, /\$config\['new_mapping_options'\]\s*=/);
    assert.match(block, /category_tree_manage/);
    assert.doesNotMatch(template, /getCategoryTreeUrl\(\)/);
});

test('mapping popup supports create, edit, refresh and delete actions', () => {
    const template = read('view/adminhtml/templates/category-tree-mapping/index.phtml');
    const modalStart = template.indexOf('class="vec-new-mapping-modal"');
    const modalEnd = template.indexOf('</div>\n    <?php endif; ?>', modalStart);
    const modal = template.slice(modalStart, modalEnd);

    assert.ok(modalStart >= 0);
    assert.match(modal, /name="category_tree_id" data-role="mapping-category-tree-id" value="0"/);
    assert.match(modal, /name="tree_code"/);
    assert.match(modal, /name="root_category_id"/);
    assert.match(modal, /name="is_active"/);
    assert.doesNotMatch(modal, /name="sync_after_import"/);
    assert.doesNotMatch(modal, /name="remove_missing"/);
    assert.match(modal, /data-role="refresh-tree-options"/);
    assert.match(modal, /data-role="delete-mapping"/);
    assert.match(modal, /data-role="delete-category-tree-form"/);
    assert.doesNotMatch(modal, /sync-metadata|stream-cursor|last-synced-at|reset-sync-cursor/);
    assert.match(modal, /data-role="cancel-new-mapping"/);
    assert.match(modal, /data-role="save-new-mapping"/);
    assert.match(modal, /data-role="mapping-root-picker"/);
    assert.match(modal, /data-role="mapping-root-picker-trigger"[\s\S]*?role="combobox"/);
    assert.match(modal, /data-role="mapping-root-picker-option"/);
    assert.match(modal, /\$rootOptionMapped \? 'disabled' : ''/);
    assert.match(modal, /class="vec-mapping-status-icon"/);
    assert.match(modal, /This Store Group already has a mapping\./);
    assert.doesNotMatch(modal, />\s*Zamknij\s*</);
});

test('popup behavior uses Magento modal and refreshes code-name options through CSRF-protected POST', () => {
    const script = read('view/adminhtml/web/js/category-tree-new-mapping.js');
    const mapping = read('view/adminhtml/web/js/category-tree-mapping.js');

    assert.match(script, /Magento_Ui\/js\/modal\/modal/);
    assert.doesNotMatch(script, /function listen\(/);
    assert.match(script, /scope\.listen\(document, 'click'/);
    assert.match(mapping, /newMapping\.init\(config, element, scope\)/);
    assert.match(script, /modalClass: 'vec-new-mapping-modal-shell'/);
    assert.match(script, /buttons: \[\]/);
    assert.match(script, /type: 'POST'/);
    assert.equal((script.match(/timeout: 60000/g) || []).length, 1);
    assert.match(script, /form_key: config\.form_key/);
    assert.match(script, /replaceTreeOptions\(root, select, response\.options/);
    assert.match(script, /label \+ ' · ' \+ code/);
    assert.match(script, /root\.querySelectorAll\('\[data-role="open-edit-mapping"\]'\)/);
    assert.match(script, /treeSelect\.disabled = editing/);
    assert.match(script, /rootSelect\.disabled = editing/);
    assert.match(script, /function initRootPicker\(select, scope\)/);
    assert.match(script, /option\.getAttribute\('aria-disabled'\) === 'true'/);
    assert.match(script, /event\.key === 'ArrowDown'/);
    assert.match(script, /rootSelectPicker\.setDisabled\(editing\)/);
    assert.match(script, /rootSelectPicker && !rootSelectPicker\.validate\(\)/);
    assert.match(script, /refreshButton\.hidden = editing/);
    assert.doesNotMatch(script, /sync_cursor_reset|resetCursorButton|activeMapping/);
    assert.match(script, /confirmDeleteButton\.click\(\)/);
    assert.match(mapping, /newMapping\.init\(config, element, scope\)/);
    assert.equal((mapping.match(/timeout: typeof timeout === 'number' \? timeout : 60000/g) || []).length, 2);
});

test('modal styling follows the bounded Ergonode workspace primitives', () => {
    const styles = read('view/adminhtml/web/css/category-tree-mapping.css');

    assert.doesNotMatch(styles, /\.vec-new-mapping-icon\b/);
    assert.match(styles, /\.vec-new-mapping-secondary\s*\{[\s\S]*?border: 1px dashed/);
    assert.match(styles, /\.vec-configuration-options > summary/);
    assert.match(styles, /\.vec-tree-options-refresh\[hidden\]\s*\{[\s\S]*?display: none/);
    assert.doesNotMatch(styles, /\.vec-new-mapping-metadata/);
    assert.doesNotMatch(styles, /\.vec-settings-sync-/);
    assert.match(styles, /\.vec-new-mapping-modal-shell \.modal-inner-wrap\s*\{/);
    assert.match(styles, /\.vec-new-mapping-field\s*,[\s\S]*?border-radius: 9px/);
    assert.match(styles, /\.vec-new-mapping-actions\s*\{[\s\S]*?justify-content: flex-end/);
    assert.match(styles, /\.vec-new-mapping-delete\[hidden\]\s*\{[\s\S]*?display: none/);
    assert.match(styles, /\.vec-root-picker-trigger\s*\{[\s\S]*?display: flex/);
    assert.match(styles, /\.vec-root-picker-option\[aria-disabled='true'\]\s*\{[\s\S]*?cursor: not-allowed/);
    assert.match(styles, /\.vec-root-picker-option:hover \.vec-mapping-hint,[\s\S]*?visibility: visible/);
    assert.doesNotMatch(styles, /\.vec-settings-form\s*\{/);
});
