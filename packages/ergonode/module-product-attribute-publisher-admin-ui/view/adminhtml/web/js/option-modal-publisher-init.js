define([
    'Ergonode_AttributePublisherAdminUi/js/ergonode-attribute-publisher-mapping'
], function (attributePublisherMapping) {
    'use strict';

    return function (config, element) {
        function onOptionMappingReady(event) {
            var workspace = event.detail && event.detail.workspace;

            if (workspace && workspace.veaWorkspace) {
                attributePublisherMapping(config, workspace);
            }
        }

        element.addEventListener('vea:option-mapping-ready', onOptionMappingReady);
        if (element.veaWorkspace && typeof element.veaWorkspace.cleanup === 'function') {
            element.veaWorkspace.cleanup(function () {
                element.removeEventListener('vea:option-mapping-ready', onOptionMappingReady);
            });
        }
    };
});
