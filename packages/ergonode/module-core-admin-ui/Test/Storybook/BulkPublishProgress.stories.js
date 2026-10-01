import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '../../view/adminhtml/web/css/ergonode-workspace.css';
import '../../view/adminhtml/web/css/bulk-publish-progress.css';
import source from '../../view/adminhtml/web/js/bulk-publish-progress.js?raw';

function storyJquery(element) {
    return {
        element,
        remove() {element.remove();},
        modal(action, option, value) {
            if (action === 'option') { return value === undefined ? !this.shell.hidden : this; }
            if (action === 'openModal') {
                this.shell.hidden = false;
                this.element.style.display = 'grid';
            }
            if (action === 'closeModal') {
                this.shell.hidden = true;
            }
            return this;
        }
    };
}

function storyModal(options, widget) {
    const shell = document.createElement('section');
    const inner = document.createElement('div');
    const header = document.createElement('header');
    const title = document.createElement('h2');

    shell.className = `modal-popup ${options.modalClass}`;
    shell.hidden = true;
    shell.setAttribute('role', 'dialog');
    shell.setAttribute('aria-label', options.title);
    inner.className = 'modal-inner-wrap';
    header.className = 'modal-header';
    title.className = 'modal-title';
    title.textContent = options.title;
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'action-close';
    close.dataset.role = 'closeBtn';
    close.textContent = '×';
    close.setAttribute('aria-label', 'Close');
    header.append(title, close);
    const content = document.createElement('div');
    content.className = 'modal-content';
    content.append(widget.element);
    inner.append(header, content);
    if (options.modalCloseBtn !== false) {
        inner.querySelector(options.modalCloseBtn || '[data-role="closeBtn"]').addEventListener('click',
            options.modalCloseBtnHandler || (() => widget.modal('closeModal')));
    }
    shell.addEventListener('keydown', event => {
        if (event.key === 'Escape') { options.keyEventHandlers?.escapeKey?.(); }
    });
    shell.append(inner);
    widget.shell = shell;
    widget.element.storyModalShell = shell;
}

const createProgress = loadAmdModule(source, {
    jquery: storyJquery,
    'Magento_Ui/js/modal/modal': storyModal,
    'mage/translate': (value) => value
}, 'Ergonode_CoreAdminUi/js/bulk-publish-progress');

const labels = {
    attribute: {
        title: 'Tworzenie atrybutów w Ergonode',
        progressLabel: 'Postęp tworzenia atrybutów',
        unit: 'atrybutów',
        processingBatch: 'Przetwarzam paczkę %1 z %2 (%3 atrybutów).',
        complete: 'Wszystkie atrybuty zostały utworzone i zapisane.',
        partial: 'Poprawne atrybuty zapisano, a błędy pozostawiono do ponowienia.'
    },
    categoryAttribute: {
        title: 'Tworzenie atrybutów kategorii w Ergonode',
        progressLabel: 'Postęp tworzenia atrybutów kategorii',
        unit: 'atrybutów kategorii',
        processingBatch: 'Przetwarzam paczkę %1 z %2 (%3 atrybutów kategorii).',
        complete: 'Wszystkie atrybuty kategorii zostały utworzone i zapisane.',
        partial: 'Poprawne atrybuty kategorii zapisano, a błędy pozostawiono do ponowienia.'
    },
    option: {
        title: 'Tworzenie opcji w Ergonode',
        progressLabel: 'Postęp tworzenia opcji',
        unit: 'opcji',
        processingBatch: 'Przetwarzam paczkę %1 z %2 (%3 opcji).',
        complete: 'Wszystkie opcje zostały utworzone i zapisane.',
        partial: 'Poprawne opcje zapisano, a błędy pozostawiono do ponowienia.'
    }
};

function items(count, prefix) {
    return Array.from({length: count}, (_, index) => ({
        code: `${prefix}_${index + 1}`,
        label: `${prefix} ${index + 1}`,
        status: 'synchronized'
    }));
}

