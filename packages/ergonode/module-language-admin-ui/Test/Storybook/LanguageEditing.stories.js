import {expect, userEvent, waitFor} from 'storybook/test';
import {createMappingWorkspace} from '@ergonode-storybook/mapping-workspace.js';
import {createCoreModuleLoader} from '@ergonode-storybook/core-modules.js';
import {createEntityItem} from '@ergonode-storybook/entity-item.js';
import {loadAmdModule, translateIdentity} from '@ergonode-storybook/load-amd-module.js';
import {createLanguageLoader, decorateStoreViewCard} from './language-fixture.js';
import ergonodeMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/m2_configuration.svg';
import magentoMark from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/images/magento-mark.svg';
import mappingSource from '../../view/adminhtml/web/js/language-mapping.js?raw';
import autosaveSource from '../../view/adminhtml/web/js/language-autosave.js?raw';
import optionsSource from '../../view/adminhtml/web/js/language-source-options.js?raw';

function prepareLanguagePresentation(root, canSave, viewState) {
    root.id = 'ergonode-language-mapping';
    root.prepend(createLanguageLoader());

    for (const [side, mark] of [['ergo', ergonodeMark], ['magento', magentoMark]]) {
        const panel = root.querySelector(`[data-source-panel="${side}"]`);
        const title = panel.querySelector('.veui-panel-title');
        const icon = document.createElement('span');
        const image = document.createElement('img');
        icon.className = `veui-brand-mark veui-brand-${side === 'ergo' ? 'ergonode' : 'magento'}`;
        icon.setAttribute('aria-hidden', 'true');
        image.src = mark;
        image.alt = '';
        icon.append(image);
        title.prepend(icon);
        title.lastChild.textContent = 'Languages';
        panel.querySelector('[data-role="source-list"]').classList.add('vel-source-list');
    }
    const storePanel = root.querySelector('[data-source-panel="magento"]');
    const storeCount = storePanel.querySelector('.vea-count');
    storeCount.dataset.role = 'store-mapping-count';
    storeCount.textContent = '1 / 1';
    const store = storePanel.querySelector('[data-role="entity-card"]');
    decorateStoreViewCard(store, {
        id: '0', code: 'admin', label: 'Default Store View', locale: 'pl_PL',
        scope: 'Main Website / Main Store', required: true, mapped: viewState !== 'no-mappings'
    });
    root.querySelectorAll('[data-source-panel="ergo"] [data-role="entity-card"]')
        .forEach((card) => card.classList.add('vel-source-card'));
    const mappingPanel = root.querySelector('[data-role="mapping-panel"]');
    mappingPanel.classList.add('vea-pairs-panel');
    const mappingList = mappingPanel.querySelector('[data-role="mapping-list"]');
    mappingList.classList.add('vel-pair-list');
    mappingList.querySelectorAll('[data-role="mapping-row"]').forEach((row) => {
        row.classList.add('vel-pair-row');
        const right = row.querySelector('[data-role="pair-slot"][data-side="magento"]');
        right.dataset.storeCode = 'admin';
        right.dataset.locale = 'pl_PL';
        right.querySelector('.vea-card-subline code').textContent = 'admin';
        const locale = document.createElement('span');
        locale.className = 'vea-type-badge vea-type-store-view';
        locale.textContent = 'pl_PL';
        right.querySelector('.vea-card-subline').append(locale);
    });
    const empty = document.createElement('p');
    empty.className = 'vel-empty-state';
    empty.dataset.role = 'mapping-empty';
    empty.textContent = 'Nie ma jeszcze mapowań. Wybierz element z lewej i z prawej strony.';
    empty.hidden = viewState !== 'no-mappings';
    mappingList.append(empty);
    if (viewState === 'no-mappings') mappingList.querySelector('[data-role="mapping-row"]').remove();
    if (viewState === 'no-languages') {
        root.querySelector('[data-source-panel="ergo"] [data-role="source-list"]').replaceChildren();
        const languageEmpty = document.createElement('div');
        languageEmpty.className = 'vel-empty-state';
        languageEmpty.dataset.role = 'language-empty-state';
        languageEmpty.innerHTML = '<p>Brak pobranych języków Ergonode.</p>';
        root.querySelector('[data-source-panel="ergo"] [data-role="source-list"]').append(languageEmpty);
    }
    if (!canSave) root.querySelectorAll('.vea-drag-handle').forEach((handle) => { handle.hidden = true; });
}

