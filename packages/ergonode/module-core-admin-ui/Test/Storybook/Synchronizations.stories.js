import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/ergonode-actions.css';
import '../../view/adminhtml/web/css/synchronizations.css';
import synchronizationsSource from '../../view/adminhtml/web/js/synchronizations.js?raw';

const activeRows = [
    {
        label: 'Attributes',
        description: 'Imports Ergonode attribute changes and refreshes shared attribute and option snapshots.',
        processCode: 'attributeStream',
        cursor: 'Z3JhcGhxbF9hcGlfc3RyZWFtO2M6OTk7ZToxOTE=',
        syncedAt: '4 Sep 2026, 10:42'
    },
    {
        label: 'Category trees',
        description: 'Consumes category-tree changes and reconciles active Ergonode trees with Magento roots.',
        processCode: 'category_tree_stream',
        cursor: null,
        syncedAt: null
    }
];
const templateRow = {
    label: 'Templates',
    description: 'Imports Ergonode templates and updates their Magento attribute-set mappings.',
    processCode: 'template_stream',
    cursor: null,
    syncedAt: '4 Sep 2026, 11:08'
};

const checkRow = {
    label: 'Attribute definition checks',
    description: 'Checks attributeStream and attributeDeletedStream before dependent imports. Refreshes the complete shared snapshot when changes are detected.',
    processCode: 'attributeDefinitionCheck',
    monitorOnly: true
};

function renderRow(row) {
    const tr = document.createElement('tr');

    tr.dataset.processCode = row.processCode;
    tr.innerHTML = `
        <td class="ves-synchronizations-select"><input type="checkbox" value="${row.processCode}" data-role="process-select" aria-label="Mark synchronization: ${row.label}" ${row.monitorOnly ? 'disabled' : ''}></td>
        <th scope="row"><strong>${row.label}</strong>
            <code>${row.processCode.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase())}</code>
            <span>${row.description}</span>
            ${row.monitorOnly ? '<span>Information only — runs with dependent imports.</span>' : `<details class="ves-monitor-cursor"><summary>Cursor</summary>
                <code data-role="process-cursor">${row.cursor || ''}</code>
                <span>An empty cursor does not determine synchronization success.</span></details>`}</th>
        ${row.monitorOnly ? `<td colspan="2"><dl class="ves-monitor-observation" data-role="observation">
            <dt>Last check started</dt><dd><time data-role="check-started-at">Checking…</time></dd>
            <dt>Result</dt><dd data-role="check-status">Checking…</dd>
            <dt>Completed</dt><dd><time data-role="check-completed-at">Checking…</time></dd>
            <dt>Last change detected</dt><dd><time data-role="check-changed-at">Checking…</time></dd>
        </dl></td>` : `<td><time data-role="synced-at">Checking…</time></td>
        <td><time data-role="reset-at">Checking…</time></td>`}`;

    return tr;
}

function renderSynchronizations({state = 'active', observation = 'no_changes'} = {}) {
    const workspace = document.createElement('div');
    const content = document.createElement('div');
    const panel = document.createElement('section');
    const rows = state === 'empty'
        ? []
        : state === 'with-template' ? [...activeRows, checkRow, templateRow] : [...activeRows, checkRow];

    workspace.className = 'veui-workspace veui-workspace-viewbar ves-synchronizations';
    workspace.style.height = '620px';
    content.className = 'ves-synchronizations-content';
    panel.className = 'veui-panel ves-synchronizations-panel';
    panel.setAttribute('aria-labelledby', `storybook-synchronizations-title-${state}`);
    panel.innerHTML = `
        <header class="veui-panel-head ves-synchronizations-panel-head">
            <div class="ves-synchronizations-panel-heading">
                <span class="ves-synchronizations-summary-icon" aria-hidden="true"></span>
                <div>
                    <h2 id="storybook-synchronizations-title-${state}">Synchronization processes: ${state}</h2>
                    <p>Only processes registered by enabled Ergonode modules are displayed.</p>
                </div>
            </div>
            <div class="ves-synchronizations-panel-actions">
                <span class="ves-synchronizations-count">${rows.length} available</span>
                <button type="button" class="veui-button ves-synchronizations-action" data-role="reset-selected" disabled>
                    <span class="veui-reset-cursor-icon" aria-hidden="true"></span>Reset cursor
                </button>
                <button type="button" class="veui-button veui-button-primary ves-synchronizations-action" data-role="run-selected" disabled>
                    <span class="veui-sync-ergonode-icon" aria-hidden="true"></span>Synchronize
                </button>
            </div>
        </header>
        <div class="veui-message ves-synchronizations-message" data-role="synchronization-message" aria-live="polite" hidden>
            <span class="ves-synchronizations-message-dot" aria-hidden="true"></span>
            <span data-role="synchronization-message-text"></span>
        </div>`;

    if (rows.length === 0) {
        panel.insertAdjacentHTML('beforeend', `
            <div class="ves-synchronizations-empty" role="status">
                <span aria-hidden="true"></span>
                <h3>No synchronization processes are available.</h3>
                <p>Enable an Ergonode consumer module to make its synchronization available.</p>
            </div>`);
    } else {
        const wrap = document.createElement('div');
        const table = document.createElement('table');
        const body = document.createElement('tbody');

        wrap.className = 'ves-synchronizations-table-wrap';
        table.className = 'ves-synchronizations-table';
        table.innerHTML = `
            <caption class="ves-visually-hidden">Available Ergonode synchronizations</caption>
            <thead><tr>
                <th class="ves-synchronizations-select" scope="col"><input type="checkbox" data-role="select-all-processes" aria-label="Mark all synchronization processes"></th>
                <th scope="col">Synchronization</th><th scope="col">Last cursor update</th><th scope="col">Last cursor reset</th>
            </tr></thead>`;
        rows.forEach((row) => body.append(renderRow(row)));
        table.append(body);
        wrap.append(table);
        panel.append(wrap);
    }

    content.append(panel);
    workspace.append(content);
    const freshness = document.createElement('p');
    freshness.className = 'ves-monitor-freshness';
    freshness.dataset.role = 'monitor-freshness';
    freshness.setAttribute('role', 'status');
    panel.querySelector('header').after(freshness);
    const initializeLive = loadAmdModule(synchronizationsSource, {
        'mage/translate': (value) => value,
        'Ergonode_CoreAdminUi/js/request': {post: () => {
            return state === 'offline'
            ? Promise.reject(new Error('Connection unavailable'))
            : Promise.resolve({success: true, observed_at: '2026-09-05T10:01:00Z', processes: rows.map((row) => ({
                process_code: row.processCode, cursor: row.cursor,
                reset_at: '2026-09-04 09:00:00', synced_at: row.syncedAt ? '2026-09-04 10:42:00' : null,
                observation: row.monitorOnly ? {
                    status: observation,
                    started_at: observation === 'not_checked' ? null : '2026-09-11 10:00:00',
                    completed_at: ['not_checked', 'running', 'changes_detected'].includes(observation) ? null : '2026-09-11 10:00:03',
                    changed_at: ['not_checked', 'initialized'].includes(observation) ? null : '2026-09-10 09:00:00'
                } : undefined
            }))});
        }}
    }, 'Ergonode_CoreAdminUi/js/synchronizations');
    initializeLive({
        form_key: 'storybook-form-key',
        urls: {run: '/synchronize', reset: '/reset', status: '/status'}
    }, workspace);

    return workspace;
}

