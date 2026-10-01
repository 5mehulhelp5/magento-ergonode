'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {modulePath} = require('./module-paths.cjs');

const adminUiRoot = path.resolve(__dirname, '../..');
const attributeRoot = adminUiRoot;
const categoryRoot = modulePath('CategoryAdminUi');
const languageRoot = modulePath('LanguageAdminUi');
const templateRoot = modulePath('TemplateAdminUi');

function read(root, relativePath) {
    return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

const sourceStateActions = read(
    adminUiRoot,
    'view/adminhtml/templates/entity/source-state-actions.phtml'
);
const mappingActions = read(
    attributeRoot,
    'view/adminhtml/templates/mapping/actions.phtml'
);
const mappingInit = read(
    attributeRoot,
    'view/adminhtml/templates/mapping/init.phtml'
);
function readMappingTemplate(relativePath) {
    const component = relativePath.includes('/option/')
        ? 'Ergonode_CoreAdminUi/js/option-mapping'
        : 'Ergonode_CoreAdminUi/js/attribute-mapping';
    const renderedInit = mappingInit.replace(
        /<\?= \$escaper->escapeJs\(\$moduleName\) \?>/g,
        component
    );

    return read(attributeRoot, relativePath)
        .replace(
            /<\?= \/\* @noEscape \*\/ \$sourceStateActionsHtml \?>/g,
            sourceStateActions
        )
        .replace(/<\?= \/\* @noEscape \*\/ \$mappingActionsHtml \?>/g, mappingActions)
        .replace(/<\?= \/\* @noEscape \*\/ \$mappingInitHtml \?>/g, renderedInit);
}

const attribute = read(attributeRoot, 'view/adminhtml/web/js/attribute-mapping.js');
const option = read(attributeRoot, 'view/adminhtml/web/js/option-mapping.js');
const template = read(templateRoot, 'view/adminhtml/web/js/template-admin.js');
const category = read(categoryRoot, 'view/adminhtml/web/js/category-tree-mapping.js');
const language = read(languageRoot, 'view/adminhtml/web/js/language-mapping.js');
const consumers = [attribute, option, template];

test('toolkit is split by responsibility and does not expose a catch-all utils module', () => {
    [
        'workspace.js',
        'workspace-context.js',
        'text.js',
        'mapping-elements.js',
        'messages.js',
        'buttons.js',
        'visibility-toggle.js',
        'request.js',
        'search.js',
        'dirty-state.js',
        'mapping-requirements.js',
        'drag-drop.js',
        'counters.js',
        'source-bulk-transfer.js'
    ].forEach((file) => {
        assert.equal(
            fs.existsSync(path.join(adminUiRoot, 'view/adminhtml/web/js', file)),
            true,
            `${file} should exist`
        );
    });
    assert.equal(fs.existsSync(path.join(adminUiRoot, 'view/adminhtml/web/js/utils.js')), false);
});

test('mapping requirements are a shared state mechanism for mapping workspaces', () => {
    const requirements = read(adminUiRoot, 'view/adminhtml/web/js/mapping-requirements.js');
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const languageScript = read(languageRoot, 'view/adminhtml/web/js/language-mapping.js');

    assert.match(requirements, /data-mapping-required="true"/);
    assert.match(requirements, /function isCompleteRowForCard/);
    assert.match(requirements, /slots\.every/);
    assert.match(requirements, /is-requirement-missing/);
    assert.doesNotMatch(requirements, /messageSelector|mapping-requirements-message/);
    assert.match(styles, /\.veui-mapping-requirement-control/);
    assert.match(styles, /\.veui-mapping-requirement-tooltip/);
    assert.match(styles, /\.veui-mapping-requirement-control:hover \.veui-mapping-requirement-tooltip/);
    assert.match(styles, /\.veui-mapping-requirement-control:focus \.veui-mapping-requirement-tooltip/);
    assert.doesNotMatch(styles, /cursor:\s*help/);
    assert.match(styles, /\.veui-required-badge/);
    assert.match(languageScript, /Ergonode_CoreAdminUi\/js\/mapping-requirements/);
    assert.match(languageScript, /mappingRequirements\.create\(root,\s*\{\s*rowSelector: \'\[data-role="mapping-row"\]\[data-mapping-active="true"\]\'/);
});

test('attribute, option and template share lifecycle and common interaction modules', () => {
    consumers.forEach((source) => {
        [
            'Ergonode_CoreAdminUi/js/workspace',
            'Ergonode_CoreAdminUi/js/workspace-context',
            'Ergonode_CoreAdminUi/js/buttons',
            'Ergonode_CoreAdminUi/js/request',
            'Ergonode_CoreAdminUi/js/search',
            'Ergonode_CoreAdminUi/js/drag-drop'
        ].forEach((moduleName) => assert.match(source, new RegExp(moduleName)));
        assert.match(source, /workspace\.mount\(element,/);
    });
    consumers.forEach((source) => {
        assert.doesNotMatch(source, /Ergonode_CoreAdminUi\/js\/counters/);
    });
});

test('compatible mapping screens use the shared source bulk-transfer mechanism', () => {
    const bulkTransfer = read(adminUiRoot, 'view/adminhtml/web/js/source-bulk-transfer.js');
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
    const story = read(adminUiRoot, 'Test/Storybook/MappingWorkspace.stories.js');

    [attribute, option, language].forEach((source) => {
        assert.match(source, /Ergonode_CoreAdminUi\/js\/source-bulk-transfer/);
        assert.match(source, /sourceBulkTransfer\.bind\(/);
    });
    assert.match(bulkTransfer, /data-role.*source-bulk-select/);
    assert.match(bulkTransfer, /data-role.*source-bulk-select-all/);
    assert.match(bulkTransfer, /Ergonode_CoreAdminUi\/js\/entity-options/);
    assert.match(bulkTransfer, /role: 'source-bulk-add-to-mapping'/);
    assert.match(bulkTransfer, /label: \$t\('Dodaj do mapowania'\)/);
    assert.match(bulkTransfer, /\.veui-source-options \.veui-entity-options-menu/);
    assert.match(bulkTransfer, /optionsMenu\.insertBefore\(addToMapping, optionsMenu\.firstChild\)/);
    assert.match(bulkTransfer, /role: 'source-bulk-select-all'/);
    assert.match(bulkTransfer, /optionsMenu\.insertBefore\(createSelectAllAction\(\), addToMapping\)/);
    assert.doesNotMatch(bulkTransfer, /selectAll\.(?:checked|indeterminate)/);
    assert.match(bulkTransfer, /selectedCards\(panel, options\)\.reverse\(\)/);
    assert.doesNotMatch(bulkTransfer, /mapping-row|pair-slot|save|autosave/);
    assert.doesNotMatch(styles, /arrow-alt-circle-(?:left|right)\.svg/);
    assert.match(story, /mountMappingInteractions\(root\)/);
    assert.match(story, /ZaznaczanieIPrzenoszenieWieluElementow/);
    assert.match(story, /KontraktTransferuDlaWspolnychWidokow/);
});

test('consumers do not retain local copies of extracted mechanisms', () => {
    consumers.forEach((source) => {
        assert.doesNotMatch(source, /function (?:normalize|escapeHtml|showErrorPopup|showGlobalMessage|showMessage|setBusy|postJson|initFullView|initSortControls)\(/);
        assert.doesNotMatch(source, /new URLSearchParams|window\.fetch\(|\$\.ajax\(/);
    });
    assert.doesNotMatch(template, /\$root\.on\(/);
    [attribute, option].forEach((source) => {
        assert.match(source, /Ergonode_CoreAdminUi\/js\/mapping-elements/);
        assert.doesNotMatch(source, /function (?:sourceLabel|typeBadgeHtml|getSlotPayload|getCardPayload|isCardActionTarget)\(/);
    });
});

test('every inline message consumer delegates timing and dismissal to the shared component', () => {
    const sharedMessages = read(adminUiRoot, 'view/adminhtml/web/js/messages.js');
    const sharedContext = read(adminUiRoot, 'view/adminhtml/web/js/workspace-context.js');

    [attribute, option, category, language, template].forEach((source) => {
        assert.match(source, /Ergonode_CoreAdminUi\/js\/workspace-context/);
        assert.doesNotMatch(source, /function (?:showMessage|hideMessage|showGlobalMessage)\(/);
    });
    assert.match(sharedContext, /Ergonode_CoreAdminUi\/js\/messages/);
    assert.match(sharedContext, /Ergonode_CoreAdminUi\/js\/dirty-state/);
    assert.match(sharedMessages, /defaultAutoHideDelay\s*=\s*30000/);
    assert.match(sharedMessages, /data-role="message-close"/);
    assert.match(sharedMessages, /addEventListener\('click', hide\)/);
});

test('delegated handlers cover dynamic content and keyboard activation', () => {
    consumers.forEach((source) => assert.match(source, /scope\.delegate\(/));
    consumers.forEach((source) => {
        assert.match(source, /scope\.delegate\('keydown'/);
        assert.match(source, /event\.key !== 'Enter'/);
        assert.match(source, /event\.key !== ' '/);
    });

    const attributeTemplate = readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml');
    const optionTemplate = readMappingTemplate('view/adminhtml/templates/option/mapping.phtml');

    [attributeTemplate, optionTemplate].forEach((markup) => {
        assert.match(markup, /data-role="entity-card"[\s\S]*?role="group"[\s\S]*?tabindex="0"/);
    });
    assert.match(template, /role: 'group'/);
    assert.match(template, /tabindex: '0'/);
});

test('mapping workspaces do not expose the removed full view mechanism', () => {
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const templates = [
        readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
        readMappingTemplate('view/adminhtml/templates/option/mapping.phtml')
    ];

    [attribute, option].forEach((source) => {
        assert.doesNotMatch(source, /full-view|fullView|toggle-full-view/);
    });
    templates.forEach((markup) => {
        assert.doesNotMatch(markup, /full-view|Full View/);
    });
    assert.doesNotMatch(styles, /full-view|is-full-view/);
    assert.equal(fs.existsSync(path.join(adminUiRoot, 'view/adminhtml/web/js/full-view.js')), false);
});

test('new mapping hint stays hidden until the mapping panel becomes a drop target', () => {
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');

    assert.match(styles, /\.vea-create-zone\s*\{[\s\S]*?display: none;/);
    assert.match(
        styles,
        /\.vea-mapping\.is-dragging-to-mapping \.vea-mapping-panel \.vea-create-zone,[\s\S]*?\.vea-mapping-panel\.is-create-drop-ready \.vea-create-zone,[\s\S]*?\.vea-mapping-panel\.is-create-drop-active \.vea-create-zone\s*\{[\s\S]*?display: flex;/
    );
});

test('auto-match buttons use the shared 14px broken-link icon and standard white button size', () => {
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const icon = read(adminUiRoot, 'view/adminhtml/web/images/auto-match.svg');
    const templates = [
        readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
        readMappingTemplate('view/adminhtml/templates/option/mapping.phtml'),
        read(languageRoot, 'view/adminhtml/templates/language/mapping.phtml')
    ];

    assert.match(icon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(icon, /<path fill="#000" d="M22\.634 7\.967/);
    templates.forEach((markup) => {
        assert.match(markup, /<span class="vea-auto-match-icon" aria-hidden="true"><\/span>/);
        assert.doesNotMatch(markup, /<svg class="vea-auto-match-icon"/);
    });
    assert.match(styles, /--vea-c-000000: #000000/);
    assert.match(styles, /\.vea-auto-match-icon,\s*\.vea-link-indicator > span\s*\{[\s\S]*?height: 14px[\s\S]*?mask: url\('\.\.\/images\/auto-match\.svg'\) center \/ 14px 14px no-repeat[\s\S]*?width: 14px/);
    assert.match(styles, /\.veui-button\.vea-auto-match[\s\S]*?background: var\(--veui-surface\)[\s\S]*?color: var\(--vea-c-000000\)/);
    assert.match(styles, /\.veui-button\.vea-auto-match:hover,[\s\S]*?background: var\(--veui-surface-hover\)[\s\S]*?border-color: var\(--veui-border-strong\)[\s\S]*?box-shadow: 0 1px 2px rgba\(15, 23, 42, 0\.03\)[\s\S]*?color: var\(--vea-c-000000\)/);
    assert.doesNotMatch(styles, /\.veui-button\.vea-auto-match\s*\{[^}]*?(?:gap|padding):/);
    assert.match(styles, /@keyframes vea-auto-pulse[\s\S]*?transform: scale\(1\.08\)/);
});

test('mapping link indicators reuse the shared auto-match icon', () => {
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');

    assert.match(styles, /\.vea-auto-match-icon,\s*\.vea-link-indicator > span\s*\{[\s\S]*?height: 14px[\s\S]*?mask: url\('\.\.\/images\/auto-match\.svg'\) center \/ 14px 14px no-repeat[\s\S]*?width: 14px/);
    assert.match(styles, /\.vea-pair-row\s*\{[\s\S]*?gap: 8px;[\s\S]*?grid-template-columns: minmax\(0, 1fr\) 24px minmax\(0, 1fr\)/);
    assert.match(styles, /\.vea-link-indicator\s*\{[\s\S]*?justify-content: center;[\s\S]*?width: 24px/);
    assert.doesNotMatch(styles, /\.vea-link-indicator::(?:before|after)/);
    assert.doesNotMatch(styles, /\.vea-link-indicator span::(?:before|after)/);
});

test('attribute and option mapping toolbars do not expose redundant back actions', () => {
    const attributeTemplate = readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml');
    const optionTemplate = readMappingTemplate('view/adminhtml/templates/option/mapping.phtml');

    assert.doesNotMatch(attributeTemplate, /vea-back-icon|vea-back-action|data-role="mapping-back"/);
    assert.doesNotMatch(optionTemplate, /vea-back-icon|data-role="mapping-back"/);
});

test('refresh controls use the shared 14px cloud download icon', () => {
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const actionStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-actions.css');
    const icon = read(adminUiRoot, 'view/adminhtml/web/images/refresh-ergonode.svg');
    const attributeTemplate = readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml');
    const optionTemplate = readMappingTemplate('view/adminhtml/templates/option/mapping.phtml');
    const languageSourceOptions = read(languageRoot, 'view/adminhtml/web/js/language-source-options.js');
    const attributeSourceTools = (attributeTemplate.match(
        /<div class="veui-panel-head-tools vea-side-tools">[\s\S]*?<\/details>/
    ) || [''])[0];
    const optionSourceTools = (optionTemplate.match(
        /<div class="veui-panel-head-tools vea-side-tools">[\s\S]*?<\/details>/
    ) || [''])[0];
    const attributeSourcePanelStart = attributeTemplate.indexOf('data-source-panel="ergo"');
    const attributeSourceToolsStart = attributeTemplate.indexOf('class="veui-panel-head-tools vea-side-tools"');
    const optionSourcePanelStart = optionTemplate.indexOf('data-source-panel="ergo"');
    const optionSourceToolsStart = optionTemplate.indexOf('class="veui-panel-head-tools vea-side-tools"');

    assert.match(icon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(icon, /<path fill="#000" d="M18\.348 7\.23/);
    assert.match(styles, /\.vea-refresh-ergonode-icon\s*\{[\s\S]*?height: 14px[\s\S]*?mask: url\('\.\.\/images\/refresh-ergonode\.svg'\) center \/ 14px 14px no-repeat[\s\S]*?width: 14px/);
    assert.match(actionStyles, /\.veui-refresh-ergonode-icon\s*\{[\s\S]*?height: 14px[\s\S]*?mask: url\('\.\.\/images\/refresh-ergonode\.svg'\) center \/ 14px 14px no-repeat[\s\S]*?width: 14px/);
    assert.equal((attributeTemplate.match(/vea-refresh-ergonode-icon/g) || []).length, 2);
    assert.equal((optionTemplate.match(/vea-refresh-ergonode-icon/g) || []).length, 1);
    [attributeSourceTools, optionSourceTools].forEach((sourceTools) => {
        assert.match(sourceTools, /class="veui-entity-options-action vea-refresh-ergonode"/);
        assert.match(sourceTools, /data-role="refresh-ergonode"[\s\S]*class="vea-refresh-ergonode-icon"[\s\S]*Odśwież/);
        assert.ok(sourceTools.indexOf('data-role="refresh-ergonode"') < sourceTools.indexOf('data-role="attribute-sort-direction"'));
    });
    assert.doesNotMatch(
        attributeTemplate.slice(attributeSourcePanelStart, attributeSourceToolsStart),
        /data-role="refresh-ergonode"/
    );
    assert.doesNotMatch(
        optionTemplate.slice(optionSourcePanelStart, optionSourceToolsStart),
        /data-role="refresh-ergonode"/
    );
    assert.match(languageSourceOptions, /iconClass: 'vea-refresh-ergonode-icon'/);
    assert.doesNotMatch(styles, /\.vea-refresh-ergonode > span(?:::\w+)?/);
    assert.doesNotMatch(attributeTemplate, /vea-attribute-empty-refresh-icon/);
    assert.doesNotMatch(languageSourceOptions, /vel-empty-refresh-icon/);
    const templateConsumer = read(
        modulePath('TemplateConsumerAdminUi'),
        'view/adminhtml/web/js/template-consumer.js'
    );
    const templateStyles = read(templateRoot, 'view/adminhtml/web/css/template-admin.css');

    assert.match(templateConsumer, /role: 'refresh-templates',[\s\S]*?iconClass: 'vea-refresh-ergonode-icon'/);
    assert.doesNotMatch(templateStyles, /\.vet-icon-refresh:before/);
});

test('Ergonode creation controls use the shared 14px cloud upload icon', () => {
    const actionStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-actions.css');
    const icon = read(adminUiRoot, 'view/adminhtml/web/images/create-ergonode.svg');

    assert.match(icon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(icon, /<path fill="#000" d="M17\.974,7\.146/);
    assert.match(actionStyles, /\.veui-create-ergonode-icon\s*\{[\s\S]*?background: currentColor[\s\S]*?height: 14px[\s\S]*?mask: url\('\.\.\/images\/create-ergonode\.svg'\) center \/ 14px 14px no-repeat[\s\S]*?width: 14px/);
    assert.match(actionStyles, /\.veui-button-primary \.veui-create-ergonode-icon\s*\{[\s\S]*?background: currentColor/);
});

test('Ergonode synchronization controls use shared 14px cloud action icons', () => {
    const actionStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-actions.css');
    const syncIcon = read(adminUiRoot, 'view/adminhtml/web/images/sync-ergonode.svg');
    const resetIcon = read(adminUiRoot, 'view/adminhtml/web/images/reset-cursor.svg');

    assert.match(syncIcon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(resetIcon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.doesNotMatch(syncIcon, /\bid=|data-name=/);
    assert.doesNotMatch(resetIcon, /\bid=|data-name=/);
    assert.match(actionStyles, /\.veui-sync-ergonode-icon\s*\{[\s\S]*?background: currentColor[\s\S]*?height: 14px[\s\S]*?mask: url\('\.\.\/images\/sync-ergonode\.svg'\) center \/ 14px 14px no-repeat[\s\S]*?width: 14px/);
    assert.match(actionStyles, /\.veui-reset-cursor-icon\s*\{[\s\S]*?background: currentColor[\s\S]*?height: 14px[\s\S]*?mask: url\('\.\.\/images\/reset-cursor\.svg'\) center \/ 14px 14px no-repeat[\s\S]*?width: 14px/);
});

test('save controls use the shared 14px analytics icon', () => {
    const icon = read(adminUiRoot, 'view/adminhtml/web/images/save.svg');
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const categoryStyles = read(categoryRoot, 'view/adminhtml/web/css/category-tree-mapping.css');
    const mappingTemplates = [
        readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
        readMappingTemplate('view/adminhtml/templates/option/mapping.phtml')
    ];
    const languageTemplate = read(languageRoot, 'view/adminhtml/templates/language/mapping.phtml');
    const categoryTemplate = read(categoryRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml');

    assert.match(icon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
    assert.match(icon, /<path fill="#fff" d="M6 15c\.553/);
    mappingTemplates.forEach((markup) => {
        assert.match(markup, /getViewFileUrl\('Ergonode_CoreAdminUi::images\/save\.svg'\)/);
        assert.match(markup, /<img class="vea-save-icon"[\s\S]*?width="14"[\s\S]*?height="14"/);
    });
    assert.doesNotMatch(languageTemplate, /data-role="save-mapping"|images\/save\.svg/);
    assert.doesNotMatch(languageTemplate, /data-role="autosave-status"/);
    assert.match(languageTemplate, /data-role="autosave-region"[\s\S]*?aria-busy="false"/);
    assert.match(categoryTemplate, /getViewFileUrl\('Ergonode_CoreAdminUi::images\/save\.svg'\)/);
    assert.match(categoryTemplate, /<img class="vec-icon vec-icon-save"[\s\S]*?width="14"[\s\S]*?height="14"/);
    const templateAdmin = read(templateRoot, 'view/adminhtml/templates/template/index.phtml');

    assert.doesNotMatch(templateAdmin, /images\/save\.svg|vet-icon-save|data-role="save-template-mapping"/);
    assert.match(templateAdmin, /data-role="autosave-region"[\s\S]*?aria-busy="false"/);
    assert.match(styles, /\.vea-save-icon\s*\{[\s\S]*?height: 14px[\s\S]*?width: 14px/);
    assert.match(categoryStyles, /\.vec-icon-save\s*\{[\s\S]*?height: 14px[\s\S]*?width: 14px/);
    assert.doesNotMatch(styles, /\.vea-save-action > span/);
    assert.doesNotMatch(styles, /\.vea-unsaved-state/);
    assert.doesNotMatch(categoryStyles, /\.vec-icon-save:(?:before|after)/);
});

test('side-panel sorting uses an options menu with two toggles and shared 14px icons', () => {
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const workspaceStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
    const search = read(adminUiRoot, 'view/adminhtml/web/js/search.js');
    const upIcon = read(adminUiRoot, 'view/adminhtml/web/images/sort-amount-up.svg');
    const downIcon = read(adminUiRoot, 'view/adminhtml/web/images/sort-amount-down.svg');
    const templates = [
        readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
        readMappingTemplate('view/adminhtml/templates/option/mapping.phtml')
    ];

    [upIcon, downIcon].forEach((icon) => {
        assert.match(icon, /<svg[^>]*width="14"[^>]*height="14"[^>]*viewBox="0 0 24 24"/);
        assert.match(icon, /<path fill="#000"/);
    });
    templates.forEach((markup) => {
        const controls = markup.match(
            /<div class="veui-panel-head-tools vea-side-tools">[\s\S]*?<\/details>/g
        ) || [];

        assert.equal(controls.length, 2);
        controls.forEach((control) => {
            assert.match(control, /<details class="veui-entity-options veui-source-options"[\s\S]*?data-role="entity-options"/);
            assert.equal((control.match(/data-role="attribute-sort-direction"/g) || []).length, 1);
            assert.equal((control.match(/data-role="attribute-sort-toggle"/g) || []).length, 1);
            assert.ok(control.indexOf('data-role="attribute-sort-direction"') < control.indexOf('data-role="attribute-sort-toggle"'));
            assert.match(control, /data-role="attribute-sort-direction-label"[\s\S]*?Góra/);
            assert.match(control, /data-role="attribute-sort-label"[\s\S]*?Nazwa/);
            assert.match(control, /veui-sort-field-icon/);
        });
        assert.doesNotMatch(markup, /vea-sort-(?:control|options|toggle|direction)/);
    });
    assert.match(sourceStateActions, /data-role="visibility-toggle"/);
    assert.match(sourceStateActions, /data-role="attribute-sort-direction"/);
    [
        read(attributeRoot, 'view/adminhtml/templates/attribute/mapping.phtml'),
        read(attributeRoot, 'view/adminhtml/templates/option/mapping.phtml')
    ].forEach((markup) => {
        assert.match(markup, /Ergonode_CoreAdminUi::entity\/source-state-actions\.phtml/);
        assert.equal((markup.match(/\$sourceStateActionsHtml/g) || []).length, 3);
    });
    assert.match(workspaceStyles, /\.veui-tools-inline\s*\{[\s\S]*?align-items: center;[\s\S]*?display: flex;/);
    assert.match(workspaceStyles, /\.veui-tools-inline > \.veui-search\s*\{[\s\S]*?flex: 1 1 auto;[\s\S]*?min-width: 0;[\s\S]*?order: 0;/);
    assert.match(workspaceStyles, /\.veui-tools-inline > \.veui-source-options\s*\{[\s\S]*?margin-left: auto;[\s\S]*?order: 1;/);
    assert.doesNotMatch(styles, /\.vea-(?:side|middle)-tools\s*\{[^}]*display:/);
    assert.doesNotMatch(styles, /\.vea-(?:mapping|sort)-options/);
    assert.match(workspaceStyles, /\.veui-source-options > summary,[\s\S]*?height: 34px[\s\S]*?width: 34px/);
    assert.match(workspaceStyles, /\.veui-entity-options-menu > \.veui-entity-options-action\s*\{[\s\S]*?min-height: 30px[\s\S]*?width: 100%/);
    assert.match(workspaceStyles, /\.veui-sort-field-icon::before\s*\{[\s\S]*?content: 'A'/);
    assert.match(workspaceStyles, /\.veui-sort-direction-icon\s*\{[\s\S]*?url\('\.\.\/images\/sort-amount-up\.svg'\)[\s\S]*?14px 14px/);
    assert.match(workspaceStyles, /\[data-direction='desc'\] \.veui-sort-direction-icon\s*\{[\s\S]*?url\('\.\.\/images\/sort-amount-down\.svg'\)/);
    assert.doesNotMatch(styles, /data:image\/svg\+xml[^\n]*vea-sort/);
    assert.match(search, /function renderDirection\(\)/);
    assert.match(search, /config\.directionLabelSelector/);
    assert.match(search, /config\.descOptionLabel : config\.ascOptionLabel/);
});

test('mapping searches and source options share one global inline toolbar', () => {
    const workspaceStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
    const languageTemplate = read(languageRoot, 'view/adminhtml/templates/language/mapping.phtml');
    const languageStyles = read(languageRoot, 'view/adminhtml/web/css/language-mapping.css');
    const languageOptions = read(languageRoot, 'view/adminhtml/web/js/language-source-options.js');

    assert.match(workspaceStyles, /\.veui-tools-inline > \.veui-search\s*\{[\s\S]*?flex: 1 1 auto/);
    assert.match(workspaceStyles, /\.veui-tools-inline > \.veui-source-options\s*\{[\s\S]*?margin-left: auto/);
    assert.match(workspaceStyles, /\.veui-source-options > summary,[\s\S]*?height: 34px[\s\S]*?width: 34px/);
    assert.equal(
        (languageTemplate.match(/veui-tools veui-tools-inline vea-side-tools vel-side-tools/g) || []).length,
        2
    );
    assert.match(languageTemplate, /veui-entity-options veui-source-options vel-source-options/);
    assert.match(languageOptions, /menu\.classList\.add\('veui-source-options'\)/);
    assert.doesNotMatch(languageStyles, /\.vel-side-tools\s*\{|\.vel-source-options > summary/);
    const templateTemplate = read(templateRoot, 'view/adminhtml/templates/template/index.phtml');
    const templateStyles = read(templateRoot, 'view/adminhtml/web/css/template-admin.css');
    const templateOptions = read(templateRoot, 'view/adminhtml/web/js/template-source-options.js');

    assert.equal(
        (templateTemplate.match(/veui-panel-head-tools vea-side-tools vet-side-tools/g) || []).length,
        2
    );
    assert.match(templateTemplate, /veui-entity-options veui-source-options vet-source-options/);
    assert.match(templateOptions, /menu\.classList\.add\('veui-source-options'\)/);
    assert.doesNotMatch(templateStyles, /\.vet-side-tools\s*\{|\.vet-source-options > summary/);
});

test('Ergonode toolbar actions share the mapping save button size', () => {
    const workspaceStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
    const mappingStyles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const categoryStyles = read(categoryRoot, 'view/adminhtml/web/css/category-tree-mapping.css');

    assert.match(workspaceStyles, /\.veui-toolbar \.veui-button,\s*\.veui-button-toolbar\s*\{[\s\S]*?border-radius: 8\.5px[\s\S]*?font-size: 11\.05px[\s\S]*?height: 33\.15px[\s\S]*?padding: 0 11\.9px/);
    assert.match(workspaceStyles, /\.veui-visibility-toggle\s*\{[\s\S]*?appearance: none/);
    assert.doesNotMatch(categoryStyles, /\.vec-button(?:-primary|-secondary)?\s*\{/);
    assert.doesNotMatch(mappingStyles, /\.vea-(?:filter-toggle|global-active-filter)/);
    assert.doesNotMatch(mappingStyles, /\.vea-viewbar \.veui-button/);
});

test('Ergonode toolbar primary buttons use accessible Magento orange without colored glow rings', () => {
    const workspaceStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css');

    assert.match(workspaceStyles, /--veui-magento-orange: #c24100/);
    assert.match(workspaceStyles, /--veui-magento-orange-soft: rgba\(235, 82, 2, \.1\)/);
    [
        ['344054', '#344054'],
        ['98a2b3', '#98a2b3'],
        ['c24100', '#c24100'],
        ['cbd5e1', '#cbd5e1'],
        ['d64700', '#d64700'],
        ['d94a00', '#d94a00'],
        ['eb5202', '#eb5202'],
        ['f8fafc', '#f8fafc'],
        ['ffffff', '#ffffff']
    ].forEach(([token, value]) => {
        assert.match(workspaceStyles, new RegExp(`--veui-c-${token}: ${value}`));
    });
    assert.match(workspaceStyles, /\.veui-button:hover,[\s\S]*?background: var\(--veui-c-f8fafc\)[\s\S]*?border-color: var\(--veui-c-cbd5e1\)[\s\S]*?box-shadow: 0 1px 2px rgba\(15, 23, 42, 0\.03\)[\s\S]*?color: var\(--veui-c-344054\)/);
    assert.match(workspaceStyles, /\.veui-button-primary\s*\{[\s\S]*?background: var\(--veui-magento-orange, var\(--veui-c-eb5202\)\)[\s\S]*?border-color: var\(--veui-magento-orange-border, var\(--veui-c-d64700\)\)[\s\S]*?color: var\(--veui-c-ffffff\)/);
    assert.match(workspaceStyles, /\.veui-button-primary:hover,[\s\S]*?background: var\(--veui-magento-orange-hover, var\(--veui-c-d94a00\)\)[\s\S]*?border-color: var\(--veui-magento-orange-hover-border, var\(--veui-c-c24100\)\)[\s\S]*?box-shadow: 0 1px 2px rgba\(15, 23, 42, 0\.03\)[\s\S]*?color: var\(--veui-c-ffffff\)/);
    assert.match(workspaceStyles, /\.veui-button:focus-visible\s*\{[\s\S]*?outline: 2px solid var\(--veui-c-98a2b3\)[\s\S]*?outline-offset: 2px/);
    assert.doesNotMatch(workspaceStyles, /\.veui-button(?:-primary)?:(?:hover|focus)[^{]*\{[^}]*box-shadow: 0 0 0 3px/);
});

test('mapping visibility uses one neutral pressed-state component with dedicated hints', () => {
    const workspaceStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const attributeTemplate = readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml');
    const optionTemplate = readMappingTemplate('view/adminhtml/templates/option/mapping.phtml');
    const categoryTemplate = read(categoryRoot, 'view/adminhtml/templates/category-tree-mapping/index.phtml');
    const categoryScript = read(categoryRoot, 'view/adminhtml/web/js/category-tree-mapping.js');
    const languageTemplate = read(languageRoot, 'view/adminhtml/templates/language/mapping.phtml');
    const languageSourceOptions = read(languageRoot, 'view/adminhtml/web/js/language-source-options.js');
    const languageScript = read(languageRoot, 'view/adminhtml/web/js/language-mapping.js');
    const cardToggleRule = (styles.match(/\.vea-card-toggle\s*\{[^}]+\}/g) || [])
        .find((rule) => rule.includes('appearance: none')) || '';
    const disabledCardToggleRule = styles.match(/\.vea-card-toggle:disabled\s*\{[^}]+\}/)?.[0] || '';

    assert.match(cardToggleRule, /color: var\(--vea-subtle\)/);
    assert.doesNotMatch(cardToggleRule, /color: #000000/);
    assert.doesNotMatch(disabledCardToggleRule, /color:/);
    assert.match(styles, /\.vea-card-toggle > span\[aria-hidden='true'\]\s*\{[\s\S]*?height: 14px[\s\S]*?width: 14px/);
    assert.doesNotMatch(styles, /--vea-eye-(?:active|inactive)-icon/);
    assert.match(workspaceStyles, /--veui-visibility-on-icon:[\s\S]*?fill-rule='evenodd'[\s\S]*?clip-rule='evenodd'/);
    assert.match(workspaceStyles, /--veui-visibility-off-icon:[\s\S]*?mask id='eye-mask'/);
    assert.match(workspaceStyles, /\.veui-visibility-icon\s*\{[\s\S]*?--veui-visibility-off-icon/);
    assert.match(workspaceStyles, /\[aria-pressed='true'\] > \.veui-visibility-icon\s*\{[\s\S]*?--veui-visibility-on-icon/);
    assert.doesNotMatch(styles, /\.vea-card-toggle span::(?:before|after)/);
    assert.match(styles, /\.vea-card-toggle\[aria-pressed='true'\] > span\[aria-hidden='true'\][\s\S]*?--veui-visibility-on-icon/);
    assert.doesNotMatch(styles, /\.vea-filter-(?:toggle|switch)/);

    assert.doesNotMatch(categoryTemplate, /data-role="visibility-toggle"/);
    assert.match(categoryScript, /role: 'visibility-toggle'/);
    assert.match(categoryScript, /className: 'veui-visibility-control'/);
    assert.match(categoryScript, /iconClass: 'veui-visibility-icon'/);
    assert.match(categoryScript, /label: \$t\('Excluded'\)/);
    assert.match(categoryScript, /'data-show-hint': \$t\('Pokaż kategorie pominięte w mapowaniu'\)/);
    assert.match(categoryScript, /'data-hide-hint': \$t\('Ukryj kategorie pominięte w mapowaniu'\)/);
    assert.match(categoryScript, /'aria-pressed': 'false'/);
    assert.doesNotMatch(categoryScript, /global-active-filter|toggle-blocked|vea-filter-toggle|vec-blocked-filter/);
    [attributeTemplate, optionTemplate].forEach((markup) => {
        const toolbar = markup.slice(
            markup.indexOf('class="veui-toolbar vea-viewbar"'),
            markup.indexOf('class="veui-message veui-global-message"')
        );
        const visibilityActions = markup.match(/data-role="visibility-toggle"/g) || [];

        assert.doesNotMatch(toolbar, /data-role="visibility-toggle"/);
        assert.equal(visibilityActions.length, 2);
        assert.match(markup, /class="veui-entity-options-action veui-visibility-control"/);
        assert.match(markup, /class="veui-visibility-icon"[\s\S]*?__\('Excluded'\)/);
    });
    assert.doesNotMatch(languageTemplate, /data-role="visibility-toggle"|vel-store-visibility-toggle/);
    assert.equal((languageTemplate.match(/data-source-options-label=/g) || []).length, 2);
    assert.equal(
        (languageTemplate.match(/veui-entity-options veui-source-options vel-source-options vel-options-placeholder/g) || [])
            .length,
        2
    );
    const sourceOptionsConsumers = [
        languageSourceOptions,
        read(templateRoot, 'view/adminhtml/web/js/template-source-options.js')
    ];
    sourceOptionsConsumers.forEach((sourceOptions) => {
        assert.match(sourceOptions, /role: 'visibility-toggle'[\s\S]*?className: 'veui-visibility-control'/);
        assert.match(sourceOptions, /iconClass: 'veui-visibility-icon'/);
        assert.match(sourceOptions, /'aria-pressed': 'false'[\s\S]*?'data-show-hint'[\s\S]*?'data-hide-hint'/);
    });
    assert.match(languageSourceOptions, /menu\.setAttribute\('data-source-options', source\)/);
    assert.doesNotMatch(
        read(templateRoot, 'view/adminhtml/templates/template/index.phtml'),
        /data-role="visibility-toggle"/
    );
    [attributeTemplate, optionTemplate].forEach((markup) => {
        assert.match(markup, /<button type="button"[\s\S]*?class="vea-card-toggle"[\s\S]*?data-role="attribute-active-toggle"[\s\S]*?aria-pressed=/);
    });
    assert.match(languageTemplate, /<button type="button"[\s\S]*?class="vea-card-toggle"[\s\S]*?data-role="source-active-toggle"[\s\S]*?aria-pressed=/);
    [attribute, option, languageScript].forEach((source) => {
        assert.match(source, /buttons\.(?:isPressed|togglePressed)/);
        assert.doesNotMatch(source, /\.checked/);
    });
    [attribute, option].forEach((source) => {
        assert.match(source, /scope\.delegate\('click', '\[data-role="attribute-active-toggle"\]'/);
        assert.match(source, /scope\.delegate\('click', '\[data-role="visibility-toggle"\]'/);
    });
    assert.match(languageScript, /scope\.delegate\('click', '\[data-role="source-active-toggle"\]'/);
    assert.match(languageScript, /scope\.delegate\('click', '\[data-role="visibility-toggle"\]'/);
});

test('Ergonode column headers reuse the system configuration mark', () => {
    const systemXml = read(adminUiRoot, 'etc/adminhtml/system.xml');
    const moduleStyles = read(adminUiRoot, 'view/adminhtml/web/css/source/_module.less');
    const templates = [
        readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
        readMappingTemplate('view/adminhtml/templates/option/mapping.phtml'),
        read(languageRoot, 'view/adminhtml/templates/language/mapping.phtml'),
        read(templateRoot, 'view/adminhtml/templates/template/index.phtml')
    ];

    assert.match(systemXml, /<tab id="ergonode"[^>]*class="ergonode-tab">/);
    assert.match(moduleStyles, /\.ergonode-tab[\s\S]*?Ergonode_CoreAdminUi::images\/m2_configuration\.svg/);
    templates.forEach((markup) => {
        assert.match(markup, /getViewFileUrl\('Ergonode_CoreAdminUi::images\/m2_configuration\.svg'\)/);
        assert.doesNotMatch(markup, /Ergonode_CoreAdminUi::images\/ergonode-mark\.svg/);
    });
});

test('mapping toolbar uses the same panel surface as the columns', () => {
    const workspaceStyles = read(adminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
    const styles = read(adminUiRoot, 'view/adminhtml/web/css/attribute-mapping.css');
    const templates = [
        readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
        readMappingTemplate('view/adminhtml/templates/option/mapping.phtml')
    ];

    assert.match(workspaceStyles, /\.veui-viewbar,[\s\S]*?\.vea-viewbar\s*\{[\s\S]*?background: rgba\(255, 255, 255, 0\.92\)/);
    assert.match(workspaceStyles, /\.veui-viewbar,[\s\S]*?\.vea-viewbar\s*\{[\s\S]*?border: 1px solid var\(--veui-border\)/);
    assert.match(workspaceStyles, /\.veui-viewbar,[\s\S]*?\.vea-viewbar\s*\{[\s\S]*?border-radius: 12px/);
    assert.match(workspaceStyles, /\.veui-viewbar,[\s\S]*?\.vea-viewbar\s*\{[\s\S]*?height: 68px/);
    assert.doesNotMatch(styles, /\.vea-viewbar\s*\{/);
    assert.match(workspaceStyles, /\.veui-button\s*\{[\s\S]*?border-radius: 6\.8px[\s\S]*?font-size: 9\.35px[\s\S]*?gap: 5\.95px[\s\S]*?height: 25\.5px[\s\S]*?padding: 0 9\.35px/);
    assert.match(workspaceStyles, /\.veui-toolbar \.veui-button,\s*\.veui-button-toolbar\s*\{[\s\S]*?border-radius: 8\.5px[\s\S]*?font-size: 11\.05px[\s\S]*?height: 33\.15px[\s\S]*?padding: 0 11\.9px/);
    assert.match(styles, /\.vea-mapping \.vea-shell\s*\{[\s\S]*?height: calc\(100% - 78px\)/);
    assert.match(
        templates[0],
        /class="veui-entity-options-action veui-split-button-option[\s\S]*?vea-complete-missing"/
    );
    assert.match(
        templates[1],
        /class="veui-entity-options-action veui-split-button-option[\s\S]*?vea-complete-missing"/
    );
});

test('attribute and option mapping share the primary Save and options split button', () => {
    const templates = [
        readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
        readMappingTemplate('view/adminhtml/templates/option/mapping.phtml')
    ];

    templates.forEach((markup) => {
        assert.match(
            markup,
            /class="veui-split-button veui-split-button-align-end veui-split-button-primary"/
        );
        assert.match(markup, /data-role="save-mapping"/);
        assert.match(markup, /class="veui-entity-options veui-split-button-options"/);
        assert.match(markup, /class="veui-entity-options-menu veui-split-button-menu"/);
    });
});

test('x-magento-init entry points and data-role contracts remain stable', () => {
    const cases = [
        [
            readMappingTemplate('view/adminhtml/templates/attribute/mapping.phtml'),
            '#<?= $escaper->escapeJs($workspaceId) ?>',
            'Ergonode_CoreAdminUi/js/attribute-mapping'
        ],
        [
            readMappingTemplate('view/adminhtml/templates/option/mapping.phtml'),
            '#<?= $escaper->escapeJs($workspaceId) ?>',
            'Ergonode_CoreAdminUi/js/option-mapping'
        ],
        [
            read(templateRoot, 'view/adminhtml/templates/template/index.phtml'),
            '#ergonode-template-admin',
            'Ergonode_TemplateAdminUi/js/template-admin'
        ],
        [
            read(modulePath('TemplateConsumerAdminUi'), 'view/adminhtml/templates/template/actions.phtml'),
            '#ergonode-template-admin',
            'Ergonode_TemplateConsumerAdminUi/js/template-consumer'
        ]
    ];

    cases.forEach(([markup, selector, component]) => {
        assert.match(markup, /type="text\/x-magento-init"/);
        assert.equal(markup.includes(`"${selector}"`), true);
        assert.equal(markup.includes(`"${component}"`), true);
    });
});