function renderEditor({canSave = true, phase = 'idle', languageState = 'active', draftIds = null,
    extraStores = 0, includeEnglish = false,
    viewState = 'mapped'} = {}) {
    // Clone only the composition HTML: its demo event listeners must not run alongside production AMD.
    const root = createMappingWorkspace({view: 'language', state: 'mapped', embedded: true}).cloneNode(true);
    prepareLanguagePresentation(root, canSave, viewState);
    const calls = [];
    const saves = [];
    const metrics = {updates: 0};
    let rejectRefresh;
    let rejectRemoval;
    const request = {post(url, config, data) {
        calls.push(url);
        if (url === '/save') saves.push({payload: JSON.parse(data.payload), updates: metrics.updates});
        if (url === '/delete') return new Promise((resolve, reject) => { rejectRemoval = reject; });
        if (url === '/refresh') return new Promise((resolve, reject) => { rejectRefresh = reject; });
        return Promise.resolve({success: true, revision: 'b'.repeat(64)});
    }};
    const core = createCoreModuleLoader({'Ergonode_CoreAdminUi/js/request': request});
    const dependencies = {'mage/translate': translateIdentity};
    for (const name of ['workspace', 'text', 'workspace-context', 'request', 'buttons', 'visibility-toggle',
        'source-bulk-transfer', 'entity-options', 'mapping-requirements', 'snapshot-removal', 'autosave']) {
        dependencies[`Ergonode_CoreAdminUi/js/${name}`] = core(`Ergonode_CoreAdminUi/js/${name}`);
    }
    const requirements = dependencies['Ergonode_CoreAdminUi/js/mapping-requirements'];
    dependencies['Ergonode_CoreAdminUi/js/mapping-requirements'] = {create(...args) {
        const delegate = requirements.create(...args);
        return {...delegate, refresh(...params) {
            metrics.updates++;
            return delegate.refresh(...params);
        }};
    }};
    const storeList = root.querySelector('[data-source-panel="magento"] [data-role="source-list"]');
    const languageList = root.querySelector('[data-source-panel="ergo"] [data-role="source-list"]');
    if (includeEnglish) {
        const card = createEntityItem({
            source: 'ergo', code: 'en_US', label: 'English', bulkSelection: true
        });
        card.classList.add('vel-source-card');
        languageList.append(card);
    }
    for (let id = 1; id <= extraStores; id++) {
        storeList.append(decorateStoreViewCard(createEntityItem({
            source: 'magento', code: String(id), label: `Store View ${id}`, bulkSelection: true
        }), {
            id: String(id), code: `store_${id}`, label: `Store View ${id}`,
            locale: 'en_US', scope: 'Main Website / Main Store'
        }));
    }
    if (draftIds) {
        const list = root.querySelector('[data-role="mapping-list"]');
        const sample = list.querySelector('[data-role="mapping-row"]');
        const drafts = draftIds.map((id) => {
            const row = sample.cloneNode(true);
            const left = row.querySelector('[data-side="ergo"]');
            left.dataset.code = '';
            left.dataset.label = '';
            left.classList.add('is-empty');
            left.textContent = 'Upuść język';
            const right = row.querySelector('[data-side="magento"]');
            right.dataset.code = id;
            right.dataset.label = `Store View ${id}`;
            right.textContent = `Store View ${id}`;
            return row;
        });
        list.replaceChildren(...drafts);
    }
    dependencies['Ergonode_LanguageAdminUi/js/language-autosave'] = loadAmdModule(autosaveSource, dependencies);
    dependencies['Ergonode_LanguageAdminUi/js/language-source-options'] = loadAmdModule(optionsSource, dependencies);
    root.dataset.storybookBehavior = 'production-language-mapping';
    root.querySelectorAll('.vea-side-tools').forEach((tools) => {
        tools.classList.add('vel-side-tools');
        tools.querySelectorAll('details').forEach((menu) => menu.remove());
    });
    root.querySelector('[data-source-panel="ergo"]').dataset.canRefresh = '1';
    root.querySelectorAll('[data-role="entity-card"]').forEach((card) => {
        card.setAttribute('draggable', canSave ? 'true' : 'false');
        if (!canSave) card.querySelectorAll('[data-role="source-bulk-selection"]').forEach((node) => node.remove());
    });
    const language = root.querySelector('[data-source="ergo"][data-code="pl_PL"]');
    if (languageState === 'removed') {
        const slot = root.querySelector('[data-role="pair-slot"][data-side="ergo"]');
        slot.dataset.code = 'removed_LANGUAGE';
        slot.querySelector('strong').textContent = 'Removed language';
    }
    if (languageState === 'excluded' && language) {
        language.querySelector('[data-role="source-active-toggle"]').setAttribute('aria-pressed', 'false');
    }
    if (!canSave) {
        root.querySelectorAll('[data-role="source-active-toggle"], [data-role="unlink-mapping"]')
            .forEach((button) => { button.disabled = true; });
        root.querySelector('[data-language-mapping-options]')?.remove();
    }
    loadAmdModule(mappingSource, dependencies)({
        canSave, revision: 'a'.repeat(64), urls: {save: '/save', refresh: '/refresh', delete_snapshot: '/delete', configuration: '/configuration'}
    }, root);
    root.languageStory = {
        calls,
        saves,
        metrics,
        failRemoval: () => rejectRemoval(new Error('Controlled removal failure')),
        failRefresh: () => rejectRefresh(new Error('Controlled refresh failure')),
    };
    if (phase !== 'idle') {
        root.querySelector('[data-role="refresh-ergonode"]').click();
        if (phase === 'failed') queueMicrotask(() => root.languageStory.failRefresh());
    }
    if (viewState === 'saving') {
        root.dataset.languageBusy = 'true';
        root.setAttribute('aria-busy', 'true');
        root.querySelector('[data-role="language-loader"]').hidden = false;
    }
    return root;
}

