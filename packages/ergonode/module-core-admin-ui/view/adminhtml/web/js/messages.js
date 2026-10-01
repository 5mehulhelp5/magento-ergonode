define([
    'mage/translate'
], function ($t) {
    'use strict';

    var defaultAutoHideDelay = 30000;

    function create(root, config) {
        config = config || {};

        var containerSelector = config.containerSelector || '[data-role="global-message"]';
        var textSelector = config.textSelector || '[data-role="global-message-text"]';
        var container = root.querySelector(containerSelector);
        var content = container ? container.querySelector(textSelector) : null;
        var tones = config.toneClasses || {
            info: 'veui-message-info',
            success: 'veui-message-success',
            warning: 'veui-message-warning',
            error: 'veui-message-error'
        };
        var closeButton = null;
        var autoHideDelay = Object.prototype.hasOwnProperty.call(config, 'autoHideDelay')
            ? Number(config.autoHideDelay)
            : defaultAutoHideDelay;
        var timer = null;

        function renderContent(message, action) {
            var actionLink;

            content.textContent = message || '';
            if (!action || !action.url || !action.label) {
                return;
            }

            actionLink = container.ownerDocument.createElement('a');
            actionLink.className = 'veui-message-action';
            actionLink.href = action.url;
            actionLink.textContent = action.label;
            content.appendChild(container.ownerDocument.createTextNode(' '));
            content.appendChild(actionLink);
        }

        function errorMessage(title, exception, fallbackMessage) {
            var detail = exception && exception.message ? exception.message : fallbackMessage;

            if (!title) {
                return detail || '';
            }
            if (!detail || detail === title) {
                return title;
            }

            return title + ': ' + detail;
        }

        function hide() {
            window.clearTimeout(timer);
            timer = null;

            if (!container || !content) {
                return;
            }

            container.hidden = true;
            content.textContent = '';
            container.removeAttribute('role');
            container.setAttribute('aria-live', 'polite');
        }

        function createCloseButton() {
            var documentScope;

            if (!container || !content) {
                return null;
            }

            closeButton = container.querySelector('[data-role="message-close"]');
            if (!closeButton) {
                documentScope = container.ownerDocument;
                if (!documentScope || typeof documentScope.createElement !== 'function') {
                    return null;
                }

                closeButton = documentScope.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'veui-message-close';
                closeButton.setAttribute('data-role', 'message-close');
                closeButton.textContent = '×';
                container.appendChild(closeButton);
            }

            closeButton.setAttribute('aria-label', config.closeLabel || $t('Close message'));
            closeButton.setAttribute('title', config.closeLabel || $t('Close message'));
            closeButton.addEventListener('click', hide);

            return closeButton;
        }

        function show(tone, message, timeout, action) {
            var delay = typeof timeout === 'undefined' ? autoHideDelay : Number(timeout);

            if (!container || !content) {
                return;
            }

            Object.keys(tones).forEach(function (name) {
                container.classList.remove(tones[name]);
            });
            if (tones[tone]) {
                container.classList.add(tones[tone]);
            }

            window.clearTimeout(timer);
            renderContent(message, action);
            container.hidden = false;
            container.setAttribute('role', tone === 'error' ? 'alert' : 'status');
            container.setAttribute('aria-live', tone === 'error' ? 'assertive' : 'polite');

            if (delay > 0) {
                timer = window.setTimeout(hide, delay);
            }
        }

        function error(title, exception, fallbackMessage, action) {
            show('error', errorMessage(title, exception, fallbackMessage), undefined, action);
        }

        function destroy() {
            window.clearTimeout(timer);
            timer = null;
            if (closeButton) {
                closeButton.removeEventListener('click', hide);
            }
        }

        createCloseButton();

        return {
            show: show,
            error: error,
            hide: hide,
            destroy: destroy
        };
    }

    return {
        create: create
    };
});
