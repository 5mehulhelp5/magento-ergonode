import '../../view/adminhtml/web/css/category-synchronization.css';
import { expect, userEvent, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CategoryAdminUi/view/adminhtml/web/css/category-tree-mapping.css';
import source from '../../view/adminhtml/web/js/category-sync-progress.js?raw';
import meterSource from '../../view/adminhtml/web/js/category-sync-progress-meter.js?raw';
import resultsSource from '../../view/adminhtml/web/js/category-sync-progress-results.js?raw';

let active = [];
let scheduled = [];

function storyJquery(element) {
    return {
        element,
        modal(action) {
            if (action === 'openModal' || action === 'closeModal') {
                this.shell.hidden = action === 'closeModal';
                this.shell.style.display = this.shell.hidden ? 'none' : 'block';
            }
        },
        closest() { return {remove: () => this.shell.remove()}; }
    };
}

function storyModal(options, widget) {
    const shell = document.createElement('div');
    const inner = document.createElement('div');
    const header = document.createElement('header');
    const title = document.createElement('h2');
    const content = document.createElement('div');

    shell.className = `modal-popup ${options.modalClass}`;
    shell.setAttribute('role', 'dialog');
    shell.setAttribute('aria-label', options.title);
    shell.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') options.keyEventHandlers.escapeKey();
    });
    inner.className = 'modal-inner-wrap';
    inner.style.background = 'white';
    header.className = 'modal-header';
    title.className = 'modal-title';
    title.textContent = options.title;
    content.className = 'modal-content';
    header.append(title);
    content.append(widget.element);
    inner.append(header, content);
    shell.append(inner);
    widget.shell = shell;
    widget.element.storyShell = shell;
}

const createProgress = loadAmdModule(source, {
    jquery: storyJquery,
    'Magento_Ui/js/modal/modal': storyModal,
    'mage/translate': (text) => text,
    'text!ui/template/modal/modal-popup.html': '<aside role="dialog"></aside>',
    'Ergonode_CategoryConsumerAdminUi/js/category-sync-progress-meter': loadAmdModule(meterSource),
    'Ergonode_CategoryConsumerAdminUi/js/category-sync-progress-results': loadAmdModule(resultsSource, {'mage/translate': (text) => text})
}, 'Ergonode_CategoryConsumerAdminUi/js/category-sync-progress');

const states = {
    running: null,
    downloading: null,
    estimating: null,
    validation: ['error', 'Required category creation attributes are not completely mapped: include_in_menu, is_active. Complete their Ergonode mappings or select Magento defaults in Stores > Configuration > Ergonode > Categories > Attributes.'],
    pausing: null,
    paused: ['paused', 'Synchronization paused. Completed changes are kept.'],
    success: ['success', 'Changes applied.'],
    upToDate: ['success', 'No new changes in Ergonode.'],
    conflicts: ['warning', 'Completed with 2 conflict(s).'],
    manyConflicts: ['warning', 'Completed with 1867 conflict(s).'],
    error: ['error', 'Ergonode is unavailable. Please try again later.'],
    unconfirmed: ['error', 'The synchronization result could not be confirmed. It may still be running. Check Synchronizations before retrying.']
};

function reset() {
    scheduled.forEach(clearTimeout);
    scheduled = [];
    active.forEach((progress) => progress.destroy());
    active = [];
}