export default {
    id: 'ergo-v-040',
    title: 'Ergonode UI/Widoki/Języki/ERGO-V-040 · Edycja i odświeżanie języków/Pełny widok',
    render: renderEditor,
    args: {canSave: true, phase: 'idle', viewState: 'mapped'},
    argTypes: {
        canSave: {control: 'boolean'},
        phase: {control: 'select', options: ['idle', 'refreshing', 'failed']},
        viewState: {control: 'select', options: ['mapped', 'no-languages', 'no-mappings', 'saving']}
    },
};

export const Playground = {
    play: async ({canvasElement}) => {
        const store = canvasElement.querySelector('[data-source="magento"][data-code="0"]');
        await expect(store).toHaveAttribute('data-store-code', 'admin');
        await expect(store).toHaveAttribute('data-locale', 'pl_PL');
        await expect(store.querySelector('.vea-type-store-view')).toHaveTextContent('pl_PL');
        await expect(store.querySelector('.vea-scope')).toHaveTextContent('Main Website / Main Store');
        await expect(store.querySelector('.veui-required-badge')).toHaveTextContent('* Wymagane');
        await expect(store.querySelector('[role="tooltip"]')).toBeInTheDocument();
    },
};
export const Odczyt = {args: {canSave: false}};
export const Odswiezanie = {args: {phase: 'refreshing'}};
export const BladOdswiezania = {args: {phase: 'failed'}};
export const BrakPobranychJezykow = {
    args: {viewState: 'no-languages'},
    play: async ({canvasElement}) => {
        const panel = canvasElement.querySelector('[data-source-panel="ergo"]');
        await expect(panel.querySelector('[data-role="language-empty-state"]')).toBeVisible();
        await expect(panel.querySelector('[data-role="entity-card"]')).toBeNull();
    },
};
export const BrakMapowan = {
    args: {viewState: 'no-mappings'},
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        await expect(root.querySelector('[data-role="mapping-empty"]')).toBeVisible();
        await expect(root.querySelector('[data-role="mapping-row"]')).toBeNull();
        await expect(root.querySelector('[data-source="magento"][data-code="0"]'))
            .toHaveAttribute('aria-invalid', 'true');
    },
};
export const Zapisywanie = {
    args: {viewState: 'saving'},
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        await expect(root.querySelector('[data-role="language-loader"]')).toBeVisible();
        await expect(root).toHaveAttribute('aria-busy', 'true');
    },
};
export const AutomatyczneDopasowaniePoLocale = {
    args: {includeEnglish: true, extraStores: 1},
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        const menu = root.querySelector('[data-language-mapping-options]');
        const autoMatch = menu.querySelector('[data-role="auto-match"]');

        await waitFor(() => expect(autoMatch).toBeEnabled());
        await userEvent.click(menu.querySelector('summary'));
        await userEvent.click(autoMatch);
        await waitFor(() => expect(root.languageStory.saves).toHaveLength(1));
        const mappings = root.languageStory.saves[0].payload.mappings;
        await expect(mappings).toEqual(expect.arrayContaining([
            expect.objectContaining({
                left: expect.objectContaining({code: 'en_US'}),
                right: expect.objectContaining({code: '1'})
            })
        ]));
        await expect(root.querySelector('[data-source="magento"][data-code="1"]')).toHaveClass('is-mapped');
    },
};

