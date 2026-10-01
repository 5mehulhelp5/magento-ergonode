'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const {moduleRoot: ergonodeModuleRoot} = require('./module-paths.cjs');

const moduleRoot = path.resolve(__dirname, '../..');
const coreAdminUiRoot = ergonodeModuleRoot('CoreAdminUi');
const consumerAdminUiRoot = ergonodeModuleRoot('TemplateConsumerAdminUi');
const template = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/templates/template/index.phtml'),
    'utf8'
);
const layout = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/layout/ergonode_template_index.xml'),
    'utf8'
);
const script = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/template-admin.js'),
    'utf8'
);
const stylesheet = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/css/template-admin.css'),
    'utf8'
);
const languageStylesheet = fs.readFileSync(
    path.join(ergonodeModuleRoot('LanguageAdminUi'), 'view/adminhtml/web/css/language-mapping.css'),
    'utf8'
);
const workspaceStyles = fs.readFileSync(
    path.join(coreAdminUiRoot, 'view/adminhtml/web/css/ergonode-workspace.css'),
    'utf8'
);
const refreshState = fs.readFileSync(
    path.join(consumerAdminUiRoot, 'view/adminhtml/web/js/template-refresh-state.js'),
    'utf8'
);
const autosave = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/template-autosave.js'),
    'utf8'
);
const coreAutosave = fs.readFileSync(
    path.join(coreAdminUiRoot, 'view/adminhtml/web/js/autosave.js'),
    'utf8'
);
const sourceOptions = fs.readFileSync(
    path.join(moduleRoot, 'view/adminhtml/web/js/template-source-options.js'),
    'utf8'
);
const synchronizationActions = fs.readFileSync(
    path.join(coreAdminUiRoot, 'view/adminhtml/templates/synchronization/actions.phtml'),
    'utf8'
);
const block = fs.readFileSync(
    path.join(moduleRoot, 'Block/Adminhtml/Template/Index.php'),
    'utf8'
);
const saveController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Template/SaveMapping.php'),
    'utf8'
);
const indexController = fs.readFileSync(
    path.join(moduleRoot, 'Controller/Adminhtml/Template/Index.php'),
    'utf8'
);
const mappingSaver = fs.readFileSync(
    path.join(ergonodeModuleRoot('Template'), 'Model/Mapping/TemplateAttributeSetMappingSaver.php'),
    'utf8'
);
const uiProvider = fs.readFileSync(
    path.join(moduleRoot, 'Model/TemplateUiProvider.php'),
    'utf8'
);
const visibilitySaver = fs.readFileSync(path.join(moduleRoot, 'Model/MappingVisibility.php'), 'utf8');
const sectionsController = path.join(moduleRoot, 'Controller/Adminhtml/Template/Sections.php');
const sectionsLayout = path.join(moduleRoot, 'view/adminhtml/layout/ergonode_template_sections.xml');
const systemConfig = fs.readFileSync(
    path.join(consumerAdminUiRoot, 'etc/adminhtml/system.xml'),
    'utf8'
);

const consumerScript = fs.readFileSync(path.join(consumerAdminUiRoot, 'view/adminhtml/web/js/template-consumer.js'), 'utf8');
const consumerBlock = fs.readFileSync(path.join(consumerAdminUiRoot, 'Block/Adminhtml/Template/Actions.php'), 'utf8');
const consumerActions = fs.readFileSync(path.join(consumerAdminUiRoot, 'view/adminhtml/templates/template/actions.phtml'), 'utf8');
const creationResolver = fs.readFileSync(path.join(consumerAdminUiRoot, 'Model/Mapping/CreationTargetResolver.php'), 'utf8');