function renderProgress(args) {
    const point = {stage: 'creating_category', tree_code: 'tghome_1', tree_number: 3, tree_total: 3,
        operations: {created: 1130, moved: 105, deleted: 12},
        processed: 1235, total: 1871, item: 'porcelana', pages: 3, downloaded: 1871};
    const progress = createProgress({
        pause: () => {
            progress.requestPause(true);
            progress.finish('paused', 'Synchronization paused. Completed changes are kept.');
        },
        resume: () => {
            progress.open(args.scope, args.force);
            progress.update(point);
        }
    });
    active.push(progress);
    progress.open(args.scope, args.force);
    if (args.scope !== 'data') {
        progress.update({stage: 'fetching_tree', tree_code: 'default', tree_number: 1, tree_total: 3});
        progress.update({stage: 'fetching_tree', tree_code: 'test', tree_number: 2, tree_total: 3});
    }
    progress.update(args.scope === 'data' ? {...point, stage: 'updating_category_data'} : point);
    if (args.state === 'estimating') {
        progress.update({...point, processed: 1169});
        scheduled.push(setTimeout(() => progress.update(point), 5500));
    }
    if (args.state === 'downloading') progress.update({stage: 'fetching_tree', tree_code: 'tghome_1',
        tree_number: 3, tree_total: 3, pages: 2, downloaded: 1400, total: null});
    if (args.state === 'conflicts') {
        progress.finish(...states.conflicts, {stats: {results: {tree: {tree_results: [{
            tree_code: 'tghome_1', stats: {created: 4, moved: 2, unmatched: 3}, conflict_count: 2,
            conflicts: ['Unable to reconcile category "porcelana": URL key already exists.',
                'Parent of Ergonode category "talerze" could not be resolved.']
        }]}}}});
        return progress;
    }
    if (args.state === 'manyConflicts') {
        const codes = ['sztucce', 'szklo', 'porcelana', 'mlynki', 'noze', 'garnki_i_patelnie',
            'akcesoria_kuchenne', 'akcesoria_barmanskie', 'kuchnia'];
        progress.finish(...states.manyConflicts, {stage: 'data_skipped', processed: 0, total: 0, stats: {results: {
            tree: {tree_results: [
                {tree_code: 'default', stats: {}, conflict_count: 0, conflicts: []},
                {tree_code: 'test', stats: {}, conflict_count: 0, conflicts: []},
                {tree_code: 'tghome_1', stats: {created: 16, unmatched: 1871}, conflict_count: 1867,
                    conflicts: ['Unable to reconcile category "dekoracje": Could not save category: URL key for specified store already exists.',
                        ...codes.map((code) => `Stored mapping for "${code}" points outside the configured Magento root.`)]}
            ]}, data: {skipped: true}
        }}});
        return progress;
    }
    if (args.state === 'pausing') progress.requestPause(true);
    if (states[args.state]) progress.finish(...states[args.state]);
    return progress;
}

export default {
    id: 'ergo-v-014-02',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-014 · Mapowanie drzewa kategorii/ERGO-V-014.02 · Postęp synchronizacji',
    tags: ['category-sync-progress'],
    args: {scope: 'all', force: false, state: 'running'},
    argTypes: {
        scope: {control: 'select', options: ['all', 'tree', 'data']},
        force: {control: 'boolean'},
        state: {control: 'select', options: Object.keys(states)}
    },
    render(args) {
        reset();
        return renderProgress(args).element.storyShell;
    },
    parameters: {a11y: {test: 'error'}}
};

export const Playground = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const bar = canvas.getByRole('progressbar', {name: 'Category synchronization progress'});
        await expect(bar).toBeVisible();
        await expect(bar).toHaveAttribute('aria-valuenow', '1235');
        await expect(bar).toHaveAttribute('aria-valuemax', '1871');
        await expect(canvas.getByText('Added to Magento')).toBeVisible();
        await expect(canvas.getByText('1130')).toBeVisible();
        await expect(canvas.getByText('105')).toBeVisible();
        await expect(canvas.getByText('12')).toBeVisible();
        await userEvent.click(canvas.getByRole('status'));
        await userEvent.keyboard('{Escape}');
        await expect(canvas.getByRole('dialog')).toBeVisible();
        await expect(canvas.queryByRole('button', {name: 'Close'})).toBeNull();
    }
};
export const TreeOnly = {args: {scope: 'tree'}};
export const CategoryData = {args: {scope: 'data'}};
export const Forced = {args: {scope: 'tree', force: true}};
export const UpToDate = {args: {state: 'upToDate'}};
export const Conflicts = {
    args: {state: 'conflicts'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByText('URL key already in use'));
        await expect(canvas.getByText('Unable to reconcile category "porcelana": URL key already exists.')).toBeVisible();
        await expect(canvas.getByText('Created: 4 · Moved: 2 · Deleted: 0 · Unmatched: 3')).toBeVisible();
    }
};
export const Failure = {args: {state: 'error'}};
export const UnconfirmedResult = {args: {state: 'unconfirmed'}};

