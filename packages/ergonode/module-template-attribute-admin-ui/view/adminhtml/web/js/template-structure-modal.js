define(['jquery', 'Magento_Ui/js/modal/modal', 'mage/translate',
    'Ergonode_TemplateAttributeAdminUi/js/template-structure'], function ($, modal, $t, structure) {
    'use strict';
    return function (options, element) {
        function mount() {
            var scope = element.veaWorkspace;
            if (!scope || !scope.claim(element, 'template-structure')) { return; }
            var content = $('<div/>');
            var opener;
            var request;
            var generation = 0;
            modal({type: 'popup', title: $t('Struktura zestawu atrybutów'),
                modalClass: 'vea-option-mapping-modal vets-modal', innerScroll: true, responsive: true,
                buttons: [], closed: function () {
                    generation += 1;
                    if (request) { request.abort(); }
                    if (opener && opener.isConnected) { opener.focus(); }
                }}, content);
            scope.cleanup(function () {
                generation += 1;
                if (request) { request.abort(); }
                content.modal('destroy'); content.remove();
            });
            scope.delegate('click', '[data-role="template-structure-action"]', function (event, button) {
                event.preventDefault(); event.stopPropagation();
                opener = button;
                var context = element.veaContext;
                var current = ++generation;
                var code = button.getAttribute('data-template-code');
                var setId = button.getAttribute('data-attribute-set-id');
                content.empty().append(structure.render({state: 'loading', template: code}));
                content.modal('openModal');
                context.autosave.flush().then(function () {
                    if (current !== generation) { return; }
                    request = $.ajax({url: context.config.urls.structure, method: 'GET', dataType: 'json',
                        data: {template_code: code, attribute_set_id: setId}});
                    return request.then(function (response) {
                        if (current !== generation) { return; }
                        if (!response.success) { throw new Error(response.message); }
                        var root = structure.render(Object.assign({}, response.structure, {icons: context.config.structureIcons}));
                        content.empty().append(root);
                        element.dispatchEvent(new CustomEvent('ergonode:template-structure-rendered', {detail: {
                            root: root, structure: response.structure, templateCode: code, attributeSetId: setId
                        }}));
                    });
                }).catch(function (error) {
                    if (current === generation) {
                        content.empty().append(structure.render({state: 'error', template: code, message: error.message}));
                    }
                });
            });
        }
        element.addEventListener('ergonode:template-workspace-ready', mount, {once: true});
        mount();
    };
});