test('template mapping uses the shared language mapping shell and middle-column interaction', () => {
    assert.match(layout, /Ergonode_CoreAdminUi::css\/attribute-mapping\.css/);
    assert.match(template, /class="veui-workspace vet-admin/);
    assert.match(template, /class="veui-layout vet-mapping-layout veui-autosave-region"/);
    assert.match(template, /data-role="mapping-panel"/);
    assert.match(template, /class="vea-pair-list vet-pair-list"/);
    assert.doesNotMatch(template, /data-role="create-template-mapping"|vet-create-mapping/);
    assert.doesNotMatch(script, /function addEmptyDraft\(\)|\$createMappingButton/);
    assert.match(script, /data-role': 'create-magento-attribute-set'/);
    assert.match(script, /class: 'vea-create-option vet-create-attribute-set'/);
    assert.match(script, /function createPendingAttributeSet\(draftId\)/);
    assert.match(script, /Tworzę zestaw atrybutów w Magento i zapisuję mapowanie/);
    assert.match(creationResolver, /'__create_magento_attribute_set__'/);
    assert.match(creationResolver, /attributeSetManager->createOrGetForTemplate\(\$templateCode\)/);
    assert.match(consumerBlock, /'can_create_attribute_sets' => \$this->configProvider->shouldCreateAttributeSets\(\)/);
    assert.match(script, /template && !attributeSet && config\.can_create_attribute_sets/);
    assert.ok(
        mappingSaver.indexOf('$connection->beginTransaction();')
        < mappingSaver.indexOf('$this->normalizeMappings($mappings, $templates, $attributeSets)')
    );
    assert.match(script, /class: 'vea-pair-row vea-attribute-pair-row vet-pair-card'/);
    assert.doesNotMatch(script, /vet-pair-link/);
    assert.doesNotMatch(
        stylesheet,
        /\.vet-pair-card(?:\.vet-draft-card)?\s*\{[^}]*grid-template-columns/
    );
    assert.doesNotMatch(script, /vea-pair-row vea-status-tone-ok vet-pair-card/);
    assert.match(script, /vea-pair-row vea-attribute-pair-row vea-status-tone-warning/);
    assert.match(stylesheet, /\.vet-admin \.vet-mapping-layout\s*\{[\s\S]*?height: calc\(100% - 78px\)/);
});

test('template system configuration exposes base mapping and cron controls', () => {
    assert.match(systemConfig, /field id="create_attribute_sets"/);
    assert.match(systemConfig, /field id="status"/);
    assert.match(
        systemConfig,
        /field id="cron_expr"[\s\S]*?<depends>[\s\S]*?<field id="status">1<\/field>/
    );
    assert.doesNotMatch(systemConfig, /auto_match_attribute_sets|import_mode|allow_attribute_set_creation/);
    assert.doesNotMatch(systemConfig, /field id="sync_attributes"|field id="sync_sections"/);
});

test('double click completes one opposite draft while drag keeps a separate draft', () => {
    assert.match(script, /Ergonode_TemplateAdminUi\/js\/template-draft-pairing/);
    assert.match(
        script,
        /delegate\('dblclick', '\[data-role="unmapped-template-list"\][\s\S]*?addDraftTemplate\([\s\S]*?, true\)/
    );
    assert.match(
        script,
        /delegate\('dblclick', '\[data-role="unmapped-set-list"\][\s\S]*?addDraftAttributeSet\([\s\S]*?, true\)/
    );
    assert.match(script, /templateDraftPairing\.findComplementary\(drafts, 'template'\)/);
    assert.match(script, /templateDraftPairing\.findComplementary\(drafts, 'attribute_set'\)/);
    assert.match(
        script,
        /if \(payload\.type === 'template'\) \{\s*addDraftTemplate\(payload\.value\)/
    );
    assert.match(
        script,
        /if \(payload\.type === 'attribute_set'\) \{\s*addDraftAttributeSet\(payload\.value\)/
    );
});

test('complete template mappings save automatically while incomplete drafts remain client-side', () => {
    const markDirtyFunction = script.slice(
        script.indexOf('function markDirty()'),
        script.indexOf('function serializeVisibility()')
    );
    const addDraftTemplateFunction = script.slice(
        script.indexOf('function addDraftTemplate('),
        script.indexOf('function addDraftAttributeSet(')
    );
    const addDraftAttributeSetFunction = script.slice(
        script.indexOf('function addDraftAttributeSet('),
        script.indexOf('function fillDraftTemplate(')
    );
    const clearDraftSideFunction = script.slice(
        script.indexOf('function clearDraftSide('),
        script.indexOf('function isTemplateDrafted(')
    );

    assert.doesNotMatch(template, /data-role="save-template-mapping"|Zapisz mapowanie|save\.svg/);
    assert.match(template, /class="veui-layout vet-mapping-layout veui-autosave-region"/);
    assert.match(template, /data-role="autosave-region"/);
    assert.match(template, /data-role="autosave-error"/);
    assert.match(template, /data-role="retry-autosave"/);
    assert.match(script, /Ergonode_TemplateAdminUi\/js\/template-autosave/);
    assert.match(script, /templateAutosave\.create\(element, config/);
    assert.match(script, /scope\.delegate\('click', '\[data-role="retry-autosave"\]'/);
    assert.match(markDirtyFunction, /autosave\.schedule\(\)/);
    assert.match(addDraftTemplateFunction, /drafts\.push\([\s\S]*?markDirty\(\)[\s\S]*?renderAll\(\)/);
    assert.match(addDraftAttributeSetFunction, /drafts\.push\([\s\S]*?markDirty\(\)[\s\S]*?renderAll\(\)/);
    assert.match(clearDraftSideFunction, /markDirty\(\)/);
    assert.match(
        script,
        /if \(removeDraft\(Number\(\$\(button\)\.data\('draft-id'\) \|\| 0\)\)\) \{[\s\S]*?markDirty\(\)/
    );
    assert.doesNotMatch(script, /function saveMappings\(\)|\$saveButton|updateButtons\(\)/);
    assert.match(autosave, /config\.urls && config\.urls\.save_mapping/);
    assert.match(autosave, /Ergonode_CoreAdminUi\/js\/autosave/);
    assert.match(autosave, /mappings: JSON\.stringify\(snapshot\.mappings \|\| \{\}\)/);
    assert.doesNotMatch(autosave, /drafts:/);
    assert.match(autosave, /visibility: JSON\.stringify\(snapshot\.visibility \|\| \[\]\)/);
    assert.match(script, /drafts: serializeDrafts\(\)/);
    assert.match(script, /function serializeDrafts\(\)/);
    assert.doesNotMatch(block, /getMappingDrafts|['"]drafts['"]/);
    assert.doesNotMatch(template, /\$mappingDrafts|foreach \(\$mappingDrafts as \$draft\)/);
    assert.doesNotMatch(saveController, /getParam\('drafts'/);
    assert.doesNotMatch(mappingSaver, /mappingDraftResource|normalizeDrafts/);
    assert.match(coreAutosave, /region\.setAttribute\('inert', ''\)/);
    assert.match(workspaceStyles, /\.veui-autosave-region\[data-autosave-state='saving'\]::after/);
    assert.match(workspaceStyles, /\.veui-autosave-region\[data-autosave-state='saving'\] > \.veui-panel/);
});

test('template columns render their stable initial state before JavaScript enhancement', () => {
    const initFunction = script.slice(
        script.indexOf('function init()'),
        script.indexOf('function bindEvents()')
    );

    assert.match(template, /class="veui-workspace vet-admin"/);
    assert.doesNotMatch(template, /is-initializing/);
    assert.doesNotMatch(stylesheet, /is-initializing|visibility:\s*hidden/);
    assert.match(template, /\$attributeSets = \$block->getAttributeSets\(\)/);
    assert.match(template, /foreach \(\$unmappedTemplates as \$template\)/);
    assert.match(template, /foreach \(\$mappedPairs as \$pairIndex => \$pair\)/);
    assert.match(template, /foreach \(\$unmappedAttributeSets as \$attributeSet\)/);
    assert.match(template, /data-role="entity-options-placeholder"/);
    assert.match(initFunction, /hydrateInitialView\(\)/);
    assert.match(initFunction, /function hydrateInitialView\(\)/);
    assert.doesNotMatch(initFunction, /renderAll\(\)/);
});

test('template mapping does not expose section trees or docking controls', () => {
    assert.doesNotMatch(template, /template-structure-preview|structure-tree|Sekcje|sekcje i atrybuty/);
    assert.doesNotMatch(script, /template-tree-state|template-dock-state/);
    assert.doesNotMatch(script, /renderStructurePreview|buildStructureModel|buildMagentoStructureModel/);
    assert.doesNotMatch(script, /toggle-template-section|toggle-attribute-dock|serializeDockedAttributes/);
    assert.doesNotMatch(stylesheet, /\.vet-structure|\.vet-tree/);
    assert.doesNotMatch(uiProvider, /loadSections|loadTemplateAttributes|loadMagentoStructures/);
    assert.doesNotMatch(uiProvider, /ergonode_template_section|ergonode_template_attribute_dock/);
    assert.doesNotMatch(script, /docked_attributes/);
    assert.doesNotMatch(saveController, /docked_attributes|dockedAttributes/);
});

test('template admin has no dedicated Sections route', () => {
    assert.equal(fs.existsSync(sectionsController), false);
    assert.equal(fs.existsSync(sectionsLayout), false);
    assert.doesNotMatch(template, /SECTION_TEMPLATE_SECTIONS|vet-admin-sections|Przejdź do Templates/);
});

test('template sources share visibility and sorting controls and persist enabled state', () => {
    assert.doesNotMatch(template, /class="veui-button veui-visibility-control veui-visibility-toggle"/);
    assert.match(template, /\$showExcludedHint = __\('Show excluded templates and attribute sets'\)/);
    assert.match(template, /\$hideExcludedHint = __\('Hide excluded templates and attribute sets'\)/);
    assert.match(template, /data-source-options-label=/);
    assert.match(template, /data-show-excluded-hint=/);
    assert.match(template, /data-hide-excluded-hint=/);
    assert.match(sourceOptions, /role: 'visibility-toggle'/);
    assert.match(sourceOptions, /label: \$t\('Excluded'\)/);
    assert.match(sourceOptions, /iconClass: 'veui-visibility-icon'/);
    assert.match(sourceOptions, /data-template-source-options/);
    assert.match(sourceOptions, /data-attribute-set-source-options/);
    assert.equal((sourceOptions.match(/role: 'source-sort-direction'/g) || []).length, 1);
    assert.equal((sourceOptions.match(/role: 'source-sort-toggle'/g) || []).length, 1);
    assert.match(sourceOptions, /iconClass: 'veui-sort-direction-icon'/);
    assert.match(sourceOptions, /iconClass: 'veui-sort-field-icon'/);
    assert.equal(
        (template.match(/class="veui-panel-head-tools vea-side-tools vet-side-tools"/g) || []).length,
        2
    );
    assert.match(
        template,
        /class="veui-entity-options veui-source-options vet-source-options vet-options-placeholder"/
    );
    assert.match(sourceOptions, /menu\.classList\.add\('veui-source-options'\)/);
    assert.match(workspaceStyles, /\.veui-tools-inline > \.veui-search\s*\{[\s\S]*?flex: 1 1 auto/);
    assert.match(workspaceStyles, /\.veui-source-options > summary,[\s\S]*?height: 34px/);
    assert.doesNotMatch(stylesheet, /\.vet-side-tools\s*\{|\.vet-source-options > summary/);
    assert.match(script, /vea-attribute-card vet-map-card vet-template-card/);
    assert.match(script, /vea-attribute-card vet-map-card vet-set-card/);
    assert.match(script, /data-role': 'source-active-toggle'/);
    assert.match(script, /scope\.delegate\('click', '\[data-role="source-active-toggle"\]'/);
    assert.match(script, /scope\.delegate\('click', '\[data-role="visibility-toggle"\]'/);
    assert.match(script, /Ergonode_TemplateAdminUi\/js\/template-source-options/);
    assert.match(script, /templateSourceOptions\.sync\(element, hasExcludedSources\(\)\)/);
    assert.match(script, /search\.bindSort\(scope, panel/);
    assert.match(script, /function sortSourcePanel\(panel\)/);
    assert.match(script, /values: \['label', 'code'\]/);
    assert.match(script, /attributeSetPanel \? \$t\('ID'\) : \$t\('Kod'\)/);
    assert.match(script, /!isSourceActive\(template\) && !showsOmittedSources\(\)/);
    assert.match(script, /!isSourceActive\(attributeSet\) && !showsOmittedSources\(\)/);
    assert.match(script, /visibility: serializeVisibility\(\)/);
    assert.match(autosave, /visibility: JSON\.stringify\(snapshot\.visibility \|\| \[\]\)/);
    assert.match(block, /MappingVisibilityProviderInterface/);
    assert.match(block, /getActiveMap\('template', 'ergo'/);
    assert.match(block, /getActiveMap\('template', 'magento'/);
    assert.match(saveController, /getParam\('visibility', '\[\]'\)/);
    assert.match(visibilitySaver, /MappingVisibilitySaverInterface/);
    assert.match(visibilitySaver, /'entity_type' => 'template'/);
    assert.match(visibilitySaver, /visibilitySaver->saveMany/);
});

test('template card options do not create Magento attribute sets directly', () => {
    const enhanceFunction = script.slice(
        script.indexOf('function enhanceTemplateCard'),
        script.indexOf('function buildAttributeSetCard')
    );

    assert.doesNotMatch(enhanceFunction, /entity-create-magento-attribute-set/);
    assert.doesNotMatch(enhanceFunction, /Utwórz Attribute Set w Magento/);
    assert.match(enhanceFunction, /role: 'entity-add-to-mapping'/);
    assert.match(script, /data-role': 'create-magento-attribute-set'/);
});

test('template screen uses requested panel and page titles', () => {
    const leftPanel = template.slice(
        template.indexOf('data-role="template-drop-source"'),
        template.indexOf('data-role="mapping-panel"')
    );
    const rightPanel = template.slice(template.indexOf('data-role="attribute-set-drop-source"'));

    assert.match(leftPanel, /<strong class="veui-panel-title">[\s\S]*?__\('Templates'\)/);
    assert.match(rightPanel, /<strong class="veui-panel-title">[\s\S]*?__\('Attribute sets'\)/);
    assert.doesNotMatch(rightPanel, /vet-panel-subtitle|__\('Magento 2'\)/);
    assert.match(indexController, /getTitle\(\)->prepend\(__\('Templates vs Attribute Sets'\)\)/);
});

test('Ergonode template source has no subtitle and exposes refresh in its empty state', () => {
    const leftPanel = template.slice(
        template.indexOf('data-role="template-drop-source"'),
        template.indexOf('data-role="mapping-panel"')
    );

    assert.match(leftPanel, /class="vea-attribute-list vet-card-list vet-template-source-list"/);
    assert.doesNotMatch(leftPanel, /vet-panel-subtitle/);
    assert.doesNotMatch(leftPanel, /class="vea-icon-action vea-refresh-ergonode"/);
    assert.match(consumerScript, /role: 'refresh-templates'/);
    assert.match(consumerScript, /label: \$t\('Refresh'\)/);
    assert.match(leftPanel, /\$templates === \[\]/);
    assert.match(leftPanel, /class="vea-attribute-empty-state vet-template-empty-state"/);
    assert.match(leftPanel, /data-role="template-empty-actions"/);
    assert.match(consumerScript, /template-empty-actions/);
    assert.match(consumerScript, /scope\.delegate\('click', '\[data-role="refresh-templates"\]'/);
});

test('template toolbar exposes synchronization separately from snapshot refresh', () => {
    const toolbar = template.slice(
        template.indexOf('<div class="veui-toolbar vea-viewbar vet-toolbar">'),
        template.indexOf('<div class="veui-message')
    );

    assert.match(consumerActions, /SynchronizationActions::class/);
    assert.match(consumerActions, /'primary_role' => 'sync-templates'/);
    assert.match(consumerActions, /'has_cursor_actions' => true/);
    assert.match(synchronizationActions, /data-synchronization-action="reset-cursor-and-sync"/);
    assert.match(consumerBlock, /'sync' => \$this->getUrl\('ergonode\/template\/sync'\)/);
    assert.match(consumerScript, /scope\.delegate\('click', '\[data-synchronization-action\]'/);
});

test('mapped template pairs show entity names and identifiers only', () => {
    const pairFunction = script.slice(
        script.indexOf('function buildPairCard'),
        script.indexOf('function buildDraftPairCard')
    );

    assert.match(pairFunction, /appendFilledPairContent\(\$ergo, templateDisplayName\(template\), template\.code\)/);
    assert.match(
        pairFunction,
        /appendFilledPairContent\([\s\S]*?\$magento,[\s\S]*?attributeSetDisplayName\(attributeSet\),[\s\S]*?attributeSetIdentifier\(attributeSet\)/
    );
    assert.doesNotMatch(pairFunction, /\.text\(\$t\('Ergonode'\)\)|\.text\(\$t\('Magento'\)\)/);
    assert.match(script, /function appendFilledPairContent\(\$side, label, identifier\)/);
    assert.match(script, /<strong\/>.*\.text\(label\)/);
    assert.match(script, /<code\/>[\s\S]*?\.text\(identifier\)[\s\S]*?class: 'vea-card-subline'/);
    assert.match(script, /function templateDisplayName\(template\)/);
    assert.match(script, /return name \|\| template\.code/);
    assert.match(script, /function attributeSetDisplayName\(attributeSet\)/);
    assert.match(script, /return name \|\| attributeSetIdentifier\(attributeSet\)/);
    assert.match(script, /function attributeSetIdentifier\(attributeSet\)/);
    assert.match(script, /: '#' \+ attributeSet\.id/);
    assert.match(uiProvider, /'name' => \$this->templateNameResolver->resolve\(/);
    assert.match(uiProvider, /'attribute_set_name' => \(string\)/);
});

test('template source cards show the Ergonode name with the template code below it', () => {
    const sourceFunction = script.slice(
        script.indexOf('function buildTemplateCard'),
        script.indexOf('function buildAttributeSetCard')
    );

    assert.match(sourceFunction, /var displayName = templateDisplayName\(template\)/);
    assert.match(sourceFunction, /<strong\/>.*\.text\(displayName\)/);
    assert.match(sourceFunction, /class: 'vea-card-subline'.*\.text\(template\.code\)/);
    assert.doesNotMatch(sourceFunction, /class: 'vea-card-subline'.*\.text\(template\.status\)/);
    assert.match(sourceFunction, /'data-search': displayName \+ ' ' \+ template\.code/);
});

test('the middle link indicator replaces trailing unlink buttons', () => {
    const pairFunctions = script.slice(
        script.indexOf('function buildPairCard'),
        script.indexOf('function renderDraft')
    );

    assert.match(
        pairFunctions,
        /class: 'vea-link-indicator vet-link-action'[\s\S]*?'data-role': 'unlink-pair'/
    );
    assert.match(
        pairFunctions,
        /class: 'vea-link-indicator vet-link-action'[\s\S]*?'data-role': 'clear-draft'/
    );
    assert.doesNotMatch(pairFunctions, /class: 'vea-unlink vet-unlink'/);
    assert.doesNotMatch(stylesheet, /\.vet-unlink/);
    assert.match(stylesheet, /\.vet-link-action:hover,[\s\S]*?background: var\(--veui-red-soft\)/);
});

test('Ergonode template source uses the shared attribute list spacing', () => {
    assert.match(stylesheet, /\.vet-template-source-list\s*\{[\s\S]*?padding: 12px 14px 14px;/);
});

test('template source cards use the shared neutral attribute card border', () => {
    assert.match(stylesheet, /\.vet-map-card\s*\{[\s\S]*?border-left-width: 1px;/);
    assert.doesNotMatch(stylesheet, /\.vet-template-card[^}]*border-left-color/);
    assert.doesNotMatch(stylesheet, /\.vet-set-card[^}]*border-left-color/);
});

test('template mapping rows use the shared no-accent pair modifier', () => {
    assert.match(script, /vea-pair-row vea-attribute-pair-row vet-pair-card/);
    assert.match(script, /vea-pair-row vea-attribute-pair-row vea-status-tone-warning vet-pair-card vet-draft-card/);
    assert.match(template, /vea-pair-row vea-attribute-pair-row vet-pair-card/);
    assert.doesNotMatch(stylesheet, /\.vet-pair-card(?::|\.|\[|\s*\{)/);
});

test('template pair sides use the universal pair markup and styles from Languages', () => {
    assert.match(languageStylesheet, /\.vel-pair-row::before\s*\{[\s\S]*?display: none;/);
    assert.match(languageStylesheet, /\.vel-pair-row\s*\{[\s\S]*?padding-right: 10px;/);
    assert.match(script, /function appendEmptyPairContent\(\$side, title, source\)/);
    assert.match(script, /appendEmptyPairContent\(\$templateSide, \$t\('Upuść template'\), \$t\('z Ergonode'\)\)/);
    assert.match(script, /appendEmptyPairContent\(\$setSide, \$t\('Upuść attribute set'\), \$t\('z Magento'\)\)/);
    assert.match(template, /<strong><\?= \$escaper->escapeHtml\(\$templateName\) \?><\/strong>[\s\S]*?<span class="vea-card-subline">[\s\S]*?<code>/);
    assert.doesNotMatch(stylesheet, /\.vet-pair-side/);
    assert.match(script, /function appendPairBadge\(\$side, label, attributes\)/);
    assert.match(script, /\$side\.children\('\.vea-card-subline'\)\.first\(\)/);
    assert.doesNotMatch(script, /\.appendTo\(\$(?:ergo|magento|pendingSide)\)/);
});

test('saved and active template mappings have no view-specific visual override', () => {
    assert.doesNotMatch(script, /vea-pair-row vea-status-tone-ok vet-pair-card/);
    assert.doesNotMatch(stylesheet, /\.vet-pair-card\.vea-status-tone-ok/);
    assert.doesNotMatch(stylesheet, /\.vet-pair-card\.is-active/);
});

test('template refresh reports progress inside the blocked Ergonode source panel', () => {
    const refreshFunction = consumerScript.slice(
        consumerScript.indexOf('function refreshTemplates'),
        consumerScript.indexOf('function synchronizeTemplates')
    );

    assert.match(template, /data-role="template-refresh-state"[\s\S]*?role="status"[\s\S]*?aria-live="polite"/);
    assert.match(template, /data-role="template-refresh-title"/);
    assert.match(template, /data-role="template-refresh-text"/);
    assert.match(consumerScript, /function setTemplateRefreshState\(title, text\)/);
    assert.match(consumerScript, /function clearTemplateRefreshState\(\)/);
    assert.match(consumerScript, /Ergonode_TemplateConsumerAdminUi\/js\/template-refresh-state/);
    assert.match(consumerScript, /templateRefreshState\.show\(title, text\)/);
    assert.match(refreshState, /panel\.classList\.add\('is-refreshing'\)/);
    assert.match(refreshState, /panel\.setAttribute\('aria-busy', 'true'\)/);
    assert.match(refreshState, /search\.disabled = true/);
    assert.match(refreshState, /button\.disabled = true/);
    assert.doesNotMatch(refreshFunction, /messageBus\.show/);
    assert.match(stylesheet, /\.vet-template-refresh-state\s*\{[\s\S]*?position: absolute/);
    assert.match(stylesheet, /\.vet-source-panel\.is-refreshing[\s\S]*?opacity: \.55/);
});

test('template refresh failure uses the shared message and links missing languages to their mapping', () => {
    const refreshFunction = consumerScript.slice(
        consumerScript.indexOf('function refreshTemplates'),
        consumerScript.indexOf('function setTemplateRefreshState')
    );

    assert.match(block, /'language_mapping' => \$this->getUrl\('ergonode\/language\/index'\)/);
    assert.match(consumerScript, /function languageMappingAction\(error\)/);
    assert.match(consumerScript, /error\.message !== \$t\([\s\S]*?Configure at least one active Ergonode language/);
    assert.match(refreshFunction, /messageBus\.error\(/);
    assert.match(consumerScript, /label: \$t\('Przejdź do mapowania języków'\)/);
    assert.match(refreshFunction, /languageMappingAction\(error\)/);
    assert.doesNotMatch(refreshFunction, /messageBus\.show\('error'/);
});
