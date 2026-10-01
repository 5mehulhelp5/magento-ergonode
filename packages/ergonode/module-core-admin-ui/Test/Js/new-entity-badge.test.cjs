'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const {modulePath} = require('./module-paths.cjs');

function read(moduleName, relativePath) {
    return fs.readFileSync(path.join(modulePath(moduleName), relativePath), 'utf8');
}

function loadMappingElements() {
    const source = read('CoreAdminUi', 'view/adminhtml/web/js/mapping-elements.js');
    let mappingElements;

    vm.runInNewContext(source, {
        define: (dependencies, factory) => {
            mappingElements = factory({
                escapeHtml: (value) => String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;'),
                normalize: (value) => String(value || '').trim().toLowerCase()
            }, (value) => value);
        }
    });

    return mappingElements;
}

test('pending entities and mappings expose precise badges on their target cards', () => {
    const mappingElements = loadMappingElements();
    const rowAttributes = {};
    const slotAttributes = {};
    let inserted = '';
    const target = {
        insertAdjacentHTML: (position, html) => {
            assert.equal(position, 'beforeend');
            inserted = html;
        }
    };
    const slot = {
        setAttribute: (name, value) => { slotAttributes[name] = value; },
        querySelector: (selector) => selector === '.vea-card-subline' ? target : null
    };
    const row = {
        setAttribute: (name, value) => { rowAttributes[name] = value; },
        querySelector: (selector) => selector.includes('data-side="magento"') ? slot : null
    };

    assert.match(mappingElements.metaHtml({
        code: 'color',
        type: 'select',
        scope: 'global',
        pending_create: true
    }), /class="veui-pending-badge"[\s\S]*>DO UTWORZENIA<\/span>/);
    assert.doesNotMatch(mappingElements.metaHtml({
        code: 'color',
        type: 'select',
        scope: 'global',
        pending_create: false
    }), /veui-pending-badge/);

    mappingElements.markNewMapping(row, 'magento');
    assert.equal(rowAttributes['data-new-mapping'], '1');
    assert.equal(slotAttributes['data-new-mapping-element'], '1');
    assert.match(inserted, /data-role="new-mapping-badge"[\s\S]*>DO ZAPISU<\/span>/);
});

test('attribute, option and language flows attach pending mapping badges to a specific side', () => {
    const attribute = read('CoreAdminUi', 'view/adminhtml/web/js/attribute-mapping.js');
    const option = read('CoreAdminUi', 'view/adminhtml/web/js/option-mapping.js');
    const language = read('LanguageAdminUi', 'view/adminhtml/web/js/language-mapping.js');
    const publisher = read('AttributePublisherAdminUi', 'view/adminhtml/web/js/ergonode-attribute-publisher-mapping.js');

    assert.match(attribute, /mappingElements\.markNewMapping\(row, payload\.source\)/);
    assert.match(attribute, /mappingElements\.markNewMapping\(row, missingSide\)/);
    assert.match(attribute, /mappingElements\.markNewMapping\(row, 'magento'\)/);
    assert.match(option, /mappingElements\.markNewMapping\(row, payload\.source\)/);
    assert.match(option, /mappingElements\.markNewMapping\(row, 'magento'\)/);
    assert.match(language, /row\.setAttribute\('data-new-mapping', '1'\)/);
    assert.match(language, /appendNewBadge\(row, payload\.source\)/);
    assert.match(language, /appendNewBadge\(row, 'magento'\)/);
    assert.match(language, /function markMappingsPersisted\([\s\S]*badge\.remove\(\)/);
    assert.match(publisher, /mappingElements\.pendingBadgeHtml\(\)/);
});

test('template mapping badges are based on the persisted snapshot and clear after save', () => {
    const template = read('TemplateAdminUi', 'view/adminhtml/web/js/template-admin.js');
    const styles = read('CoreAdminUi', 'view/adminhtml/web/css/ergonode-workspace.css');

    assert.match(template, /var persistedMappings = \{\}/);
    assert.match(template, /hasOwnProperty\.call\(persistedMappings, template\.code\)/);
    assert.match(template, /function appendPairBadge\(\$side, label, attributes\)/);
    assert.match(template, /\$side\.children\('\.vea-card-subline'\)\.first\(\)/);
    assert.match(template, /appendPairBadge\(\$magento, \$t\('ZAPISYWANIE'\)/);
    assert.match(template, /appendPairBadge\(\$pendingSide, \$t\('SZKIC'\)/);
    assert.match(template, /persistedMappings = Object\.assign\(\{\}, mappings\);[\s\S]*renderAll\(\)/);
    assert.match(styles, /--veui-amber-badge: #f59e0b/);
    assert.match(styles, /\.veui-pending-badge\s*\{[\s\S]*background: var\(--veui-amber-badge\)/);
    assert.doesNotMatch(styles, /\.vea-pair-row > \.veui-pending-badge/);
    assert.doesNotMatch(styles, /\.vet-pair-side > \.veui-pending-badge/);
});