export const Completion = {
    render(args) {
        reset();
        const host = document.createElement('div');
        const progress = renderProgress(args);
        const complete = document.createElement('button');
        complete.textContent = 'Simulate server completion';
        complete.addEventListener('click', () => progress.finish('success', 'Changes applied.'));
        host.append(progress.element.storyShell, complete);
        return host;
    },
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByRole('progressbar')).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: 'Simulate server completion'}));
        await expect(canvas.queryByRole('progressbar')).toBeNull();
        await expect(canvas.getByRole('status')).toHaveTextContent('Changes applied.');
        await userEvent.click(canvas.getByRole('button', {name: 'Close'}));
        await expect(canvas.queryByRole('dialog')).toBeNull();
    }
};

export const KeyboardClose = {
    args: {state: 'success'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('status'));
        await userEvent.keyboard('{Escape}');
        await expect(canvas.queryByRole('dialog')).toBeNull();
    }
};

export const StateMatrix = {
    render(args) {
        reset();
        const host = document.createElement('div');
        host.style.display = 'grid';
        host.style.gap = '24px';
        Object.keys(states).forEach((state) => {
            host.append(renderProgress({...args, state}).element.storyShell);
        });
        return host;
    }
};

export const PauseAndResume = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Processed: 1235 of 1871')).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: 'Pause'}));
        await expect(canvas.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '1235');
        await expect(canvas.getByText('Estimate paused')).toBeVisible();
        await expect(canvas.getByRole('status')).toHaveTextContent('Completed changes are kept.');
        const resume = canvas.getByRole('button', {name: 'Resume'});
        resume.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('progressbar')).toBeVisible();
        await expect(canvas.queryByRole('button', {name: 'Resume'})).toBeNull();
    }
};

export const Downloading = {
    args: {state: 'downloading'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Downloaded: 1400 categories · 2 pages')).toBeVisible();
        await expect(canvas.getByText('Tree 3 of 3: tghome_1')).toBeVisible();
        await expect(canvas.getByRole('progressbar')).not.toHaveAttribute('aria-valuenow');
    }
};
export const InvalidConfiguration = {args: {state: 'validation'}};

export const ManyConflicts = {
    args: {state: 'manyConflicts'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const activeTree = canvas.getByRole('button', {name: /tghome_1.*1867/});
        await expect(activeTree).toHaveAttribute('aria-pressed', 'true');
        await expect(canvas.queryByText('Processed: 0 of 0')).toBeNull();
        const group = canvas.getByText('Mapping outside Magento root').closest('summary');
        await userEvent.click(group);
        await expect(canvas.getByText('Stored mapping for "sztucce" points outside the configured Magento root.')).toBeVisible();
        await expect(canvas.getByText(/Showing 10 of 1867 conflicts/)).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: /default.*Completed/}));
        await expect(canvas.getByText('No conflicts.')).toBeVisible();
        activeTree.focus();
        await userEvent.keyboard('{Enter}');
        await expect(activeTree).toHaveAttribute('aria-pressed', 'true');
    }
};

export const EstimatedTime = {
    args: {state: 'estimating'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(await canvas.findByText(/About .* remaining/, {}, {timeout: 8000})).toBeVisible();
        await expect(canvas.getByText('Estimate for this stage')).toBeVisible();
        await expect(canvas.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '1235');
    }
};
