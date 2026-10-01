import { expect, userEvent, within } from 'storybook/test';
import '../../view/adminhtml/web/css/ergonode-workspace.css';
import settingsIcon from '../../view/adminhtml/web/images/settings.svg';

function render(args) {
    const root = document.createElement('div');
    root.className = 'veui-workspace veui-workspace-blocked';
    if (args.narrow) root.style.maxWidth = '380px';
    root.innerHTML = `<section class="veui-connection-notice" data-role="connection-notice" role="status"
        aria-labelledby="ergonode-connection-notice-title">
        <div class="veui-connection-notice-content">
            <h2 id="ergonode-connection-notice-title">This view is unavailable.</h2>
            <p>${args.canConfigure ? 'Configure the Ergonode connection to access this view.'
                : 'Ask an administrator to configure the Ergonode connection.'}</p>
        </div>
        ${args.canConfigure ? `<div class="veui-connection-notice-actions">
            <a class="veui-button veui-button-toolbar" href="/configuration" target="_blank" rel="noopener noreferrer">
                <img src="${settingsIcon}" width="16" height="16" alt="" aria-hidden="true">
                Open configuration
            </a>
        </div>` : ''}
    </section>`;
    // Native GET links perform navigation in Magento; this fixture only records activation.
    root.querySelectorAll('a').forEach((link) => link.addEventListener('click', (event) => {
        event.preventDefault();
        root.dataset.activated = link.getAttribute('href');
    }));
    return root;
}

export default {
    id: 'ergo-c-024',
    title: 'Ergonode UI/Komponenty/Wspólne/ERGO-C-024 · Komunikat o połączeniu',
    render,
    args: {canConfigure: true, narrow: false}
};

export const Playground = {};
export const WithoutConfigurationAccess = {
    args: {canConfigure: false},
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        await expect(canvas.queryByRole('link', {name: 'Open configuration'})).not.toBeInTheDocument();
        await expect(canvas.getByText('Ask an administrator to configure the Ergonode connection.')).toBeVisible();
    }
};
export const NarrowScreen = {
    args: {narrow: true},
    play: async ({canvasElement}) => {
        const notice = canvasElement.querySelector('[data-role="connection-notice"]');
        await expect(notice.scrollWidth).toBeLessThanOrEqual(notice.clientWidth);
    }
};
export const MouseAndKeyboard = {
    play: async ({canvasElement}) => {
        const canvas = within(canvasElement);
        const root = canvasElement.querySelector('.veui-workspace-blocked');
        await expect(canvasElement.querySelector('.veui-toolbar, .veui-layout')).not.toBeInTheDocument();
        await expect(canvas.queryByRole('link', {name: 'Check again'})).not.toBeInTheDocument();
        const configure = canvas.getByRole('link', {name: 'Open configuration'});
        await userEvent.click(configure);
        await expect(root).toHaveAttribute('data-activated', '/configuration');
        delete root.dataset.activated;
        configure.focus();
        await expect(configure).toHaveFocus();
        await userEvent.keyboard('{Enter}');
        await expect(root).toHaveAttribute('data-activated', '/configuration');
    }
};
