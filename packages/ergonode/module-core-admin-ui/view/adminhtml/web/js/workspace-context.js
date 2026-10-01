define([
    'Ergonode_CoreAdminUi/js/messages',
    'Ergonode_CoreAdminUi/js/dirty-state',
    'mage/translate'
], function (messages, dirtyState, $t) {
    'use strict';

    function cleanupExposed(root, context) {
        if (root.veaContext === context) {
            delete root.veaContext;
        }
    }

    function create(scope, root, options) {
        var context;
        var publish;

        options = options || {};
        if (!scope || !root || typeof scope.cleanup !== 'function') {
            throw new Error($t('Workspace context requires an active scope and root element.'));
        }

        publish = options.publish !== false;
        context = {
            dirty: options.serialize ? dirtyState.create(options.serialize) : null,
            message: options.message === false ? null : messages.create(root, options.message || {}),
            serialize: typeof options.serialize === 'function' ? options.serialize : null
        };

        if (publish) {
            root.veaContext = context;
        }
        scope.cleanup(function () {
            if (context.message && typeof context.message.destroy === 'function') {
                context.message.destroy();
            }
            cleanupExposed(root, context);
        });

        return context;
    }

    return {
        create: create
    };
});
