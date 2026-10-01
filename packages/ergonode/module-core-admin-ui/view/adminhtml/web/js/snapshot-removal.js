define([
    'Ergonode_CoreAdminUi/js/request',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/entity-options',
    'mage/translate'
], function (request, buttons, entityOptions, $t) {
    'use strict';

    function action(options) {
        var attributes;

        options = options || {};
        attributes = Object.assign({
            'data-code': options.code || '',
            'data-label': options.label || options.code || ''
        }, options.attributes || {});

        return {
            available: options.available !== false,
            className: 'veui-entity-options-delete' +
                (options.className ? ' ' + options.className : ''),
            iconClass: options.iconClass || 'veui-entity-options-delete-icon',
            label: options.actionLabel || $t('Remove from list'),
            role: options.role || 'entity-delete-snapshot',
            title: options.title || $t('Remove from list'),
            ariaLabel: options.ariaLabel || $t('Remove from list'),
            attributes: attributes
        };
    }

    function mappingAction(options) {
        options = options || {};

        return {
            active: options.active !== false,
            available: options.available !== false,
            className: options.className || '',
            iconClass: options.iconClass || 'veui-entity-options-add-icon',
            label: options.label || $t('Dodaj do mapowania'),
            requiresActive: true,
            role: options.role || 'entity-add-to-mapping',
            attributes: options.attributes || {}
        };
    }

    function show(options, tone, message) {
        var messageBus = options.message;

        if (messageBus && typeof messageBus.show === 'function') {
            messageBus.show(tone, message);
        }
    }

    function reload(root) {
        var view = root && root.ownerDocument ? root.ownerDocument.defaultView : window;

        view.setTimeout(function () {
            view.location.reload();
        }, 350);
    }

    function enhance(card, options) {
        var actions;
        var snapshot;

        options = options || {};
        snapshot = Object.assign({}, options.snapshot || {});
        if (snapshot.code === undefined) {
            snapshot.code = card ? card.getAttribute('data-code') || '' : '';
        }
        if (snapshot.label === undefined) {
            snapshot.label = card
                ? card.getAttribute('data-label') || snapshot.code
                : snapshot.code;
        }
        actions = (options.actions || []).slice();
        if (options.mappingAction) {
            actions.unshift(mappingAction(Object.assign(
                {active: options.active},
                options.mappingAction === true ? {} : options.mappingAction
            )));
        }
        if (options.removable !== false) {
            actions.push(action(snapshot));
        }

        return entityOptions.enhance(card, {
            active: options.active,
            actions: actions,
            menuLabel: options.menuLabel,
            toggle: options.toggle,
            toggleLabel: options.toggleLabel,
            toggleSelector: options.toggleSelector
        });
    }

    function bind(scope, root, config, options) {
        options = options || {};
        config = config || {};

        entityOptions.bind(scope, root);

        if (!scope || !root || !scope.claim(root, 'snapshot-removal')) {
            return;
        }

        scope.delegate('click', '[data-role="entity-delete-snapshot"]', function (event, button) {
            var code = button.getAttribute('data-code') || '';
            var label = button.getAttribute('data-label') || code;
            var confirmAction = options.confirm || window.confirm.bind(window);
            var data = {code: code};

            event.preventDefault();
            event.stopImmediatePropagation();
            if (!code || button.disabled) {
                return;
            }
            if (typeof options.isDirty === 'function' && options.isDirty()) {
                show(options, 'error', options.dirtyMessage || $t(
                    'Save mapping changes before removing this item.'
                ));
                return;
            }
            if (!confirmAction($t(
                'Remove "%1" from this list? Magento data will not be deleted.'
            ).replace('%1', label))) {
                return;
            }
            if (typeof options.data === 'function') {
                data = Object.assign(data, options.data(button) || {});
            }

            if (typeof options.beforeRemove === 'function' && options.beforeRemove(button) === false) {
                return;
            }

            buttons.run(button, function () {
                return request.post(config.urls && config.urls.delete_snapshot, config, data);
            }, {busyClass: 'is-working'}).then(function (response) {
                show(options, 'success', response.message || $t(
                    'The item has been removed from this list.'
                ));
                if (typeof options.onSuccess === 'function') {
                    options.onSuccess(response, button);
                } else {
                    reload(root);
                }
            }).catch(function (error) {
                if (typeof options.onError === 'function') {
                    options.onError(error, button);
                }
                show(options, 'error', error && error.message
                    ? error.message
                    : $t('Unable to remove the item from this list.'));
            });
        });
    }

    function requestRemoval(card) {
        var button = card
            ? card.querySelector('[data-role="entity-delete-snapshot"]')
            : null;

        if (!button || button.disabled) {
            return false;
        }

        button.click();

        return true;
    }

    return {
        bind: bind,
        enhance: enhance,
        requestRemoval: requestRemoval,
        setActiveState: entityOptions.setActiveState
    };
});