export const ViewerMyszIKlawiatura = {
    args: {canSave: false},
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        const unlink = root.querySelector('[data-role="unlink-mapping"]');
        const card = root.querySelector('[data-role="entity-card"]');
        await expect(unlink).toBeDisabled();
        await userEvent.click(unlink);
        await userEvent.dblClick(card);
        card.focus();
        await userEvent.keyboard('{Enter}');
        await expect(root.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(1);
        await expect(root.querySelector('[data-role="entity-delete-snapshot"]')).toBeNull();
        await expect(root.querySelector('[data-role="source-bulk-add-to-mapping"]')).toBeNull();
        const search = root.querySelector('[data-role="source-search"]');
        await userEvent.type(search, 'no matching language');
        await expect(card).not.toBeVisible();
        await expect(root.languageStory.calls).toEqual([]);
    },
};

export const RefreshBlokujeEdycjeIOdblokowujePoBledzie = {
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        const sourceMenu = root.querySelector('[data-source-panel="ergo"] details');
        await userEvent.click(sourceMenu.querySelector('summary'));
        await userEvent.click(sourceMenu.querySelector('[data-role="refresh-ergonode"]'));
        await waitFor(() => expect(root.languageStory.calls).toEqual(['/refresh']));
        await expect(root).toHaveAttribute('inert');
        const unlink = root.querySelector('[data-role="unlink-mapping"]');
        unlink.focus();
        await expect(unlink).not.toHaveFocus();
        // Native hit-testing, rather than a synthetic click, verifies inert also prevents mouse input.
        const bounds = unlink.getBoundingClientRect();
        const hit = document.elementFromPoint(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2);
        await expect(root.contains(hit)).toBe(false);
        root.languageStory.failRefresh();
        await waitFor(() => expect(root).not.toHaveAttribute('inert'));
        unlink.focus();
        await expect(unlink).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await waitFor(() => expect(root.languageStory.calls).toEqual(['/refresh', '/save']));
        await expect(root.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(0);
    },
};


export const UsuwanieBlokujeEdycje = {
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        const confirm = window.confirm;
        window.confirm = () => true;
        try {
            const menu = root.querySelector('[data-source="ergo"] [data-role="entity-options"]');
            await userEvent.click(menu.querySelector('summary'));
            await userEvent.click(menu.querySelector('[data-role="entity-delete-snapshot"]'));
            await waitFor(() => expect(root.languageStory.calls).toEqual(['/delete']));
            await expect(root).toHaveAttribute('inert');
            const unlink = root.querySelector('[data-role="unlink-mapping"]');
            unlink.focus();
            await expect(unlink).not.toHaveFocus();
            const bounds = unlink.getBoundingClientRect();
            await expect(root.contains(document.elementFromPoint(bounds.x + bounds.width / 2,
                bounds.y + bounds.height / 2))).toBe(false);
            root.languageStory.failRemoval();
            await waitFor(() => expect(root).not.toHaveAttribute('inert'));
            unlink.focus();
            await userEvent.keyboard('{Enter}');
            await waitFor(() => expect(root.languageStory.calls).toEqual(['/delete', '/save']));
        } finally {
            window.confirm = confirm;
        }
    },
};

