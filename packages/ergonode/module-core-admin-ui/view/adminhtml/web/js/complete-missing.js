define(['Ergonode_CoreAdminUi/js/workspace'], function (workspace) {
    'use strict';

    var actionSelector = '[data-completes-missing-side="1"]';

    function availableActions(root) {
        return Array.prototype.filter.call(root.querySelectorAll(actionSelector), function (button) {
            return !button.disabled && !!button.closest('[data-role="mapping-row"]');
        });
    }

    function updateAction(root, completing) {
        var button = root.querySelector('[data-role="complete-missing"]');
        var saveButton = root.querySelector('[data-role="save-mapping"]');
        var saveBusy = saveButton && saveButton.getAttribute('aria-busy') === 'true';
        var shouldDisable = completing || availableActions(root).length === 0 || saveBusy;

        if (button && button.disabled !== shouldDisable) {
            button.disabled = shouldDisable;
        }
    }

    function runActions(actions) {
        actions.forEach(function (action) {
            action.click();
        });

        return actions.length;
    }

    return function (root, options) {
        var button = root.querySelector('[data-role="complete-missing"]');
        var completing = false;
        var observer;
        var scope = workspace.get(root);

        options = options || {};
        if (!button || !scope) {
            return;
        }

        scope.listen(button, 'click', function (event) {
            var actions;

            event.preventDefault();
            event.stopPropagation();

            if (button.disabled) {
                return;
            }

            actions = availableActions(root);
            completing = true;
            button.setAttribute('aria-busy', 'true');
            updateAction(root, completing);

            Promise.resolve().then(function () {
                return typeof options.complete === 'function'
                    ? options.complete(actions)
                    : runActions(actions);
            }).catch(function (error) {
                if (typeof options.onError === 'function') {
                    options.onError(error);
                }
            }).finally(function () {
                completing = false;
                button.removeAttribute('aria-busy');
                updateAction(root, completing);
            });
        });

        updateAction(root, completing);
        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(function () {
                updateAction(root, completing);
            });
            observer.observe(root, {attributes: true, childList: true, subtree: true});
            scope.cleanup(function () {
                observer.disconnect();
            });
        }
    };
});
