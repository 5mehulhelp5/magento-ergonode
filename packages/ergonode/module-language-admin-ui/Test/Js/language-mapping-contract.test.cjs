const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const adminRoot = path.resolve(__dirname, '../..');
const backendRoot = path.resolve(adminRoot, '../../..');
const languageRoot = path.join(backendRoot, 'vendor/ergonode/module-language');
const coreRoot = path.join(backendRoot, 'vendor/ergonode/module-core');
const coreAdminRoot = path.join(backendRoot, 'vendor/ergonode/module-core-admin-ui');

function read(root, file) {
    return fs.readFileSync(path.join(root, file), 'utf8');
}

test('language mapping is a dedicated domain and Admin UI module', () => {
    const menu = read(adminRoot, 'etc/adminhtml/menu.xml');
    const routes = read(adminRoot, 'etc/adminhtml/routes.xml');
    const layout = read(adminRoot, 'view/adminhtml/layout/ergonode_language_index.xml');

    assert.match(menu, /id="Ergonode_Language::language_mapping"/);
    assert.match(menu, /action="ergonode\/language\/index"/);
    assert.match(routes, /Ergonode_LanguageAdminUi/);
    assert.match(layout, /Ergonode\\LanguageAdminUi\\Block\\Adminhtml\\Language\\Mapping/);
});

