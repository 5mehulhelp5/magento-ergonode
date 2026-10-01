define([], function () {
    'use strict';

    function create(root) {
        var panel = root.querySelector('[data-role="template-drop-source"]');
        var status = root.querySelector('[data-role="template-refresh-state"]');
        var titleNode = root.querySelector('[data-role="template-refresh-title"]');
        var textNode = root.querySelector('[data-role="template-refresh-text"]');
        var search = root.querySelector('[data-role="template-search"]');

        function refreshButtons() {
            return root.querySelectorAll('[data-role="refresh-templates"]');
        }

        function show(title, text) {
            if (!panel || !status) {
                return;
            }

            panel.classList.add('is-refreshing');
            panel.setAttribute('aria-busy', 'true');
            status.hidden = false;
            if (titleNode) {
                titleNode.textContent = title;
            }
            if (textNode) {
                textNode.textContent = text;
            }
            if (search) {
                search.disabled = true;
            }
            Array.prototype.forEach.call(refreshButtons(), function (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
        }

        function clear() {
            if (!panel || !status) {
                return;
            }

            panel.classList.remove('is-refreshing');
            panel.removeAttribute('aria-busy');
            status.hidden = true;
            if (search) {
                search.disabled = false;
            }
            Array.prototype.forEach.call(refreshButtons(), function (button) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            });
        }

        return {
            show: show,
            clear: clear
        };
    }

    return {
        create: create
    };
});
