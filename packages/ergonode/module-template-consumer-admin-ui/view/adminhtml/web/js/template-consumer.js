define([
    'mage/translate', 'Ergonode_CoreAdminUi/js/buttons', 'Ergonode_CoreAdminUi/js/request',
    'Ergonode_CoreAdminUi/js/synchronization-actions', 'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_CoreAdminUi/js/snapshot-removal', 'Ergonode_TemplateConsumerAdminUi/js/template-refresh-state'
], function ($t, buttons, request, synchronizationActions, entityOptions, snapshotRemoval, refreshState) {
    'use strict';
    return function (options, element) {
        function mount() {
            var context = element.veaContext;
            var scope = element.veaWorkspace;
            if (!context || !scope || !scope.claim(element, 'template-consumer')) { return; }
            var config = context.config;
            var autosave = context.autosave;
            var messageBus = context.message;
            var templateRefreshState = refreshState.create(element);
            synchronizationActions.bind(scope, element);
            if (config.can_delete_snapshots) {
                snapshotRemoval.bind(scope, element, config, {
                    isDirty: context.dirty.isDirty.bind(context.dirty),
                    message: messageBus
                });
            } else {
                entityOptions.bind(scope, element);
            }
            function mappingAction(active) {
                return {
                    active: active,
                    iconClass: 'veui-entity-options-add-icon',
                    label: $t('Dodaj do mapowania'),
                    requiresActive: true,
                    role: 'entity-add-to-mapping'
                };
            }
            function enhanceSnapshots() {
                element.querySelectorAll('[data-role="unmapped-template-list"] [data-role="entity-card"]').forEach(function (card) {
                    var active = card.getAttribute('data-source-active') !== '0' && !card.classList.contains('is-inactive');
                    var enhancement = {
                        active: active,
                        menuLabel: $t('Opcje szablonu') + ': ' + card.getAttribute('data-label'),
                        toggle: card.querySelector('[data-role="source-active-toggle"]')
                    };
                    if (config.can_delete_snapshots) {
                        snapshotRemoval.enhance(card, Object.assign({}, enhancement, {
                            mappingAction: true,
                            snapshot: {
                                code: card.getAttribute('data-template-code'),
                                label: card.getAttribute('data-label')
                            }
                        }));
                        return;
                    }
                    entityOptions.enhance(card, Object.assign({}, enhancement, {
                        actions: [mappingAction(active)]
                    }));
                });
            }
            scope.listen(element, 'ergonode:template-rendered', enhanceSnapshots);
            enhanceSnapshots();
            if (config.can_refresh_templates) {
                var menu = element.querySelector('[data-template-source-options] .veui-entity-options-menu');
                var action = entityOptions.createAction({role: 'refresh-templates', className: 'vea-refresh-ergonode',
                    iconClass: 'vea-refresh-ergonode-icon', label: $t('Refresh'), title: $t('Odśwież szablony z Ergonode'), ariaLabel: $t('Odśwież szablony z Ergonode')});
                if (menu) { menu.prepend(action); }
                var emptyActions = element.querySelector('[data-role="template-empty-actions"]');
                if (emptyActions) {
                    var emptyAction = action.cloneNode(true);
                    emptyAction.classList.add('veui-button', 'vea-attribute-empty-refresh');
                    emptyActions.append(emptyAction);
                }
                scope.delegate('click', '[data-role="refresh-templates"]', function (event, button) {
                    refreshTemplates(button);
                });
            }
            scope.delegate('click', '[data-synchronization-action]', function (event, button) {
                synchronizeTemplates(
                    button,
                    button.getAttribute('data-synchronization-action') || 'sync'
                );
            });
        function languageMappingAction(error) {
            if (!error || error.message !== $t(
                'Configure at least one active Ergonode language mapped to an active Magento store scope.'
            )) {
                return null;
            }

            return {
                label: $t('Przejdź do mapowania języków'),
                url: config.urls && config.urls.language_mapping
            };
        }

        function refreshTemplates(refreshButton) {
            var summary = {changed: 0, unchanged: 0};

            buttons.run(refreshButton, function () {
                return autosave.flush().then(function () {
                    setTemplateRefreshState(
                        $t('Odświeżanie szablonów'),
                        $t('Pobieram szablony i ich strukturę. Zestawy atrybutów Magento pozostają bez zmian.')
                    );

                    return request.post(config.urls.refresh, config, {
                        form_key: config.form_key
                    }).then(function (response) {
                        summary.changed += Number(response.changed || 0);
                        summary.unchanged += Number(response.unchanged || 0);

                        return response;
                    });
                });
            }).then(function () {
                setTemplateRefreshState(
                    $t('Odświeżanie zakończone'),
                    $t('Zmienione: %1, bez zmian: %2. Przeładowuję widok.')
                        .replace('%1', summary.changed)
                        .replace('%2', summary.unchanged)
                );
                window.setTimeout(function () {
                    window.location.reload();
                }, 700);
            }).catch(function (error) {
                clearTemplateRefreshState();
                if (autosave.hasError()) {
                    return;
                }
                messageBus.error(
                    $t('Nie udało się odświeżyć template.'),
                    error,
                    $t('Nie udało się odświeżyć template.'),
                    languageMappingAction(error)
                );
            });
        }

        function synchronizeTemplates(syncButton, action) {
            var summary = {imported: 0, changed: 0};
            var resetOnly = action === 'reset-cursor';

            if (action !== 'sync' && !window.confirm($t(
                action === 'reset-cursor-and-sync'
                    ? 'Clear the saved template cursor and synchronize the complete template list?'
                    : 'Clear the saved template cursor? Each synchronization reads the complete list.'
            ))) {
                return;
            }

            buttons.run(syncButton, function () {
                var prepare = resetOnly ? Promise.resolve() : autosave.flush();

                return prepare.then(function () {
                    if (resetOnly) {
                        return request.post(config.urls.sync, config, {
                            form_key: config.form_key,
                            synchronization_action: action
                        });
                    }
                    setTemplateRefreshState(
                        $t('Synchronizowanie szablonów'),
                        $t('Pobieram dane z Ergonode i aktualizuję zestawy atrybutów Magento.')
                    );

                    return request.post(config.urls.sync, config, {
                        form_key: config.form_key,
                        synchronization_action: action
                    }).then(function (response) {
                        summary.imported += Number(response.imported || 0);
                        summary.changed += Number(response.changed || 0);

                        return response;
                    });
                });
            }).then(function () {
                if (resetOnly) {
                    messageBus.show('success', $t('The saved template cursor has been cleared.'));
                    return;
                }
                setTemplateRefreshState(
                    $t('Synchronizacja zakończona'),
                    $t('Pobrane: %1, zmienione: %2. Przeładowuję widok.')
                        .replace('%1', summary.imported)
                        .replace('%2', summary.changed)
                );
                window.setTimeout(function () {
                    window.location.reload();
                }, 700);
            }).catch(function (error) {
                clearTemplateRefreshState();
                if (autosave.hasError()) {
                    return;
                }
                messageBus.error(
                    $t('Nie udało się zsynchronizować template.'),
                    error,
                    $t('Nie udało się zsynchronizować template z Magento.'),
                    languageMappingAction(error)
                );
            });
        }

        function setTemplateRefreshState(title, text) {
            templateRefreshState.show(title, text);
        }

        function clearTemplateRefreshState() {
            templateRefreshState.clear();
        }


        }
        element.addEventListener('ergonode:template-workspace-ready', mount, {once: true});
        mount();
    };
});
