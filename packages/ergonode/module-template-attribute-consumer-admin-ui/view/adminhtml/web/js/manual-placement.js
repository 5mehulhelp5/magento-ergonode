define(['mage/translate', 'Ergonode_CoreAdminUi/js/request', 'Ergonode_CoreAdminUi/js/buttons'], function ($t, request, buttons) {
    'use strict';
    return function (options, element) {
        function enhance(event) {
            var detail = event.detail;
            var config = element.veaContext && element.veaContext.config;
            if (!config || !config.urls.manual_placement) { return; }
            var badge = detail.root.querySelector('.vets-readonly');
            if (badge && config.can_edit_placement) { badge.textContent = $t('Ustawienia rozmieszczenia'); }
            var attributes = detail.structure.attributes || [];
            detail.root.querySelectorAll('[data-role="placement-actions"]').forEach(function (slot) {
                var attribute = attributes.find(function (item) {
                    return String(item.attribute_id) === slot.getAttribute('data-attribute-id');
                });
                if (!attribute || slot.childElementCount) { return; }
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'vetp-manual-action';
                button.setAttribute('data-role', 'manual-placement');
                var icon = document.createElement('span');
                icon.className = 'vetp-pin';
                icon.setAttribute('aria-hidden', 'true');
                button.append(icon);
                var status = document.createElement('span');
                status.className = 'vetp-feedback';
                status.setAttribute('role', 'status');
                function update() {
                    var protectedPlacement = Boolean(attribute.placement_protected);
                    var manual = Boolean(attribute.manual_placement);
                    button.disabled = protectedPlacement || !config.can_edit_placement;
                    button.setAttribute('aria-pressed', String(manual || protectedPlacement));
                    button.setAttribute('aria-label', $t('Pozycja zarządzana ręcznie') + ': ' + attribute.attribute_code);
                    button.title = protectedPlacement
                        ? $t('Pozycja chroniona przez integrację. Synchronizacja zachowuje grupę i kolejność tego atrybutu.')
                        : (manual ? '' : $t('Włącz ręczne rozmieszczenie. ')) + $t('Synchronizacja zachowa grupę i kolejność atrybutu. Pozycję zmień w edycji zestawu atrybutów Magento. Atrybut pozostanie w zestawie po usunięciu z szablonu Ergonode.');
                }
                update();
                button.addEventListener('click', function () {
                    var target = !attribute.manual_placement;
                    status.textContent = '';
                    buttons.run(button, function () {
                        return request.post(config.urls.manual_placement, config, {
                            form_key: config.form_key, template_code: detail.templateCode,
                            attribute_set_id: detail.attributeSetId, attribute_id: attribute.attribute_id,
                            manual: target ? '1' : '0'
                        }).then(function () {
                            attribute.manual_placement = target;
                            status.textContent = $t('Zapisano');
                            var copy = slot.parentElement.querySelector('.vets-attribute-copy');
                            var retained = copy.querySelector('.vets-retained');
                            if (target && attribute.missing_from_template && !retained) {
                                retained = document.createElement('span');
                                retained.className = 'vets-retained';
                                retained.textContent = $t('Brak w szablonie Ergonode — zachowany ręcznie');
                                copy.append(retained);
                            } else if (!target && retained) { retained.remove(); }
                        });
                    }).catch(function (error) {
                        status.textContent = error.message || $t('Nie udało się zapisać ustawienia.');
                    }).finally(update);
                });
                slot.append(button, status);
            });
        }
        function mount() {
            var scope = element.veaWorkspace;
            if (!scope || !scope.claim(element, 'manual-placement')) { return; }
            scope.listen(element, 'ergonode:template-structure-rendered', enhance);
        }
        element.addEventListener('ergonode:template-workspace-ready', mount, {once: true});
        mount();
    };
});
