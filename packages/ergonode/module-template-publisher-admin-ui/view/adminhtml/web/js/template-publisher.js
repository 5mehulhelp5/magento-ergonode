define([
    'mage/translate',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/request',
    'Ergonode_TemplatePublisherAdminUi/js/template-code'
], function ($t, buttons, request, templateCode) {
    'use strict';

    function actionTitle(collision) {
        if (collision && collision.source === 'code') {
            return $t('Nie można utworzyć template: kod %1 jest już używany w Ergonode.')
                .replace('%1', collision.candidateCode);
        }
        if (collision) {
            return $t('Nie można utworzyć template: nazwa %1 w Ergonode odpowiada kodowi %2.')
                .replace('%1', collision.matchedName)
                .replace('%2', collision.candidateCode);
        }

        return $t('Utwórz template w Ergonode z zestawu atrybutów Magento');
    }

    function createButton(state, config, element) {
        var attributeSet = state.attributeSet;
        var collision = templateCode.findCreationCollision(attributeSet.name, attributeSet.id, state.templates);
        var title = actionTitle(collision);
        var button = document.createElement('button');
        var icon = document.createElement('span');
        var hiddenLabel = document.createElement('span');

        button.type = 'button';
        button.className = 'vea-create-option vea-create-ergonode vet-create-template';
        button.dataset.role = 'create-ergonode-template';
        button.dataset.draftId = String(state.draftId);
        button.dataset.completesMissingSide = '1';
        button.dataset.collisionSource = collision ? collision.source : '';
        button.title = title;
        button.setAttribute('aria-label', title);
        button.disabled = Boolean(collision);
        icon.className = 'veui-create-ergonode-icon';
        icon.setAttribute('aria-hidden', 'true');
        hiddenLabel.className = 'vea-visually-hidden';
        hiddenLabel.textContent = $t('Utwórz template w Ergonode');
        button.append(icon, hiddenLabel);

        if (!collision) {
            button.addEventListener('click', function (event) {
                var code = templateCode.candidateFromAttributeSet(attributeSet.name, attributeSet.id);
                var message = element.veaContext.message;

                event.preventDefault();
                event.stopPropagation();
                message.show('info', $t('Tworzę template w Ergonode...'));
                buttons.run(button, function () {
                    return state.create({
                        template: {
                            code: code,
                            name: attributeSet.name,
                            attribute_set_id: null,
                            status: $t('Tworzenie'),
                            status_tone: 'attention',
                            active: true,
                            creating: true,
                            creating_label: $t('TWORZENIE'),
                            creating_title: $t('Tworzenie template w Ergonode')
                        },
                        request: function () {
                            return request.post(config.urls.create, config, {
                                template_code: code,
                                attribute_set_id: attributeSet.id
                            });
                        },
                        applyResponse: function (createdTemplate, response) {
                            createdTemplate.name = String(
                                response.template && response.template.name || createdTemplate.name || code
                            );
                            createdTemplate.creating = false;
                            createdTemplate.status = $t('Gotowy');
                            createdTemplate.status_tone = 'ok';
                        }
                    });
                }, {
                    busyClass: 'is-working'
                }).then(function (response) {
                    message.show('success', response.message || $t('Template został utworzony w Ergonode.'));
                }).catch(function (error) {
                    message.show('error', error.message || $t('Nie udało się utworzyć template w Ergonode.'));
                });
            });
        }

        return button;
    }

    return function (config, element) {
        var unregister = null;
        var readyListener;
        var cleanupRegistered = false;

        config = config || {};
        config.urls = config.urls || {};
        config.form_key = config.form_key || window.FORM_KEY || '';

        function cleanup() {
            element.removeEventListener('ergonode:template-workspace-ready', readyListener);
            if (unregister) {
                unregister();
                unregister = null;
            }
        }

        function registerCleanup() {
            if (!cleanupRegistered && element.veaWorkspace
                && typeof element.veaWorkspace.cleanup === 'function'
            ) {
                cleanupRegistered = true;
                element.veaWorkspace.cleanup(cleanup);
            }
        }

        function register() {
            if (unregister || !element.veaContext
                || typeof element.veaContext.registerDraftTemplateAction !== 'function'
            ) {
                return Boolean(unregister);
            }
            unregister = element.veaContext.registerDraftTemplateAction({
                render: function (container, state) {
                    container.appendChild(createButton(state, config, element));
                }
            });
            registerCleanup();

            return true;
        }

        readyListener = function () {
            if (register()) {
                element.removeEventListener('ergonode:template-workspace-ready', readyListener);
            }
        };
        if (!register()) {
            element.addEventListener('ergonode:template-workspace-ready', readyListener);
        }
    };
});