function render(args = {}) {
    const kind = args.kind || 'attribute';
    const progress = createProgress(labels[kind]);
    const batch = items(8, kind);

    progress.open(12);
    progress.showBatch(1, 2, batch);
    progress.applyBatch(batch);
    if (args.state === 'waiting') {
        progress.wait(3, 'Ergonode ograniczyło liczbę zapytań.');
    }
    if (args.state === 'internalLimit') {
        progress.wait(23, 'Too Many Requests — wewnętrzny limit Magento dla Ergonode GraphQL: 10 requestów/min.');
    }
    if (args.state === 'partial') {
        progress.applyBatch([{
            code: 'color',
            label: 'Color',
            status: 'existing',
            message: '[BAD_USER_INPUT] Atrybut o tym kodzie już istnieje i został zmapowany.'
        }]);
        progress.applyBatch([{
            code: 'invalid_code',
            label: 'Element z błędem',
            status: 'failed',
            message: 'Kod jest już używany.'
        }]);
        progress.applyBatch(items(2, kind));
        progress.finalizing();
        progress.complete(true);
    }
    if (args.state === 'complete') {
        progress.applyBatch(items(4, kind));
        progress.finalizing();
        progress.complete(false);
    }

    progress.element.storyModalShell.progress = progress;
    return progress.element.storyModalShell;
}

export default {
    id: 'ergo-c-023',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-023 · Postęp publikacji zbiorczej',
    render,
    args: {kind: 'attribute', state: 'running'},
    argTypes: {
        kind: {control: 'select', options: ['attribute', 'categoryAttribute', 'option']},
        state: {control: 'select', options: ['running', 'waiting', 'internalLimit', 'partial', 'complete']}
    },
    parameters: {a11y: {test: 'error'}}
};

export const Playground = {};

export const Disposed = {
    play: async ({canvasElement}) => {
        const shell = canvasElement.querySelector('[role="dialog"]');
        const progress = shell.progress;
        const waiting = progress.wait(30, 'Retry').catch(error => error.message);
        progress.destroy();
        progress.destroy();
        await expect(await waiting).toContain('disposed');
        progress.complete(false);
        progress.open(1);
        await expect(canvasElement.querySelector('[role="dialog"]')).toBeNull();
    }
};

export const AtrybutyKategorii = {
    args: {kind: 'categoryAttribute'}
};

export const OpcjePoRateLimit = {
    args: {kind: 'option', state: 'waiting'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByRole('progressbar', {name: 'Postęp tworzenia opcji'})).toBeVisible();
        await expect(canvas.getByText(/Ponawiam za \d+ s/)).toBeVisible();
    }
};

export const CzescowySukces = {
    args: {kind: 'attribute', state: 'partial'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByText('Element z błędem · invalid_code')).toBeVisible();
        await expect(canvas.getByText('Color · color')).toBeVisible();
        await expect(canvasElement.querySelector('[data-role="publish-warning-count"]')).toHaveTextContent('1');
        await expect(canvas.getByRole('button', {name: 'Zamknij'})).toBeVisible();
    }
};

export const Zamykanie = {
    args: {kind: 'attribute', state: 'partial'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const close = canvas.getByRole('button', {name: 'Zamknij'});

        await userEvent.click(close);
        await expect(canvasElement.querySelector('[role="dialog"]')).not.toBeVisible();
    }
};

export const Zakonczono = {
    args: {kind: 'option', state: 'complete'}
};

export const WewnetrznyLimitMagento = {
    args: {kind: 'option', state: 'internalLimit'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText(/wewnętrzny limit Magento/)).toBeVisible();
        await expect(canvas.getByText(/Ponawiam za \d+ s/)).toBeVisible();
    }
};

export const KrzyzykPoZakonczeniu = {
    args: {state: 'complete'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('button', {name: 'Zamknij okno'}));
        await expect(canvasElement.querySelector('[role="dialog"]')).not.toBeVisible();
    }
};
export const OchronaTrwajacegoProcesu = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByRole('button', {name: /Zatrzymaj wysyłkę i zamknij/})).toBeDisabled();
        canvas.getByRole('list', {name: ''}).focus();
        await userEvent.keyboard('{Escape}');
        await expect(canvas.getByRole('dialog')).toBeVisible();
    }
};
