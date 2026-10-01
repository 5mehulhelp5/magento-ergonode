define(['Ergonode_CoreAdminUi/js/buttons'], function (buttons) {
    'use strict';

    var selector = '[data-role="visibility-toggle"]';

    function getHint(button, visible) {
        return button ? button.getAttribute(visible ? 'data-hide-hint' : 'data-show-hint') || '' : '';
    }

    function sync(button) {
        var visible = buttons.isPressed(button);
        var hint = getHint(button, visible);

        if (!button) {
            return false;
        }
        if (hint) {
            button.setAttribute('title', hint);
            button.setAttribute('aria-label', hint);
        }

        return visible;
    }

    function initialize(root) {
        if (!root) {
            return;
        }
        if (root.matches && root.matches(selector)) {
            sync(root);
        }
        root.querySelectorAll(selector).forEach(sync);
    }

    function isVisible(button) {
        return buttons.isPressed(button);
    }

    function setVisible(button, visible) {
        buttons.setPressed(button, visible);

        return sync(button);
    }

    function toggle(button) {
        return setVisible(button, !isVisible(button));
    }

    return {
        initialize: initialize,
        isVisible: isVisible,
        setVisible: setVisible,
        sync: sync,
        toggle: toggle
    };
});
