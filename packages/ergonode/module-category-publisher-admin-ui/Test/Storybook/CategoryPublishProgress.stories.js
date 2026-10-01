import { expect, userEvent, within } from 'storybook/test';

import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';

import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/ergonode-workspace.css';
import '@ergonode-modules/CoreAdminUi/view/adminhtml/web/css/bulk-publish-progress.css';
import '../../view/adminhtml/web/css/ergonode-category-publisher.css';
import bulkProgressSource from '@ergonode-modules/CoreAdminUi/view/adminhtml/web/js/bulk-publish-progress.js?raw';
import source from '../../view/adminhtml/web/js/category-publish-progress.js?raw';

function storyJquery(element) {
    return {
        element,
        modal(action) {
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
    header.append(title);
    widget.element.classList.add('modal-content');
    inner.append(header, widget.element);
    shell.append(inner);
    widget.shell = shell;
    widget.element.storyModalShell = shell;
}

const createBulkProgress = loadAmdModule(bulkProgressSource, {
    jquery: storyJquery,
    'Magento_Ui/js/modal/modal': storyModal,
    'mage/translate': (value) => value
}, 'Ergonode_CoreAdminUi/js/bulk-publish-progress');
const createProgress = loadAmdModule(source, {
    'mage/translate': (value) => value,
    'Ergonode_CoreAdminUi/js/bulk-publish-progress': createBulkProgress
}, 'Ergonode_CategoryPublisherAdminUi/js/category-publish-progress');

function items(count, offset = 0) {
    return Array.from({length: count}, (_, index) => ({
        code: `category_${offset + index + 1}`,
        label: `Kategoria ${offset + index + 1}`,
        status: 'synchronized'
    }));
}

function render(args = {}) {
    const progress = createProgress();
    const total = args.total || 5000;

    progress.open(total, {
        pause: () => queueMicrotask(() => progress.setPauseState('paused')),
        resume: () => progress.showBatch(6, Math.ceil(total / 50), items(50, 250))
    });
    progress.showBatch(5, Math.ceil(total / 50), items(50, 200));
    progress.applyBatch(items(args.completed || 200));
    if (args.state === 'rateLimit') {
        progress.wait(30, 'Ergonode ograniczyło liczbę zapytań.');
    }
    if (args.state === 'pausing' || args.state === 'paused') {
        progress.setPauseState(args.state);
    }
    if (args.state === 'finalizing' || args.state === 'complete') {
        progress.finalizing();
    }
    if (args.state === 'complete') {
        progress.complete(false);
    }
    if (args.state === 'failed') {
        progress.fail('Nie udało się połączyć z Magento.');
    }
    if (args.state === 'errors') {
        progress.applyBatch([{
            code: 'invalid_code',
            label: 'Niepoprawna kategoria',
            status: 'failed',
            message: 'Kategoria o tym kodzie już istnieje.'
        }, {
            code: 'child_code',
            label: 'Potomek',
            status: 'blocked',
            message: 'Kategoria nadrzędna ma błąd.'
        }, {
            code: 'promocja_10_99',
            label: 'Promocja 10-99',
            status: 'skipped',
            message: 'Kod jest już używany; kategorię pominięto.'
        }]);
        progress.finalizing();
        progress.complete(true);
    }

    return progress.element.storyModalShell;
}

export default {
    id: 'ergo-v-068-03',
    title: 'Ergonode UI/Widoki/Kategorie/ERGO-V-068 · Formularz kategorii/ERGO-V-068.03 · Postęp publikacji kategorii',
    render,
    args: {state: 'running'},
    argTypes: {
        state: {
            control: 'select',
            options: ['running', 'pausing', 'paused', 'rateLimit', 'finalizing', 'complete', 'errors', 'failed']
        }
    },
    parameters: {a11y: {test: 'error'}}
};

export const Playground = {};

export const Wstrzymywanie = {
    args: {state: 'pausing'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByRole('button', {name: 'Wstrzymaj'})).toHaveAttribute('aria-disabled', 'true');
        await expect(canvas.getByRole('status')).toHaveTextContent('Wstrzymuję proces');
    }
};

export const Wstrzymano = {
    args: {state: 'paused'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByRole('button', {name: 'Wznów'})).toBeEnabled();
        await expect(canvas.getByRole('status')).toHaveTextContent('Proces wstrzymany.');
        await expect(canvas.getByText('Przetworzono 200 z 5000')).toBeVisible();
        await expect(canvas.queryByRole('button', {name: 'Zamknij'})).not.toBeInTheDocument();
    }
};

export const PauzaIWznowienie = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.click(canvas.getByRole('button', {name: 'Wstrzymaj'}));
        await expect(canvas.getByRole('button', {name: 'Wznów'})).toBeEnabled();
        await expect(canvas.getByText('Przetworzono 200 z 5000')).toBeVisible();
        await userEvent.click(canvas.getByRole('button', {name: 'Wznów'}));
        await expect(canvas.getByRole('status')).toHaveTextContent('Przetwarzam paczkę 6');
        await expect(canvas.getByRole('button', {name: 'Wstrzymaj'})).toBeEnabled();
    }
};

export const PauzaKlawiatura = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await userEvent.tab();
        await expect(canvasElement.querySelector('[data-role="publish-current-items"]')).toHaveFocus();
        await userEvent.tab();
        await expect(canvas.getByRole('button', {name: 'Wstrzymaj'})).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('button', {name: 'Wznów'})).toBeEnabled();
        await expect(canvas.getByRole('button', {name: 'Wznów'})).toHaveFocus();
        await userEvent.keyboard(' ');
        await expect(canvas.getByRole('status')).toHaveTextContent('Przetwarzam paczkę 6');
    }
};

export const ZapisMapowan = {args: {state: 'finalizing', total: 200, completed: 200}};
export const Zakonczono = {args: {state: 'complete', total: 200, completed: 200}};
export const BladProcesu = {args: {state: 'failed'}};

export const OczekiwaniePo429 = {
    args: {state: 'rateLimit'},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByRole('progressbar', {name: 'Postęp tworzenia kategorii'})).toBeVisible();
        await expect(canvas.getByText(/Ponawiam za \d+ s/)).toBeVisible();
        await expect(canvas.getByText('Przetworzono 200 z 5000')).toBeVisible();
    }
};

export const CzesciowySukcesZBledami = {
    args: {state: 'errors', total: 203, completed: 200},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);

        await expect(canvas.getByText('Niepoprawna kategoria · invalid_code')).toBeVisible();
        await expect(canvas.getByText('Potomek · child_code')).toBeVisible();
        await expect(canvas.getByText('Promocja 10-99 · promocja_10_99')).toBeVisible();
        await expect(canvas.getByRole('button', {name: 'Zamknij'})).toBeVisible();
    }
};
