define([], function () {
    'use strict';

    function close(menu, restoreFocus) {
        var summary = menu ? menu.querySelector('summary') : null;

        if (!menu) {
            return;
        }

        menu.open = false;
        if (restoreFocus && summary) {
            summary.focus();
        }
    }

    function closeOthers(root, current) {
        root.querySelectorAll('[data-role="synchronization-action-options"][open]').forEach(function (menu) {
            if (menu !== current) {
                close(menu, false);
            }
        });
    }

    function bind(scope, root) {
        if (!scope || !root || !scope.claim(root, 'synchronization-actions')) {
            return;
        }

        scope.delegate(
            'click',
            '[data-role="synchronization-action-options"] > summary',
            function (event, summary) {
                closeOthers(root, summary.closest('[data-role="synchronization-action-options"]'));
            }
        );
        scope.delegate('click', '[data-synchronization-action]', function (event, action) {
            close(action.closest('[data-role="synchronization-action-options"]'), false);
        });
        scope.delegate(
            'keydown',
            '[data-role="synchronization-action-options"] > summary',
            function (event, summary) {
                var menu;

                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                menu = summary.closest('[data-role="synchronization-action-options"]');
                if (menu.hasAttribute('open')) {
                    close(menu, true);
                    return;
                }

                closeOthers(root, menu);
                menu.setAttribute('open', '');
            }
        );
        scope.delegate('keydown', '[data-role="synchronization-action-options"]', function (event, menu) {
            if (event.key !== 'Escape') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            close(menu, true);
        });
        scope.listen(document, 'click', function (event) {
            if (!event.target.closest || !event.target.closest('[data-role="synchronization-actions"]')) {
                closeOthers(root, null);
            }
        });
    }

    return {bind: bind};
});
