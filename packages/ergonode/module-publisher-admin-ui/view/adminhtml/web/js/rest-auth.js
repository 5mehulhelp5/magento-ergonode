define(['require', 'Ergonode_PublisherAdminUi/js/manual-auth'], function (localRequire, createAuth) {
    'use strict';

    return function (config) {
        return createAuth(Object.assign({
            logo_url: localRequire.toUrl('Ergonode_CoreAdminUi/images/m2_configuration.svg')
        }, config));
    };
});
