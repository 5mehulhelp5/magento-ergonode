define(['mage/translate'], function ($t) {
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
        root.querySelectorAll('[data-role="entity-options"][open]').forEach(function (menu) {
            if (menu !== current) {
                close(menu, false);
            }
        });
    }

    function buildAction(options) {
        var action = document.createElement('button');
        var icon = document.createElement('span');
        var label = document.createElement('span');
        var available = options.available !== false;

        action.type = 'button';
        action.className = 'veui-entity-options-action' +
            (options.className ? ' ' + options.className : '');
        action.setAttribute('data-role', options.role || 'entity-option-action');
        action.setAttribute('draggable', 'false');
        action.setAttribute('aria-label', options.ariaLabel || options.label || '');
        action.title = options.title || options.label || '';
        action.disabled = options.disabled === true || !available ||
            (options.requiresActive === true && options.active === false);
        action.setAttribute('data-action-available', available ? '1' : '0');
        if (options.requiresActive === true) {
            action.setAttribute('data-requires-active', '1');
        }
        Object.keys(options.attributes || {}).forEach(function (name) {
            var value = options.attributes[name];

            if (value !== null && value !== undefined && value !== false) {
                action.setAttribute(name, String(value));
            }
        });

        icon.className = options.iconClass || 'veui-entity-options-add-icon';
        icon.setAttribute('aria-hidden', 'true');
        label.className = 'veui-entity-options-action-label';
        label.textContent = options.label || '';
        action.append(icon, label);

        return action;
    }

    function prepareAction(action) {
        var label;

        if (!action) {
            return null;
        }

        action.classList.add('veui-entity-options-action');
        action.setAttribute('data-role', action.getAttribute('data-role') || 'entity-option-action');
        action.setAttribute('draggable', 'false');
        label = action.querySelector('.veui-entity-options-action-label');
        if (!label && action.lastElementChild && action.lastElementChild.getAttribute('aria-hidden') !== 'true') {
            action.lastElementChild.classList.add('veui-entity-options-action-label');
        }

        return action;
    }

    function prepareToggle(toggle, label) {
        var text = document.createElement('span');

        if (!toggle) {
            return null;
        }

        label = label || mappingToggleLabel(toggle.getAttribute('aria-pressed') !== 'false');
        prepareAction(toggle);
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        if (!toggle.querySelector('.veui-entity-options-action-label')) {
            text.className = 'veui-entity-options-action-label';
            text.textContent = label;
            toggle.appendChild(text);
        }

        return toggle;
    }

    function mappingToggleLabel(active) {
        return active ? $t('Exclude') : $t('Include');
    }

    function create(options) {
        var menu = document.createElement('details');
        var summary = document.createElement('summary');
        var icon = document.createElement('span');
        var actions = document.createElement('div');
        var label = options.menuLabel || $t('Opcje elementu');

        menu.className = 'veui-entity-options';
        menu.setAttribute('data-role', 'entity-options');
        menu.setAttribute('draggable', 'false');

        summary.setAttribute('role', 'button');
        summary.setAttribute('draggable', 'false');
        summary.setAttribute('aria-label', label);
        summary.title = label;
        icon.className = 'veui-entity-options-icon';
        icon.setAttribute('aria-hidden', 'true');
        summary.appendChild(icon);

        actions.className = 'veui-entity-options-menu';
        actions.setAttribute('role', 'group');
        actions.setAttribute('aria-label', label);
        (options.actions || []).forEach(function (actionOptions) {
            actions.appendChild(actionOptions && actionOptions.nodeType === 1
                ? prepareAction(actionOptions)
                : buildAction(actionOptions));
        });
        if (options.toggle) {
            actions.appendChild(prepareToggle(options.toggle, options.toggleLabel));
        }

        menu.append(summary, actions);

        return menu;
    }

    function enhance(card, options) {
        var toggle;
        var menu;
        var placeholder;

        options = options || {};

        if (!card) {
            return null;
        }

        menu = card.querySelector('[data-role="entity-options"]');
        if (menu) {
            return menu;
        }

        toggle = options.toggle || (options.toggleSelector
            ? card.querySelector(options.toggleSelector)
            : null);
        menu = create({
            actions: options.actions || [],
            menuLabel: options.menuLabel,
            toggle: toggle,
            toggleLabel: options.toggleLabel
        });
        placeholder = card.querySelector('[data-role="entity-options-placeholder"]');
        if (placeholder) {
            placeholder.replaceWith(menu);
        } else {
            card.appendChild(menu);
        }

        if (typeof options.active === 'boolean') {
            setActiveState(card, options.active);
        }

        return menu;
    }

    function setActionsEnabled(card, active) {
        if (!card) {
            return;
        }

        card.querySelectorAll('[data-role="entity-options"] [data-requires-active="1"]').forEach(function (action) {
            action.disabled = !active || action.getAttribute('data-action-available') !== '1';
        });
    }

    function setActiveState(card, active) {
        var toggle = card ? card.querySelector('[data-role="entity-options"] .vea-card-toggle') : null;
        var label = mappingToggleLabel(active);
        var text = toggle ? toggle.querySelector('.veui-entity-options-action-label') : null;

        setActionsEnabled(card, active);
        if (!toggle) {
            return;
        }

        toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        if (text) {
            text.textContent = label;
        }
    }

    function bind(scope, root) {
        if (!scope || !root || !scope.claim(root, 'entity-options')) {
            return;
        }

        scope.delegate('click', '[data-role="entity-options"] > summary', function (event, summary) {
            closeOthers(root, summary.closest('[data-role="entity-options"]'));
        });
        scope.delegate('click', '[data-role="entity-options"] .veui-entity-options-action', function (event, action) {
            close(action.closest('[data-role="entity-options"]'), false);
        });
        scope.delegate('keydown', '[data-role="entity-options"] > summary', function (event, summary) {
            var menu;

            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            menu = summary.closest('[data-role="entity-options"]');
            if (menu.hasAttribute('open')) {
                close(menu, true);
                return;
            }

            closeOthers(root, menu);
            menu.setAttribute('open', '');
        });
        scope.delegate('keydown', '[data-role="entity-options"]', function (event, menu) {
            if (event.key !== 'Escape') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            close(menu, true);
        });
        scope.listen(document, 'click', function (event) {
            if (!event.target.closest || !event.target.closest('[data-role="entity-options"]')) {
                closeOthers(root, null);
            }
        });
    }

    return {
        bind: bind,
        create: create,
        createAction: buildAction,
        enhance: enhance,
        setActiveState: setActiveState
    };
});