const meta = {
    id: 'ergo-v-036',
    title: 'Ergonode UI/Widoki/Wspólne/ERGO-V-036 · Synchronizacje/Pełny widok',
    tags: ['autodocs'],
    render: renderSynchronizations,
    args: {state: 'active', observation: 'no_changes'},
    argTypes: {
        observation: {control: 'select', options: ['not_checked', 'running', 'changes_detected', 'initialized', 'no_changes', 'changed', 'failed']},
        state: {
            control: 'select',
            options: ['active', 'with-template', 'empty', 'offline']
        }
    }
};

export default meta;

export const Playground = {};

export const Stany = {
    render: () => {
        const grid = document.createElement('div');

        grid.className = 'veui-storybook-grid';
        ['active', 'with-template', 'empty', 'offline'].forEach((state) => grid.append(renderSynchronizations({state})));

        return grid;
    }
};

export const ZaznaczanieMysza = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const checkbox = canvas.getByRole('checkbox', {name: 'Mark synchronization: Attributes'});
        const synchronize = canvas.getByRole('button', {name: 'Synchronize'});
        const reset = canvas.getByRole('button', {name: 'Reset cursor'});

        await expect(synchronize).toBeDisabled();
        await userEvent.click(checkbox);
        await expect(synchronize).toBeEnabled();
        await expect(reset).toBeEnabled();
    }
};

export const Dostepnosc = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByRole('table', {name: 'Available Ergonode synchronizations'}))
            .toBeInTheDocument();
        await expect(canvas.getByRole('checkbox', {name: 'Mark all synchronization processes'}))
            .toBeInTheDocument();
        await expect(canvas.getByRole('rowheader', {name: /Attributes/})).toBeInTheDocument();
    }
};

export const CursorStatus = {play: async ({canvasElement}) => {
    const canvas = within(canvasElement);
    await expect(await canvas.findByText(/^Live updates every 2 seconds/)).toBeInTheDocument();
    await expect(canvasElement.querySelector('[data-role="synced-at"]')).not.toHaveTextContent('Checking…');
    await expect(canvas.getByText('No recorded data', {selector: '[data-role="synced-at"]'})).toBeInTheDocument();
}};
export const KeyboardSelection = {play: async ({canvasElement}) => {
    const canvas = within(canvasElement);
    canvas.getByRole('checkbox', {name: 'Mark synchronization: Attributes'}).focus();
    await userEvent.keyboard('[Space]');
    await expect(canvas.getByRole('button', {name: 'Synchronize'})).toBeEnabled();
}};

export const CheckStates = {
    render: () => {
        const grid = document.createElement('div');
        grid.className = 'veui-storybook-grid';
        ['not_checked', 'running', 'changes_detected', 'initialized', 'no_changes', 'changed', 'failed'].forEach((observation) => {
            grid.append(renderSynchronizations({state: observation, observation}));
        });
        return grid;
    }
};

export const InformationalCheck = {play: async ({canvasElement}) => {
    const canvas = within(canvasElement);
    await expect(await canvas.findByText('No changes')).toBeInTheDocument();
    await expect(canvas.getByRole('checkbox', {name: 'Mark synchronization: Attribute definition checks'})).toBeDisabled();
    const row = canvasElement.querySelector('[data-process-code="attributeDefinitionCheck"]');
    await expect(row.querySelector('[data-role="process-cursor"]')).toBeNull();
    await expect(row.querySelector('[data-role="check-started-at"]')).not.toHaveTextContent('Checking…');
}};

export const FailedCheck = {args: {observation: 'failed'}, play: async ({canvasElement}) => {
    const canvas = within(canvasElement);
    await expect(await canvas.findByText('Failed')).toHaveClass('is-error');
    await expect(canvasElement.querySelector('[data-role="check-changed-at"]')).not.toHaveTextContent('No recorded data');
}};
