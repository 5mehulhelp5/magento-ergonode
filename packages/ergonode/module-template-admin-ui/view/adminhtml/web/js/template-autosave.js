define([
    'Ergonode_CoreAdminUi/js/autosave',
    'Ergonode_CoreAdminUi/js/request'
], function (autosave, request) {
    'use strict';

    function create(root, config, options) {
        options = options || {};
        config = config || {};

        return autosave.create(root, {
            serialize: options.serialize,
            persist: function (snapshot) {
                return request.post(config.urls && config.urls.save_mapping, config, {
                    mappings: JSON.stringify(snapshot.mappings || {}),
                    visibility: JSON.stringify(snapshot.visibility || [])
                });
            },
            onError: options.onError,
            onSaved: options.onSaved
        });
    }

    return {
        create: create
    };
});