async function verifyInactive({canvasElement}) {
    const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
    const row = root.querySelector('[data-role="mapping-row"]');
    await expect(row).toHaveAttribute('data-mapping-active', 'false');
    await expect(row.querySelector('[data-role="mapping-inactive"]')).toBeVisible();
    await expect(root).toHaveClass('has-missing-mapping-requirements');
    await expect(root.languageStory.calls).toEqual([]);
    const unlink = row.querySelector('[data-role="unlink-mapping"]');
    unlink.focus();
    await userEvent.keyboard('{Enter}');
    await waitFor(() => expect(root.languageStory.calls).toEqual(['/save']));
    await expect(root.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(0);
}

export const UsunietyJezyk = {
    args: {languageState: 'removed'},
    play: async (context) => {
        await verifyInactive(context);
        const root = context.canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        const language = root.querySelector('[data-source="ergo"][data-code="pl_PL"]');
        language.focus();
        await userEvent.keyboard('{Enter}');
        await waitFor(() => expect(root.languageStory.calls).toHaveLength(2));
        await waitFor(() => expect(root.querySelector('[data-role="autosave-region"]')).not.toHaveAttribute('inert'));
        const store = root.querySelector('[data-source="magento"][data-code="0"]');
        store.focus();
        await userEvent.keyboard('{Enter}');
        await waitFor(() => expect(root.languageStory.calls).toHaveLength(3));
        await expect(root.querySelector('[data-role="mapping-row"]')).toHaveAttribute('data-mapping-active', 'true');
        await expect(root.querySelector('[data-role="mapping-inactive"]')).toBeNull();
        await expect(root).not.toHaveClass('has-missing-mapping-requirements');
    },
};
export const WylaczonyJezyk = {args: {languageState: 'excluded'}, play: verifyInactive};

async function moveDraft({canvasElement}, targetId = '2') {
    const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
    const source = root.querySelector('[data-source="magento"][data-code="1"]');
    const target = root.querySelector(`[data-role="pair-slot"][data-side="magento"][data-code="${targetId}"]`);
    const dataTransfer = new DataTransfer();
    source.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer}));
    target.dispatchEvent(new DragEvent('dragover', {bubbles: true, cancelable: true, dataTransfer}));
    target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer}));
    source.dispatchEvent(new DragEvent('dragend', {bubbles: true, dataTransfer}));
    await waitFor(() => expect(root.languageStory.saves).toHaveLength(1));
    const ids = root.languageStory.saves[0].payload.mappings.map((row) => row.right.code);
    await expect(ids).toEqual(targetId === '1' ? ['2', '1'] : ['1']);
    await expect(new Set(ids).size).toBe(ids.length);
    await expect(source).toHaveClass('is-mapped');
    if (targetId !== '1') {
        await expect(root.querySelector('[data-source="magento"][data-code="2"]')).not.toHaveClass('is-mapped');
    }
}

export const SzkicZrodloZaCelem = {args: {draftIds: ['2', '1'], extraStores: 2}, play: moveDraft};
export const SzkicZrodloPrzedCelem = {args: {draftIds: ['1', '2'], extraStores: 2}, play: moveDraft};
export const SzkicWlasnyCel = {
    args: {draftIds: ['2', '1'], extraStores: 2},
    play: (context) => moveDraft(context, '1'),
};

export const GrupaPrzeliczanaRaz = {
    args: {extraStores: 3},
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        const panel = root.querySelector('[data-source-panel="magento"]');
        for (const id of ['1', '2', '3']) {
            await userEvent.click(panel.querySelector(`[data-code="${id}"] [data-role="source-bulk-select"]`));
        }
        await userEvent.click(panel.querySelector('[data-source-options] summary'));
        const updates = root.languageStory.metrics.updates;
        await userEvent.click(panel.querySelector('[data-role="source-bulk-add-to-mapping"]'));
        await waitFor(() => expect(root.languageStory.saves).toHaveLength(1));
        const saved = root.languageStory.saves[0];
        await expect(saved.updates - updates).toBe(1);
        await expect(saved.payload.mappings.map((row) => row.right.code)).toEqual(['1', '2', '3', '0']);
        await expect(root.querySelectorAll('[data-role="mapping-row"]')).toHaveLength(4);
    },
};

export const SzukaniePoNazwieIKodzie = {
    play: async ({canvasElement}) => {
        const root = canvasElement.querySelector('[data-storybook-behavior="production-language-mapping"]');
        const panel = root.querySelector('[data-source-panel="ergo"]');
        const search = panel.querySelector('[data-role="source-search"]');
        const card = panel.querySelector('[data-code="pl_PL"]');
        for (const query of ['Polski', 'pl_PL']) {
            await userEvent.clear(search);
            await userEvent.type(search, query);
            await expect(card).toBeVisible();
        }
        await userEvent.clear(search);
        await userEvent.type(search, 'Deutsch');
        await expect(card).not.toBeVisible();
        await expect(root.languageStory.calls).toEqual([]);
    },
};
