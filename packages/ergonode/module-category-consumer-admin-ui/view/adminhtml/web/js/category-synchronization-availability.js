define([], function () {
    'use strict';

    function update(root, blockers) {
        blockers = blockers || {};
        root.querySelectorAll('[data-synchronization-action]').forEach(function (button) {
            var action = button.getAttribute('data-synchronization-action');
            var scope = button.getAttribute('data-synchronization-scope');
            var reason = action === 'reset-cursor' ? '' : String(blockers[scope === 'data' ? 'data' : 'tree'] || '');
            var hint = button.querySelector('[data-role="sync-blocking-hint"]');

            button.setAttribute('aria-disabled', reason ? 'true' : 'false');
            button.classList.remove('is-hint-dismissed');
            if (hint) {
                hint.textContent = reason;
                if (reason) {
                    button.setAttribute('aria-describedby', hint.id);
                } else {
                    button.removeAttribute('aria-describedby');
                }
            }
        });
    }

    function bind(scope, root) {
        if (!scope.claim(root, 'category-synchronization-availability')) {
            return;
        }
        scope.listen(root, 'click', function (event) {
            var button = event.target.closest('[data-synchronization-action]');

            if (button && button.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
        scope.listen(root, 'keydown', function (event) {
            var button = event.target.closest('[data-synchronization-action]');

            if (!button || button.getAttribute('aria-disabled') !== 'true') {
                return;
            }
            if (event.key === 'Escape') {
                button.classList.add('is-hint-dismissed');
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
        ['focusout', 'mouseout'].forEach(function (type) {
            scope.listen(root, type, function (event) {
                var button = event.target.closest('[data-synchronization-action]');

                if (button && !button.contains(event.relatedTarget)) {
                    button.classList.remove('is-hint-dismissed');
                }
            });
        });
    }

    return {bind: bind, update: update};
});
