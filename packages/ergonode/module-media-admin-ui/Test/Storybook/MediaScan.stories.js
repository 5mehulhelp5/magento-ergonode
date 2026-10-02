import { expect, userEvent, within } from 'storybook/test';
import { loadAmdModule } from '@ergonode-storybook/load-amd-module.js';
import source from '../../view/adminhtml/web/js/media-scan.js?raw';
import template from '../../view/adminhtml/web/template/media-scan.html?raw';

const initial = {
    status: 'required', processed: 0, estimated_total: 723, percent: 0,
    indexed: 0, reused: 0, removed: 0, bytes: 0, blocked: true,
    elapsed_seconds: 0, estimated_remaining_seconds: null, last_completed_at: null, error: null
};

function render(args) {
    const root = document.createElement('div');
    let scan = { ...initial, ...args.scan };
    const jquery = { ajax(request) {
        if (request.type === 'POST' && !args.rejectStart) {
            root.dataset.started = 'true';
            root.dataset.method = request.type;
            root.dataset.csrf = String(Object.hasOwn(request.data, 'form_key'));
            scan = { ...scan, status: 'pending' };
        }
        const result = request.type === 'POST'
            ? { success: !args.rejectStart, message: 'Scan request rejected.' }
            : { success: true, scan };
        return {
            done(callback) { callback(result); return this; },
            fail() { return this; },
            always(callback) { callback(); return this; }
        };
    } };
    const initialize = loadAmdModule(source, {
        jquery,
        'mage/translate': text => text,
        'text!Ergonode_MediaAdminUi/template/media-scan.html': template
    });
    initialize({ statusUrl: '/fixture/status', startUrl: '/fixture/start' }, root);
    return root;
}

export default {
    id: 'ergo-v-067-02',
    title: 'Ergonode UI/Widoki/Media/ERGO-V-067 · Ustawienia mediów/ERGO-V-067.02 · Skan lokalnych mediów',
    render,
    parameters: { a11y: { test: 'error' } }
};

export const Playground = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        const start = canvas.getByRole('button', { name: 'Scan media' });
        start.focus();
        await userEvent.keyboard('{Enter}');
        await expect(canvas.getByRole('status')).toHaveTextContent('Waiting for the background worker');
        await expect(start).toBeDisabled();
        await expect(canvasElement.querySelector('[data-method]').dataset.method).toBe('POST');
        await expect(canvasElement.querySelector('[data-csrf]').dataset.csrf).toBe('true');
        await userEvent.click(canvas.getByRole('button', { name: 'Refresh status' }));
        await expect(start).toBeDisabled();
    }
};
export const Required = {};
export const Pending = { args: { scan: { status: 'pending' } } };
export const Running = { args: { scan: {
    status: 'running', processed: 320, percent: 44, estimated_remaining_seconds: 123, elapsed_seconds: 98
} } };
export const EstimateExceeded = { args: { scan: { status: 'running', processed: 800, percent: 99 } } };
export const UnknownSize = { args: { scan: { status: 'running', processed: 30, estimated_total: 0, percent: null } } };
export const Completed = { args: { scan: {
    status: 'complete', processed: 740, percent: 100, blocked: false, last_completed_at: 1789200000
} } };
export const FailedFirstScan = { args: { scan: { status: 'failed', error: 'A local directory cannot be read.' } } };
export const FailedRefresh = { args: { scan: {
    status: 'failed', error: 'A local directory cannot be read.', blocked: false, last_completed_at: 1789200000
} } };
export const RejectedRequest = {
    args: { rejectStart: true },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(canvas.getByRole('button', { name: 'Scan media' }));
        await expect(canvas.getByRole('alert')).toHaveTextContent('Scan request rejected.');
        await expect(canvas.getByRole('button', { name: 'Scan media' })).toBeEnabled();
    }
};