test('mapping workspace exposes shared language headings and autosave behavior', () => {
    const template = read(adminRoot, 'view/adminhtml/templates/language/mapping.phtml');
    const script = read(adminRoot, 'view/adminhtml/web/js/language-mapping.js');
    const autosave = read(adminRoot, 'view/adminhtml/web/js/language-autosave.js');
    const coreAutosave = read(coreAdminRoot, 'view/adminhtml/web/js/autosave.js');
    const sourceOptions = read(adminRoot, 'view/adminhtml/web/js/language-source-options.js');
    const styles = read(adminRoot, 'view/adminhtml/web/css/language-mapping.css');
    const workspaceStyles = read(coreAdminRoot, 'view/adminhtml/web/css/ergonode-workspace.css');
    const refresh = read(adminRoot, 'Controller/Adminhtml/Language/Refresh.php');
    const acl = read(languageRoot, 'etc/acl.xml');

    const panelHeadings = template.match(
        /<strong class="veui-panel-title">\s*<span class="veui-brand-mark veui-brand-(?:ergonode|magento)"[\s\S]*?<\/strong>/g
    ) || [];

    assert.equal(panelHeadings.length, 2);
    for (const heading of panelHeadings) {
        assert.match(heading, /__\('Languages'\)/);
        assert.doesNotMatch(heading, /__\('(Języki Ergonode|Magento Store Views)'\)/);
    }
    assert.match(template, /data-role="mapping-list"/);
    assert.doesNotMatch(template, /veui-panel-subtitle|Kliknij dwukrotnie lub przeciągnij język i Store View/);
    assert.match(template, /Ergonode_LanguageAdminUi\/js\/language-mapping/);
    assert.match(script, /Ergonode_LanguageAdminUi\/js\/language-autosave/);
    assert.match(script, /languageAutosave\.create\(root, config/);
    assert.match(autosave, /Ergonode_CoreAdminUi\/js\/autosave/);
    assert.match(autosave, /payload: JSON\.stringify\(payload\)/);
    assert.match(autosave, /DEFAULT_DEBOUNCE_MS = 350/);
    assert.match(autosave, /window\.setTimeout\(schedulePersist, debounceMs\)/);
    assert.match(template, /data-role="language-loader"/);
    assert.match(styles, /#ergonode-language-mapping\[data-language-busy='true'\]::after/);
    assert.match(script, /data-locale/);
    assert.match(script, /data-role="auto-match"/);
    assert.equal((template.match(/data-source-options-label=/g) || []).length, 2);
    assert.equal((template.match(/data-show-excluded-hint=/g) || []).length, 2);
    assert.equal((template.match(/data-hide-excluded-hint=/g) || []).length, 2);
    assert.equal(
        (template.match(/class="veui-tools veui-tools-inline vea-side-tools vel-side-tools"/g) || []).length,
        2
    );
    assert.equal(
        (template.match(/veui-entity-options veui-source-options vel-source-options vel-options-placeholder/g) || [])
            .length,
        2
    );
    assert.match(template, /veui-entity-options vel-options-placeholder/);
    const leftPanel = template.slice(
        template.indexOf('data-source-panel="ergo"'),
        template.indexOf('data-role="mapping-panel"')
    );
    const middlePanel = template.slice(
        template.indexOf('data-role="mapping-panel"'),
        template.indexOf('data-source-panel="magento"')
    );

    assert.doesNotMatch(leftPanel, /data-role="auto-match"|Auto Connect/);
    assert.match(middlePanel, /class="veui-tools veui-tools-inline vel-middle-tools"/);
    assert.match(middlePanel, /data-language-mapping-options="1"/);
    assert.match(middlePanel, /data-role="auto-match"[\s\S]*Auto Connect/);
    assert.equal((template.match(/data-role="auto-match"/g) || []).length, 1);
    assert.doesNotMatch(template, /class="vea-icon-action vea-refresh-ergonode"/);
    assert.doesNotMatch(template, /data-role="visibility-toggle"|vel-store-visibility-toggle/);
    assert.match(template, /Language actions: Ergonode/);
    assert.match(template, /Language actions: Magento/);
    assert.match(template, /Show excluded Store Views/);
    assert.match(template, /Hide excluded Store Views/);
    assert.match(script, /Ergonode_LanguageAdminUi\/js\/language-source-options/);
    assert.match(script, /languageSourceOptions\.initialize\(root\)/);
    assert.match(script, /languageSourceOptions\.sync\(root, findAutoMatches\(root\)\.length\)/);
    assert.match(sourceOptions, /label: \$t\('Refresh'\)/);
    assert.doesNotMatch(sourceOptions, /label: \$t\('Auto Connect'\)/);
    assert.match(sourceOptions, /mappingAutoMatchSelector/);
    assert.match(sourceOptions, /root \? root\.querySelector\(mappingAutoMatchSelector\) : null/);
    assert.match(sourceOptions, /label: \$t\('Excluded'\)/);
    assert.match(sourceOptions, /button\.disabled = !available/);
    assert.match(sourceOptions, /panel\.querySelectorAll\('\[data-role="entity-card"\] \[data-role="source-active-toggle"\]'\)/);
    assert.match(sourceOptions, /root\.querySelectorAll\(panelSelector\)/);
    assert.doesNotMatch(
        sourceOptions,
        /root\.querySelectorAll\('\[data-role="entity-card"\] \[data-role="source-active-toggle"\]'/
    );
    assert.match(sourceOptions, /placeholder\.remove\(\)/);
    assert.match(sourceOptions, /menu\.classList\.add\('veui-source-options'\)/);
    assert.match(sourceOptions, /menu\.setAttribute\('data-source-options', source\)/);
    assert.match(sourceOptions, /visibilityToggle\.setVisible\(button, false\)/);
    assert.doesNotMatch(template, /data-role="refresh-ergonode"/);
    assert.match(workspaceStyles, /\.veui-tools-inline > \.veui-search\s*\{[\s\S]*?flex: 1 1 auto/);
    assert.match(workspaceStyles, /\.veui-source-options > summary,[\s\S]*?height: 34px/);
    assert.doesNotMatch(
        styles,
        /\.vel-side-tools\s*\{|\.vel-source-options > summary|\.vel-options-placeholder-trigger\s*\{/
    );
    assert.doesNotMatch(styles, /\.vel-store-visibility-toggle/);
    assert.match(
        styles,
        /\.vel-source-card\[data-source='ergo'\] > \.vea-card-toggle\s*\{[\s\S]*?display:\s*none/
    );
    assert.match(template, /veui-entity-options-placeholder-trigger vel-options-placeholder-trigger/);
    assert.match(script, /Ergonode_CoreAdminUi\/js\/visibility-toggle/);
    assert.match(script, /var visible = matchesQuery && \(active \|\| showOmitted\)/);
    assert.match(template, /<button type="button"[\s\S]*?data-role="source-active-toggle"[\s\S]*?aria-pressed=/);
    assert.doesNotMatch(template, /<input[^>]*data-role="(?:visibility-toggle|source-active-toggle)"/);
    assert.match(script, /buttons\.setPressed\(toggle, active\)/);
    assert.match(script, /menu && placeholder[\s\S]*?placeholder\.remove\(\)/);
    assert.match(script, /visibilityToggle\.toggle\(button\)/);
    assert.doesNotMatch(script, /\.checked/);
    assert.equal((template.match(/data-role="disable-drop-zone"/g) || []).length, 1);
    assert.match(template, /data-drop-action="remove-from-list"/);
    assert.match(template, /Remove from list/);
    assert.match(template, /Drag here to remove this language/);
    assert.doesNotMatch(template, /Upuść tutaj Magento Store View|Drop a Magento Store View here/);
    assert.doesNotMatch(styles, /is-dragging-magento[^\n]*disable-drop-zone/);
    assert.match(script, /visibility: serializeVisibility\(root\)/);
    assert.match(script, /event\.dataTransfer\.effectAllowed = 'linkMove'/);
    assert.match(script, /event\.dataTransfer\.dropEffect = 'move'/);
    assert.match(script, /function setCardDragImage\(event, card\)/);
    assert.match(script, /preview = card\.cloneNode\(true\)/);
    assert.match(script, /preview\.querySelectorAll\('button, details, \[role="tooltip"\]'\)/);
    assert.match(script, /preview\.querySelectorAll\('\[id\]'\)/);
    assert.match(script, /event\.dataTransfer\.setDragImage\(/);
    assert.match(script, /card\.classList\.add\('is-dragging'\)/);
    assert.match(script, /draggedCard\.classList\.remove\('is-dragging'\)/);
    assert.match(styles, /\.vel-source-card\.is-dragging\s*\{[\s\S]*?opacity:\s*0;/);
    assert.match(script, /preview\.style\.left = '-10000px'/);
    assert.match(script, /preview\.style\.top = '-10000px'/);
    assert.match(script, /snapshotRemoval\.requestRemoval\(draggedCard\)/);
    assert.match(
        script,
        /data-drop-action'\) === 'remove-from-list'[\s\S]*?snapshotRemoval\.requestRemoval\(draggedCard\);[\s\S]*?return;/
    );
    assert.match(script, /setCardActive\(draggedCard, false\)/);
    assert.match(template, /data-role="language-empty-state"/);
    assert.match(template, /Brak pobranych języków Ergonode/);
    assert.doesNotMatch(template, /data-role="save-mapping"/);
    assert.doesNotMatch(template, /vel-autosave-status|data-role="autosave-status"/);
    assert.match(
        template,
        /class="veui-layout vea-shell veui-autosave-region"[\s\S]*?data-role="autosave-region"[\s\S]*?data-autosave-state="saved"[\s\S]*?aria-busy="false"/
    );
    assert.match(template, /data-role="autosave-error"[\s\S]*?role="alert"[\s\S]*?hidden/);
    assert.match(template, /data-role="retry-autosave"/);
    assert.doesNotMatch(template, /data-role="unsaved-state"|Niezapisane zmiany/);
    assert.match(coreAutosave, /inFlight \|\| blocked \|\| !pending/);
    assert.match(coreAutosave, /if \(pending\) \{[\s\S]*?drain\(\)/);
    assert.match(coreAutosave, /setState\('error'\)/);
    assert.match(coreAutosave, /region\.setAttribute\('inert', ''\)/);
    assert.match(coreAutosave, /region\.removeAttribute\('inert'\)/);
    assert.match(coreAutosave, /error\.hidden = state !== 'error'/);
    assert.match(
        workspaceStyles,
        /\.veui-autosave-region\[data-autosave-state='saving'\] > \.veui-panel[\s\S]*?filter:[\s\S]*?opacity:/
    );
    assert.match(workspaceStyles, /\.veui-autosave-region\[data-autosave-state='saving'\]::after/);
    assert.match(template, /data-can-refresh="<\?= \$block->canRefreshLanguages\(\) \? '1' : '0' \?>"/);
    assert.match(script, /request\.post\(config\.urls && config\.urls\.refresh/);
    assert.match(refresh, /implements HttpPostActionInterface/);
    assert.match(refresh, /ADMIN_RESOURCE = 'Ergonode_Language::language_mapping_refresh'/);
    assert.match(acl, /id="Ergonode_Language::language_mapping_refresh"/);
    assert.doesNotMatch(template, /toggle-full-view|full-view-label|Full View/);
    assert.doesNotMatch(script, /full-view|fullView/);
});

test('source cards render their initial filtered and mapped state before JavaScript mounts', () => {
    const template = read(adminRoot, 'view/adminhtml/templates/language/mapping.phtml');

    assert.match(template, /\$mappedStoreViewIds = array_fill_keys/);
    assert.match(template, /\$languageSearch = \$language\['label'\] \. ' ' \. \$language\['code'\]/);
    assert.match(template, /data-search="<\?= \$escaper->escapeHtmlAttr\(\$languageSearch\)/);
    assert.match(template, /\$language\['active'\] \? '' : ' is-filter-hidden is-inactive'/);
    assert.match(template, /\$storeViewCardClass \.= \$isActive \? '' : ' is-filter-hidden is-inactive'/);
    assert.match(template, /<\?= \$isActive \? '' : 'hidden' \?>/);
    assert.match(template, /\$isMapped \? ' is-mapped' : ''/);
    assert.match(template, /\$isMapped \? 'aria-disabled="true"' : ''/);
});

test('language refresh flushes autosave and reloads authoritative server state', () => {
    const script = read(adminRoot, 'view/adminhtml/web/js/language-mapping.js');
    const refreshStart = script.indexOf("scope.delegate('click', '[data-role=\"refresh-ergonode\"]'");
    const refreshEnd = script.indexOf("scope.delegate('click', '[data-role=\"retry-autosave\"]'", refreshStart);
    const handler = script.slice(refreshStart, refreshEnd);

    assert.match(handler, /autosave\.flush\(\)[\s\S]*request\.post[\s\S]*window\.location\.reload\(\)/);
    assert.doesNotMatch(script, /rememberRefreshState|restoreRefreshState|history\.replaceState/);
});

test('Magento side count shows mapped Store Views against all Store Views', () => {
    const template = read(adminRoot, 'view/adminhtml/templates/language/mapping.phtml');
    const script = read(adminRoot, 'view/adminhtml/web/js/language-mapping.js');

    assert.match(template, /data-role="store-mapping-count"/);
    assert.match(template, /count\(\$mappedStoreViewIds\)[\s\S]*?' \/ '[\s\S]*?count\(\$storeViews\)/);
    assert.match(script, /\[data-role="store-mapping-count"\]/);
    assert.match(script, /String\(mappedStores\) \+ ' \/ ' \+ String\(stores\)/);
});

test('admin store view uses a client-friendly required mapping tooltip instead of a visibility toggle', () => {
    const template = read(adminRoot, 'view/adminhtml/templates/language/mapping.phtml');
    const script = read(adminRoot, 'view/adminhtml/web/js/language-mapping.js');

    assert.match(template, /\$requiredStoreViewId = 0/);
    assert.match(template, /\(int\)\$storeView\['id'\] === \$requiredStoreViewId/);
    assert.match(template, /data-mapping-required="true"/);
    assert.match(template, /\* Wymagane/);
    assert.match(template, /class="veui-mapping-requirement-control"/);
    assert.match(template, /class="veui-mapping-requirement-icon"/);
    assert.match(template, /class="veui-mapping-requirement-tooltip"/);
    assert.match(template, /role="tooltip"/);
    assert.match(template, /Wybierz język domyślny/);
    assert.match(template, /Przypisz język Ergonode, który ma być używany jako domyślny/);
    assert.match(template, /Bez tego synchronizacja nie może się rozpocząć/);
    assert.doesNotMatch(template, /ID 0|Wymagane mapowanie języka/);
    assert.match(template, /\$isActive = \$isRequired \|\| \$storeView\['active'\]/);
    assert.match(template, /<\?php if \(\$isRequired\): \?>[\s\S]*?veui-mapping-requirement-control[\s\S]*?<\?php else: \?>[\s\S]*?data-role="source-active-toggle"/);
    assert.match(script, /requirements\.isRequired\(card\)/);
    assert.match(script, /requirements\.refresh\(\)/);
    assert.match(script, /countMappingsForCard\(card\) > 1[\s\S]*?window\.confirm/);
    assert.match(script, /save the change immediately/);
});

test('language mapping follows the slot-based interaction used by attributes and options', () => {
    const template = read(adminRoot, 'view/adminhtml/templates/language/mapping.phtml');
    const script = read(adminRoot, 'view/adminhtml/web/js/language-mapping.js');
    const styles = read(adminRoot, 'view/adminhtml/web/css/language-mapping.css');
    const schema = read(languageRoot, 'etc/db_schema.xml');

    assert.doesNotMatch(template, /vea-type-language|>język<|__\('język'\)/);
    assert.match(template, /data-role="mapping-panel"/);
    assert.match(
        template,
        /<button type="button"[\s\S]*?class="vea-link-indicator vel-unlink-mapping"[\s\S]*?data-role="unlink-mapping"/
    );
    assert.doesNotMatch(template, /class="vea-unlink"/);
    assert.match(script, /function unlinkMappingHtml\(\)/);
    assert.doesNotMatch(script, /function unlinkButtonHtml|class="vea-unlink"/);
    assert.match(script, /function addSourcePayloadToMapping\(root, payload, batchCard\)/);
    assert.match(script, /function findMatchingDraft\(root, payload\)/);
    assert.match(script, /function findComplementaryDraft\(root, source\)/);
    assert.match(script, /function findManualPair\(root, draggedCard, targetCard\)/);
    assert.match(
        script,
        /function addManualMapping\(language, store\)[\s\S]*?findMatchingDraft\(root, language\)[\s\S]*?findMatchingDraft\(root, store\)/
    );
    assert.match(
        script,
        /function addSourcePayloadToMapping\(root, payload, batchCard\)[\s\S]*?findComplementaryDraft\(root, payload\.source\)[\s\S]*?fillDraft\(complementaryDraft, payload\)/
    );
    assert.doesNotMatch(
        script.slice(
            script.indexOf('function findManualPair(root, draggedCard, targetCard)'),
            script.indexOf('function updateSourcePanel(root, panel)')
        ),
        /data-locale|\.locale|languagePrefix/
    );
    assert.match(
        script,
        /scope\.delegate\('drop', '\[data-role="source-panel"\] \[data-role="entity-card"\]'[\s\S]*?addManualMapping\(pair\.language, pair\.store\)[\s\S]*?autosave\.schedule\(\)/
    );
    assert.match(script, /scope\.delegate\('dblclick', '\[data-role="entity-card"\]'/);
    assert.match(script, /function unlinkMappingRow\(row\)/);
    assert.match(
        script,
        /scope\.delegate\('click', '\[data-role="unlink-mapping"\]'[\s\S]*?unlinkMappingRow\(row\)[\s\S]*?autosave\.schedule\(\)/
    );
    assert.doesNotMatch(script, /function removeMappedSlot|function setSlotEmpty/);
    assert.match(script, /ergonode:language-activated/);
    assert.match(script, /scope\.listen\(root, languageActivatedEvent/);
    assert.match(script, /appendNewBadge\(row, 'ergo'\)/);
    assert.match(script, /left: left\.getAttribute\('data-code'\) \? \{code:/);
    assert.match(schema, /name="store_id"[^>]*nullable="true"/);
    assert.match(schema, /name="language_code"[^>]*nullable="true"/);
    assert.match(styles, /\.vel-pair-row::before\s*\{\s*display:\s*none;/);
    assert.match(styles, /\.vel-unlink-mapping\s*\{[\s\S]*?cursor:\s*pointer/);
    assert.doesNotMatch(styles, /\.vel-pair-row\s*\{[^}]*grid-template-columns/);
    assert.doesNotMatch(script, /scope\.delegate\('click', '\[data-role="entity-card"\]'/);
    assert.doesNotMatch(script, /var selected = \{ergo:/);
});

test('auto-match persists its batch once through autosave', () => {
    const script = read(adminRoot, 'view/adminhtml/web/js/language-mapping.js');
    const autosave = read(adminRoot, 'view/adminhtml/web/js/language-autosave.js');
    const matcherStart = script.indexOf('function findAutoMatches(root)');
    const matcherEnd = script.indexOf('function updateSourcePanel(root, panel)', matcherStart);
    const autoMatchStart = script.indexOf("scope.delegate('click', '[data-role=\"auto-match\"]'");
    const autoMatchEnd = script.indexOf("scope.delegate('click', '[data-role=\"refresh-ergonode\"]'", autoMatchStart);

    assert.notEqual(matcherStart, -1);
    assert.notEqual(matcherEnd, -1);
    assert.notEqual(autoMatchStart, -1);
    assert.notEqual(autoMatchEnd, -1);

    const matcher = script.slice(matcherStart, matcherEnd);
    const autoMatchHandler = script.slice(autoMatchStart, autoMatchEnd);

    assert.match(matcher, /return matches;/);
    assert.doesNotMatch(matcher, /request\.post|urls\.save/);
    assert.match(autoMatchHandler, /findAutoMatches\(root\)/);
    assert.match(script, /languageSourceOptions\.sync\(root, findAutoMatches\(root\)\.length\)/);
    assert.doesNotMatch(autoMatchHandler, /request\.post|urls\.save/);
    assert.equal((autoMatchHandler.match(/autosave\.schedule\(\)/g) || []).length, 1);
    assert.match(autosave, /request\.post\(config\.urls && config\.urls\.save/);
});
