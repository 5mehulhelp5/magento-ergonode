define([
    'jquery',
    'mage/translate',
    'Ergonode_CoreAdminUi/js/workspace',
    'Ergonode_CoreAdminUi/js/workspace-context',
    'Ergonode_CoreAdminUi/js/buttons',
    'Ergonode_CoreAdminUi/js/visibility-toggle',
    'Ergonode_CoreAdminUi/js/request',
    'Ergonode_CoreAdminUi/js/search',
    'Ergonode_CoreAdminUi/js/drag-drop',
    'Ergonode_CoreAdminUi/js/source-bulk-transfer',
    'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_TemplateAdminUi/js/template-draft-pairing',
    'Ergonode_TemplateAdminUi/js/template-autosave',
    'Ergonode_TemplateAdminUi/js/template-source-options'
], function ($, $t, workspace, workspaceContext, buttons, visibilityToggle, request, search, dragDrop, sourceBulkTransfer, entityOptions, templateDraftPairing, templateAutosave, templateSourceOptions) {
    'use strict';

    return function (config, element) {
        return workspace.mount(element, function (scope) {
        var $root = $(element);
        var templates = Array.isArray(config.templates) ? config.templates : [];
        var attributeSets = Array.isArray(config.attribute_sets) ? config.attribute_sets : [];
        var mappings = {};
        var persistedMappings = {};
        var drafts = [];
        var nextDraftId = 1;
        var selectedCode = '';
        var activeDragPayload = null;
        var pendingAttributeSetMarker = '__create_magento_attribute_set__';
        var draftTemplateActions = [];

        var $templateList = $root.find('[data-role="unmapped-template-list"]');
        var $setList = $root.find('[data-role="unmapped-set-list"]');
        var $pairList = $root.find('[data-role="mapped-pair-list"]');
        var $templateSearch = $root.find('[data-role="template-search"]');
        var $setSearch = $root.find('[data-role="attribute-set-search"]');
        var $draftTemplate = $root.find('[data-role="draft-template"]');
        var $draftSet = $root.find('[data-role="draft-set"]');
        var context = workspaceContext.create(scope, element, {
            publish: false,
            serialize: function () {
                return {
                    mappings: mappings,
                    drafts: serializeDrafts(),
                    visibility: serializeVisibility()
                };
            }
        });
        context.registerDraftTemplateAction = registerDraftTemplateAction;
        element.veaWorkspace = scope;
        scope.cleanup(function () {
            delete element.veaWorkspace;
        });
        var messageBus = context.message;
        var dirtyTracker = context.dirty;
        var autosave = templateAutosave.create(element, config, {
            serialize: function () {
                return {
                    mappings: Object.assign({}, mappings),
                    visibility: serializeVisibility(),
                    createsRemoteEntity: hasPendingAttributeSetMappings()
                };
            },
            onSaved: function (response, snapshot) {
                dirtyTracker.capture();
                persistedMappings = Object.assign({}, mappings);
                renderAll();
                if (snapshot.createsRemoteEntity) {
                    messageBus.show('success', response.message || $t('Mapowania zostały zapisane.'));
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 350);
                }
            }
        });

        element.veaContext = context;
        context.config = config;
        context.autosave = autosave;

        try {
            init();
            element.dispatchEvent(new CustomEvent('ergonode:template-workspace-ready'));
        } catch (exception) {
            renderInitError(exception);
        }

        function renderInitError(exception) {
            var message = exception && exception.message ? exception.message : String(exception || '');

            $root.addClass('is-init-error');
            messageBus.show('error', $t('Nie udało się zainicjalizować widoku Templates: %1').replace('%1', message));
            if (window.console && typeof window.console.error === 'function') {
                window.console.error(exception);
            }
        }

        function init() {
            config.urls = config.urls || {};
            config.form_key = config.form_key || window.FORM_KEY || '';

            templates.forEach(function (template) {
                if (template.attribute_set_id) {
                    mappings[template.code] = Number(template.attribute_set_id);
                    persistedMappings[template.code] = Number(template.attribute_set_id);
                }
            });
            selectedCode = Object.keys(mappings).sort()[0] || '';
            templateSourceOptions.initialize(element, hasExcludedSources());
            hydrateInitialView();
            if (config.urls.structure) {
                $pairList.children('[data-template-code]').each(function () {
                    var code = this.getAttribute('data-template-code');
                    var template = getTemplate(code);
                    var attributeSet = getAttributeSet(mappings[code], code);
                    if (template && attributeSet) { appendStructureAction($(this), template, attributeSet); }
                });
            }
            bindEvents();
            dirtyTracker.capture();
        }

        function hydrateInitialView() {
            $templateList.find('[data-role="entity-card"][data-template-code]').each(function () {
                var template = getTemplate(String($(this).data('template-code') || ''));

                if (template) {
                    enhanceTemplateCard(this, template);
                }
            });
            templateSourceOptions.sync(element, hasExcludedSources());
        }

        function bindEvents() {
            entityOptions.bind(scope, element);
            sourceBulkTransfer.bind(scope, element, {
                isSelectable: function (card) {
                    if (card.getAttribute('data-drag-type') === 'template') {
                        return canUseTemplateInDraft(card.getAttribute('data-template-code') || '');
                    }
                    if (card.getAttribute('data-drag-type') === 'attribute_set') {
                        return canUseAttributeSetInDraft(card.getAttribute('data-attribute-set-id') || '');
                    }

                    return false;
                },
                transfer: function (card) {
                    if (card.getAttribute('data-drag-type') === 'template') {
                        return addDraftTemplate(card.getAttribute('data-template-code') || '', true);
                    }
                    if (card.getAttribute('data-drag-type') === 'attribute_set') {
                        return addDraftAttributeSet(card.getAttribute('data-attribute-set-id') || '', true);
                    }

                    return false;
                }
            });
            search.bind(scope, {
                inputSelector: '[data-role="template-search"]',
                update: renderTemplateList
            });
            search.bind(scope, {
                inputSelector: '[data-role="attribute-set-search"]',
                update: renderSetList
            });
            bindSourceSorting();
            scope.delegate('dragstart', '[draggable="true"][data-drag-type]', function (event, source) {
                var payloadObject = buildDragPayload($(source));

                activeDragPayload = payloadObject;
                $(source).addClass('is-dragging');
                setTemplateDragState(payloadObject, true);
                dragDrop.write(event.dataTransfer, payloadObject, 'application/json');
                event.dataTransfer.effectAllowed = 'move';
            });

            scope.delegate('dragend', '[draggable="true"][data-drag-type]', function (event, source) {
                $(source).removeClass('is-dragging');
                $root.find('[data-drop-zone]').removeClass('is-over');
                activeDragPayload = null;
                setTemplateDragState(null, false);
            });

            scope.delegate('dragover', '[data-drop-zone]', function (event, dropZone) {
                var zone = String($(dropZone).data('drop-zone') || '');
                var payload = activeDragPayload || readDragPayload(event);

                if (!acceptsDropZone(zone, payload)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                event.dataTransfer.dropEffect = 'move';
                $(dropZone).addClass('is-over');
            });

            scope.delegate('dragleave', '[data-drop-zone]', function (event, dropZone) {
                event.stopPropagation();
                if (!dragDrop.isLeaving(dropZone, event)) {
                    return;
                }

                $(dropZone).removeClass('is-over');
            });

            scope.delegate('drop', '[data-drop-zone]', function (event, dropZone) {
                var zone = String($(dropZone).data('drop-zone') || '');
                var payload = activeDragPayload || readDragPayload(event);

                if (!acceptsDropZone(zone, payload)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                $root.find('[data-drop-zone]').removeClass('is-over');
                activeDragPayload = null;
                setTemplateDragState(null, false);
                handleDrop(zone, payload);
            });

            scope.delegate('dblclick', '[data-role="unmapped-template-list"] [data-template-code]', function (event, card) {
                if (event.target.closest('[data-role="entity-options"], [data-role="source-active-toggle"]')) {
                    return;
                }

                addDraftTemplate(String($(card).data('template-code') || ''), true);
            });

            scope.delegate('dblclick', '[data-role="unmapped-set-list"] [data-attribute-set-id]', function (event, card) {
                if (event.target.closest('[data-role="source-active-toggle"]')) {
                    return;
                }

                addDraftAttributeSet(String($(card).data('attribute-set-id') || ''), true);
            });

            scope.delegate('click', '[data-role="source-active-toggle"]', function (event, button) {
                var $card = $(button).closest('[data-role="entity-card"]');

                event.preventDefault();
                event.stopPropagation();
                setSourceActive(
                    String($card.data('source') || ''),
                    String($card.data('code') || ''),
                    !buttons.isPressed(button)
                );
            });

            scope.delegate('click', '[data-role="entity-add-to-mapping"]', function (event, button) {
                var card = button.closest('[data-template-code]');

                event.preventDefault();
                event.stopPropagation();
                if (card) {
                    addDraftTemplate(String($(card).data('template-code') || ''), true);
                }
            });

            scope.delegate('click', '[data-role="visibility-toggle"]', function (event, button) {
                visibilityToggle.toggle(button);
                renderTemplateList();
                renderSetList();
            });

            scope.delegate('click', '[data-role="mapped-pair-list"] [data-template-code]', function (event, card) {
                if (event.target.closest('[data-role="unlink-pair"], [data-role="template-structure-action"]')) {
                    return;
                }

                selectPair(String($(card).data('template-code') || ''));
            });

            scope.delegate('click', '[data-role="unlink-pair"]', function (event, button) {
                event.stopPropagation();
                unlinkPair(String($(button).data('template-code') || ''));
            });

            scope.delegate('click', '[data-role="clear-draft"]', function (event, button) {
                event.stopPropagation();
                if (removeDraft(Number($(button).data('draft-id') || 0))) {
                    markDirty();
                    renderAll();
                }
            });

            scope.delegate('click', '[data-role="create-magento-attribute-set"]', function (event, button) {
                event.preventDefault();
                event.stopPropagation();
                createPendingAttributeSet(Number($(button).data('draft-id') || 0));
            });

            scope.delegate('keydown', '[data-drag-type]', function (event, card) {
                var type;

                if (event.target.closest('[data-role="entity-options"], button, input, label')) {
                    return;
                }

                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                type = card.getAttribute('data-drag-type') || '';
                if (type !== 'template' && type !== 'attribute_set') {
                    return;
                }

                event.preventDefault();
                if (type === 'template') {
                    addDraftTemplate(card.getAttribute('data-template-code') || '', true);
                } else {
                    addDraftAttributeSet(card.getAttribute('data-attribute-set-id') || '', true);
                }
            });

            scope.delegate('click', '[data-role="retry-autosave"]', function () {
                autosave.retry();
            });

        }

        function buildDragPayload($source) {
            return {
                type: String($source.data('drag-type') || ''),
                value: String($source.data('drag-value') || ''),
                draftId: String($source.data('draft-id') || ''),
                templateCode: String($source.data('template-code') || ''),
                attributeSetId: String($source.data('attribute-set-id') || '')
            };
        }

        function setTemplateDragState(payload, active) {
            var type = payload && payload.type ? payload.type : '';

            $root.toggleClass('is-template-dragging', active);
            $root.toggleClass('is-dragging-to-mapping', active && (type === 'template' || type === 'attribute_set'));
            $root.toggleClass('is-dragging-from-mapping', active && type.indexOf('mapped_') === 0);
        }

        function renderAll() {
            renderTemplateList();
            renderSetList();
            renderPairList();
            renderDraft();
            templateSourceOptions.sync(element, hasExcludedSources());
        }

        function bindSourceSorting() {
            $root.find(
                '[data-role="template-drop-source"], [data-role="attribute-set-drop-source"]'
            ).each(function () {
                var panel = this;
                var attributeSetPanel = panel.getAttribute('data-role') === 'attribute-set-drop-source';

                search.bindSort(scope, panel, {
                    toggleSelector: '[data-role="source-sort-toggle"]',
                    directionSelector: '[data-role="source-sort-direction"]',
                    directionLabelSelector: '.veui-entity-options-action-label',
                    labelSelector: '.veui-entity-options-action-label',
                    defaultValue: 'label',
                    values: ['label', 'code'],
                    options: {
                        label: {label: $t('Nazwa'), ariaLabel: $t('Sortuj po nazwie')},
                        code: {
                            label: attributeSetPanel ? $t('ID') : $t('Kod'),
                            ariaLabel: attributeSetPanel ? $t('Sortuj po ID') : $t('Sortuj po kodzie')
                        }
                    },
                    ascLabel: $t('Sortuj rosnąco'),
                    ascOptionLabel: $t('Góra'),
                    descLabel: $t('Sortuj malejąco'),
                    descOptionLabel: $t('Dół'),
                    update: updateSourcePanel
                });
                sortSourcePanel(panel);
            });
        }

        function updateSourcePanel(panel) {
            if (panel.getAttribute('data-role') === 'template-drop-source') {
                renderTemplateList();
                return;
            }

            renderSetList();
        }

        function sortSourcePanel(panel) {
            var templatePanel = panel && panel.getAttribute('data-role') === 'template-drop-source';
            var list = panel ? panel.querySelector(templatePanel
                ? '[data-role="unmapped-template-list"]'
                : '[data-role="unmapped-set-list"]') : null;
            var toggle = panel ? panel.querySelector('[data-role="source-sort-toggle"]') : null;
            var direction = panel ? panel.querySelector('[data-role="source-sort-direction"]') : null;
            var value = toggle ? toggle.getAttribute('data-sort-value') || 'label' : 'label';

            search.sort(list, '[data-role="entity-card"]', {
                direction: direction ? direction.getAttribute('data-direction') || 'asc' : 'asc',
                value: function (card) {
                    return card.getAttribute(value === 'code' ? 'data-code' : 'data-label') || '';
                },
                tie: function (first, second) {
                    return search.normalize(first.getAttribute('data-code') || '').localeCompare(
                        search.normalize(second.getAttribute('data-code') || ''),
                        'pl',
                        {numeric: true, sensitivity: 'base'}
                    );
                }
            });
        }

        function renderTemplateList() {
            var query = search.normalize($templateSearch.val());
            var visible = 0;

            $templateList.empty();
            if (!templates.length) {
                return;
            }

            templates.forEach(function (template) {
                if (mappings[template.code] || isTemplateDrafted(template.code)) {
                    return;
                }

                if (!isSourceActive(template) && !showsOmittedSources()) {
                    return;
                }

                if (query && search.normalize(
                    templateDisplayName(template) + ' ' + template.code
                ).indexOf(query) === -1) {
                    return;
                }

                visible++;
                $templateList.append(buildTemplateCard(template));
            });

            sortSourcePanel($root.find('[data-role="template-drop-source"]')[0]);
            if (!visible) {
                $templateList.append($('<div/>', {class: 'vet-empty-row'}).text($t('Brak niezmapowanych template.')));
            }
            element.dispatchEvent(new CustomEvent('ergonode:template-rendered'));
        }

        function renderSetList() {
            var query = search.normalize($setSearch.val());
            var usedIds = getUsedAttributeSetIds();
            var visible = 0;

            $setList.empty();
            attributeSets.forEach(function (attributeSet) {
                var id = Number(attributeSet.id);
                if (usedIds[id] || isAttributeSetDrafted(id)) {
                    return;
                }

                if (!isSourceActive(attributeSet) && !showsOmittedSources()) {
                    return;
                }

                if (query && search.normalize(attributeSet.name + ' ' + id).indexOf(query) === -1) {
                    return;
                }

                visible++;
                $setList.append(buildAttributeSetCard(attributeSet));
            });

            sortSourcePanel($root.find('[data-role="attribute-set-drop-source"]')[0]);
            if (!visible) {
                $setList.append($('<div/>', {class: 'vet-empty-row'}).text($t('Brak niezmapowanych attribute setów.')));
            }
        }

        function renderPairList() {
            var codes = Object.keys(mappings).sort();

            $pairList.empty();
            codes.forEach(function (templateCode) {
                var template = getTemplate(templateCode);
                var attributeSet = getAttributeSet(mappingAttributeSetId(mappings[templateCode]), templateCode);

                if (!template || !attributeSet) {
                    return;
                }

                $pairList.append(buildPairCard(template, attributeSet));
            });

            drafts.forEach(function (draft) {
                $pairList.append(buildDraftPairCard(draft));
            });
            if (!codes.length && !drafts.length) {
                $pairList.append(
                    $('<p/>', {class: 'vea-attribute-empty-state vet-mapping-empty'})
                        .text($t('Nie ma jeszcze mapowań. Wybierz element z lewej i z prawej strony.'))
                );
            }
        }

        function buildTemplateCard(template) {
            var displayName = templateDisplayName(template);
            var $card = $('<div/>', {
                class: 'vea-attribute-card vet-map-card vet-template-card vet-status-' +
                    (template.status_tone || 'attention') + (isSourceActive(template) ? '' : ' is-inactive'),
                'data-role': 'entity-card',
                'data-source': 'ergo',
                'data-code': template.code,
                'data-label': displayName,
                'data-search': displayName + ' ' + template.code,
                'data-drag-type': 'template',
                'data-drag-value': template.code,
                'data-template-code': template.code,
                role: 'group',
                tabindex: '0',
                'aria-label': $t('Dodaj template do mapowania: %1').replace('%1', displayName)
            });
            $card.attr('draggable', isSourceActive(template) ? 'true' : 'false');

            $('<span/>', {class: 'vea-drag-handle vet-drag-handle', 'aria-hidden': 'true'}).appendTo($card);
            var $copy = $('<div/>', {class: 'vea-card-copy vet-card-copy'}).appendTo($card);
            $('<strong/>').text(displayName).appendTo($copy);
            $('<span/>', {class: 'vea-card-subline'}).text(template.code).appendTo($copy);
            buildActiveToggle(
                isSourceActive(template),
                $t('Włączony szablon do mapowania'),
                $t('Włącz / wyłącz template w mapowaniu')
            ).appendTo($card);
            enhanceTemplateCard($card[0], template);

            return $card;
        }

        function enhanceTemplateCard(card, template) {
            var displayName = templateDisplayName(template);
            var activeToggle = card.querySelector('[data-role="source-active-toggle"]');
            var placeholder = card.querySelector('[data-role="entity-options-placeholder"]');
            var menu = entityOptions.enhance(card, {
                active: isSourceActive(template),
                actions: [{role: 'entity-add-to-mapping', label: $t('Dodaj do mapowania'),
                    iconClass: 'veui-entity-options-add-icon', requiresActive: true}],
                menuLabel: $t('Opcje szablonu') + ': ' + displayName,
                toggle: activeToggle
            });
            if (menu && placeholder) {
                placeholder.remove();
            }
        }

        function buildAttributeSetCard(attributeSet) {
            var displayName = attributeSetDisplayName(attributeSet);
            var $card = $('<div/>', {
                class: 'vea-attribute-card vet-map-card vet-set-card' +
                    (isSourceActive(attributeSet) ? '' : ' is-inactive'),
                'data-role': 'entity-card',
                'data-source': 'magento',
                'data-code': String(attributeSet.id),
                'data-label': displayName,
                'data-search': displayName + ' ' + attributeSet.id,
                'data-drag-type': 'attribute_set',
                'data-drag-value': attributeSet.id,
                'data-attribute-set-id': attributeSet.id,
                role: 'group',
                tabindex: '0',
                'aria-label': $t('Dodaj attribute set do mapowania: %1').replace('%1', displayName)
            });
            $card.attr('draggable', isSourceActive(attributeSet) ? 'true' : 'false');

            $('<span/>', {class: 'vea-drag-handle vet-drag-handle', 'aria-hidden': 'true'}).appendTo($card);
            var $copy = $('<div/>', {class: 'vea-card-copy vet-card-copy'}).appendTo($card);
            $('<strong/>').text(displayName).appendTo($copy);
            $('<span/>', {class: 'vea-card-subline'}).text('#' + attributeSet.id).appendTo($copy);
            buildActiveToggle(
                isSourceActive(attributeSet),
                $t('Włączony zestaw atrybutów do mapowania'),
                $t('Włącz / wyłącz attribute set w mapowaniu')
            ).appendTo($card);

            return $card;
        }

        function buildActiveToggle(active, ariaLabel, title) {
            return $('<button/>', {
                type: 'button',
                class: 'vea-card-toggle',
                'data-role': 'source-active-toggle',
                'aria-label': ariaLabel,
                'aria-pressed': active ? 'true' : 'false',
                title: title
            }).append($('<span/>', {'aria-hidden': 'true'}));
        }

        function appendFilledPairContent($side, label, identifier) {
            $('<strong/>').text(label).appendTo($side);
            $('<code/>')
                .text(identifier)
                .appendTo($('<span/>', {class: 'vea-card-subline'}).appendTo($side));
        }

        function appendEmptyPairContent($side, title, source) {
            $('<strong/>').text(title).appendTo($side);
            $('<span/>', {'data-role': 'slot-hint'}).text(source).appendTo($side);
        }

        function appendPairBadge($side, label, attributes) {
            var $target = $side.children('.vea-card-subline').first();

            return $('<span/>', attributes || {})
                .addClass('veui-pending-badge')
                .text(label)
                .appendTo($target.length ? $target : $side);
        }

        function buildPairCard(template, attributeSet) {
            var $card = $('<article/>', {
                class: 'vea-pair-row vea-attribute-pair-row vet-pair-card' +
                    (template.code === selectedCode ? ' is-active' : ''),
                'data-template-code': template.code
            });

            if (template.creating) {
                $card.addClass('vea-status-tone-warning');
            }

            var $ergo = $('<div/>', {
                class: 'vea-pair-card vet-pair-side',
                'data-drag-type': 'mapped_template',
                'data-drag-value': template.code,
                'data-template-code': template.code
            }).attr('draggable', 'true').appendTo($card);
            appendFilledPairContent($ergo, templateDisplayName(template), template.code);
            if (template.creating) {
                appendPairBadge($ergo, String(template.creating_label || $t('TWORZENIE')), {
                    'data-role': 'creating-template-badge',
                    title: String(template.creating_title || template.status || template.creating_label || ''),
                    'aria-label': String(template.creating_title || template.status || template.creating_label || '')
                });
            }
            $('<button/>', {
                type: 'button',
                class: 'vea-link-indicator vet-link-action',
                title: $t('Usuń mapowanie'),
                'aria-label': $t('Usuń mapowanie'),
                'data-role': 'unlink-pair',
                'data-template-code': template.code
            })
                .append($('<span/>', {'aria-hidden': 'true'}))
                .appendTo($card);

            var $magento = $('<div/>', {
                class: 'vea-pair-card vet-pair-side',
                'data-drag-type': 'mapped_attribute_set',
                'data-drag-value': attributeSet.id,
                'data-template-code': template.code,
                'data-attribute-set-id': attributeSet.id
            }).attr('draggable', 'true').appendTo($card);
            appendFilledPairContent(
                $magento,
                attributeSetDisplayName(attributeSet),
                attributeSetIdentifier(attributeSet)
            );

            if (!Object.prototype.hasOwnProperty.call(persistedMappings, template.code)) {
                appendPairBadge($magento, $t('ZAPISYWANIE'), {
                    'data-role': 'new-mapping-badge',
                    title: $t('Trwa automatyczny zapis nowego mapowania'),
                    'aria-label': $t('Trwa automatyczny zapis nowego mapowania')
                });
            }

            appendStructureAction($card, template, attributeSet);
            return $card;
        }

        function appendStructureAction($card, template, attributeSet) {
            if (!config.urls.structure) {
                return;
            }
            $('<button/>', {
                type: 'button', class: 'vea-option-map-action vet-structure-action',
                'data-role': 'template-structure-action',
                'data-template-code': template.code,
                'data-attribute-set-id': attributeSet.id,
                title: $t('Podgląd struktury zestawu atrybutów'),
                'aria-label': $t('Podgląd struktury zestawu atrybutów')
            }).append($('<span/>', {'aria-hidden': 'true'})).appendTo($card);
        }

        function buildDraftPairCard(draft) {
            var template = draft.templateCode ? getTemplate(draft.templateCode) : null;
            var attributeSet = draft.attributeSetId
                ? getAttributeSet(draft.attributeSetId, draft.templateCode)
                : null;
            var $card = $('<article/>', {
                class: 'vea-pair-row vea-attribute-pair-row vea-status-tone-warning vet-pair-card vet-draft-card'
            });
            var $templateSide = $('<div/>', {
                class: 'vea-pair-card vet-pair-side' + (template ? '' : ' is-empty'),
                'data-drag-type': template ? 'draft_template' : '',
                'data-drag-value': template ? template.code : '',
                'data-draft-id': draft.id,
                'data-template-code': template ? template.code : ''
            }).appendTo($card);
            if (template) {
                $templateSide.attr('draggable', 'true');
            } else {
                $templateSide.attr('data-drop-zone', 'draft-template:' + draft.id);
            }

            if (template) {
                appendFilledPairContent($templateSide, templateDisplayName(template), template.code);
            } else {
                appendEmptyPairContent($templateSide, $t('Upuść template'), $t('z Ergonode'));
            }
            renderDraftTemplateActions($templateSide[0], draft, attributeSet);

            $('<button/>', {
                type: 'button',
                class: 'vea-link-indicator vet-link-action',
                title: $t('Wyczyść szkic'),
                'aria-label': $t('Wyczyść szkic'),
                'data-role': 'clear-draft',
                'data-draft-id': draft.id
            })
                .append($('<span/>', {'aria-hidden': 'true'}))
                .appendTo($card);

            var $setSide = $('<div/>', {
                class: 'vea-pair-card vet-pair-side' + (attributeSet ? '' : ' is-empty'),
                'data-drag-type': attributeSet ? 'draft_attribute_set' : '',
                'data-drag-value': attributeSet ? attributeSet.id : '',
                'data-draft-id': draft.id,
                'data-attribute-set-id': attributeSet ? attributeSet.id : ''
            }).appendTo($card);
            if (attributeSet) {
                $setSide.attr('draggable', 'true');
            } else {
                $setSide.attr('data-drop-zone', 'draft-set:' + draft.id);
            }

            if (attributeSet) {
                appendFilledPairContent(
                    $setSide,
                    attributeSetDisplayName(attributeSet),
                    attributeSetIdentifier(attributeSet)
                );
            } else {
                appendEmptyPairContent($setSide, $t('Upuść attribute set'), $t('z Magento'));
            }
            if (template && !attributeSet && config.can_create_attribute_sets) {
                $('<button/>', {
                    type: 'button',
                    class: 'vea-create-option vet-create-attribute-set',
                    'data-role': 'create-magento-attribute-set',
                    'data-draft-id': draft.id,
                    'data-completes-missing-side': '1',
                    title: $t('Utwórz zestaw atrybutów w Magento z szablonu Ergonode'),
                    'aria-label': $t('Utwórz zestaw atrybutów w Magento z szablonu Ergonode')
                })
                    .append($('<span/>', {class: 'vea-create-option-icon', 'aria-hidden': 'true'}))
                    .append($('<span/>', {class: 'vea-visually-hidden'}).text($t('Utwórz zestaw atrybutów w Magento')))
                    .appendTo($setSide);
            }

            var $pendingSide = attributeSet ? $setSide : (template ? $templateSide : $());
            if ($pendingSide.length) {
                appendPairBadge($pendingSide, $t('SZKIC'), {
                    'data-role': 'new-mapping-badge',
                    title: $t('Szkic niepełnego mapowania'),
                    'aria-label': $t('Szkic niepełnego mapowania')
                });
            }

            return $card;
        }

        function renderDraft() {
            $draftTemplate.text('');
            $draftSet.text('');
        }

        function handleDrop(zone, payload) {
            if (!payload || !payload.type) {
                return;
            }

            if ((payload.type === 'mapped_template' || payload.type === 'mapped_attribute_set')
                && (zone === 'template-source' || zone === 'attribute-set-source')
            ) {
                removeMappedTemplateSide(payload);
                return;
            }

            if (payload.type === 'draft_template' && zone === 'template-source') {
                clearDraftSide(Number(payload.draftId || 0), 'template');
                renderAll();
                return;
            }

            if (payload.type === 'draft_attribute_set' && zone === 'attribute-set-source') {
                clearDraftSide(Number(payload.draftId || 0), 'set');
                renderAll();
                return;
            }

            if (zone.indexOf('draft-template:') === 0 && payload.type === 'template') {
                fillDraftTemplate(Number(zone.split(':')[1] || 0), payload.value);
                return;
            }

            if (zone.indexOf('draft-set:') === 0 && payload.type === 'attribute_set') {
                fillDraftAttributeSet(Number(zone.split(':')[1] || 0), payload.value);
                return;
            }

            if (payload.type === 'template') {
                addDraftTemplate(payload.value);
                return;
            }

            if (payload.type === 'attribute_set') {
                addDraftAttributeSet(payload.value);
                return;
            }

        }

        function addDraftTemplate(templateCode, pairComplementary) {
            var complementaryDraft;

            if (!canUseTemplateInDraft(templateCode)) {
                return false;
            }

            complementaryDraft = pairComplementary
                ? templateDraftPairing.findComplementary(drafts, 'template')
                : null;
            if (complementaryDraft) {
                return fillDraftTemplate(complementaryDraft.id, templateCode);
            }

            drafts.push({
                id: nextDraftId++,
                templateCode: String(templateCode),
                attributeSetId: ''
            });
            markDirty();
            renderAll();

            return true;
        }

        function addDraftAttributeSet(attributeSetId, pairComplementary) {
            var complementaryDraft;

            if (!canUseAttributeSetInDraft(attributeSetId)) {
                return false;
            }

            complementaryDraft = pairComplementary
                ? templateDraftPairing.findComplementary(drafts, 'attribute_set')
                : null;
            if (complementaryDraft) {
                return fillDraftAttributeSet(complementaryDraft.id, attributeSetId);
            }

            drafts.push({
                id: nextDraftId++,
                templateCode: '',
                attributeSetId: String(attributeSetId)
            });
            markDirty();
            renderAll();

            return true;
        }

        function fillDraftTemplate(draftId, templateCode) {
            var draft = getDraft(draftId);

            if (!draft || draft.templateCode || !canUseTemplateInDraft(templateCode)) {
                return false;
            }

            draft.templateCode = String(templateCode);
            completeDraftIfReady(draft);
            renderAll();

            return true;
        }

        function fillDraftAttributeSet(draftId, attributeSetId) {
            var draft = getDraft(draftId);

            if (!draft || draft.attributeSetId || !canUseAttributeSetInDraft(attributeSetId)) {
                return false;
            }

            draft.attributeSetId = String(attributeSetId);
            completeDraftIfReady(draft);
            renderAll();

            return true;
        }

        function completeDraftIfReady(draft, scheduleSave) {
            if (!draft || !draft.templateCode || !draft.attributeSetId) {
                return;
            }

            mappings[draft.templateCode] = isPendingAttributeSet(draft.attributeSetId)
                ? pendingAttributeSetMarker
                : Number(draft.attributeSetId);
            selectedCode = draft.templateCode;
            removeDraft(draft.id);
            if (scheduleSave !== false) {
                markDirty();
            }
        }

        function createPendingAttributeSet(draftId) {
            var draft = getDraft(draftId);

            if (!draft || !draft.templateCode || draft.attributeSetId) {
                return;
            }

            draft.attributeSetId = pendingAttributeSetMarker;
            completeDraftIfReady(draft);
            renderAll();
            messageBus.show('info', $t('Tworzę zestaw atrybutów w Magento i zapisuję mapowanie.'));
        }

        function registerDraftTemplateAction(action) {
            var active = true;

            if (!action || typeof action.render !== 'function') {
                return function () {};
            }
            draftTemplateActions.push(action);
            renderPairList();

            return function () {
                if (!active) {
                    return;
                }
                active = false;
                draftTemplateActions = draftTemplateActions.filter(function (candidate) {
                    return candidate !== action;
                });
                if (element.veaContext === context) {
                    renderPairList();
                }
            };
        }

        function renderDraftTemplateActions(container, draft, attributeSet) {
            if (!container || !attributeSet || draft.templateCode) {
                return;
            }
            draftTemplateActions.forEach(function (action) {
                action.render(container, {
                    attributeSet: Object.assign({}, attributeSet),
                    create: function (options) {
                        return createDraftTemplate(draft.id, options);
                    },
                    draftId: draft.id,
                    templates: templates.slice()
                });
            });
        }

        function createDraftTemplate(draftId, options) {
            var draft = getDraft(draftId);
            var attributeSet = draft ? getAttributeSet(draft.attributeSetId) : null;
            var placeholder = options && options.template;
            var code = String(placeholder && placeholder.code || '');
            var draftSnapshot;

            if (!draft || draft.templateCode || !attributeSet || isPendingAttributeSet(draft.attributeSetId)
                || !code || getTemplate(code) || !options || typeof options.request !== 'function'
            ) {
                return Promise.reject(new Error($t('Nie można utworzyć template dla tego szkicu.')));
            }

            draftSnapshot = {
                id: draft.id,
                templateCode: '',
                attributeSetId: String(attributeSet.id)
            };
            templates.push(placeholder);
            draft.templateCode = code;
            completeDraftIfReady(draft, false);
            renderAll();

            return Promise.resolve().then(options.request).then(function (response) {
                if (typeof options.applyResponse === 'function') {
                    options.applyResponse(placeholder, response);
                }
                renderAll();
                markDirty();

                return response;
            }).catch(function (error) {
                delete mappings[code];
                templates = templates.filter(function (template) {
                    return template !== placeholder;
                });
                drafts.push(draftSnapshot);
                selectedCode = Object.keys(mappings).sort()[0] || '';
                renderAll();
                throw error;
            });
        }

        function removeMappedTemplateSide(payload) {
            var templateCode = payload.templateCode || payload.value;
            var mapping = mappings[templateCode];
            var attributeSetId = mapping ? String(mappingAttributeSetId(mapping)) : '';

            if (!templateCode || !attributeSetId) {
                return;
            }

            delete mappings[templateCode];
            if (payload.type === 'mapped_template') {
                drafts.push({
                    id: nextDraftId++,
                    templateCode: '',
                    attributeSetId: attributeSetId
                });
            } else {
                drafts.push({
                    id: nextDraftId++,
                    templateCode: String(templateCode),
                    attributeSetId: ''
                });
            }

            if (selectedCode === templateCode) {
                selectedCode = Object.keys(mappings).sort()[0] || '';
            }

            markDirty();
            renderAll();
        }

        function unlinkPair(templateCode) {
            if (!mappings[templateCode]) {
                return;
            }

            delete mappings[templateCode];
            if (selectedCode === templateCode) {
                selectedCode = Object.keys(mappings).sort()[0] || '';
            }

            markDirty();
            renderAll();
        }

        function selectPair(templateCode) {
            selectedCode = mappings[templateCode] ? templateCode : '';
            renderAll();
        }

        function markDirty() {
            autosave.schedule();
        }

        function serializeDrafts() {
            return drafts.map(function (draft) {
                return {
                    template_code: draft.templateCode || null,
                    attribute_set_id: draft.attributeSetId || null
                };
            });
        }

        function serializeVisibility() {
            return templates.map(function (template) {
                return {
                    source: 'ergo',
                    code: String(template.code),
                    active: isSourceActive(template)
                };
            }).concat(attributeSets.map(function (attributeSet) {
                return {
                    source: 'magento',
                    code: String(attributeSet.id),
                    active: isSourceActive(attributeSet)
                };
            }));
        }

        function isSourceActive(source) {
            return source.active !== false;
        }

        function showsOmittedSources() {
            return visibilityToggle.isVisible($root.find('[data-role="visibility-toggle"]')[0]);
        }

        function hasExcludedSources() {
            return templates.some(function (template) {
                return !isSourceActive(template);
            }) || attributeSets.some(function (attributeSet) {
                return !isSourceActive(attributeSet);
            });
        }

        function setSourceActive(source, code, active) {
            var collection = source === 'ergo' ? templates : attributeSets;
            var item = collection.find(function (candidate) {
                return String(source === 'ergo' ? candidate.code : candidate.id) === code;
            });

            if (!item) {
                return;
            }

            item.active = active;
            markDirty();
            renderTemplateList();
            renderSetList();
            templateSourceOptions.sync(element, hasExcludedSources());
        }

        function getDraft(draftId) {
            draftId = Number(draftId);

            return drafts.find(function (draft) {
                return draft.id === draftId;
            }) || null;
        }

        function removeDraft(draftId) {
            var previousLength = drafts.length;

            draftId = Number(draftId);
            drafts = drafts.filter(function (draft) {
                return draft.id !== draftId;
            });

            return drafts.length !== previousLength;
        }

        function clearDraftSide(draftId, side) {
            var draft = getDraft(draftId);

            if (!draft) {
                return;
            }

            if (side === 'template') {
                draft.templateCode = '';
            } else {
                draft.attributeSetId = '';
            }

            if (!draft.templateCode && !draft.attributeSetId) {
                removeDraft(draft.id);
            }

            markDirty();
        }

        function isTemplateDrafted(templateCode) {
            templateCode = String(templateCode);

            return drafts.some(function (draft) {
                return draft.templateCode === templateCode;
            });
        }

        function isAttributeSetDrafted(attributeSetId) {
            attributeSetId = String(attributeSetId);

            return drafts.some(function (draft) {
                return String(draft.attributeSetId || '') === attributeSetId;
            });
        }

        function canUseTemplateInDraft(templateCode) {
            templateCode = String(templateCode);

            return Boolean(getTemplate(templateCode))
                && isSourceActive(getTemplate(templateCode))
                && !mappings[templateCode]
                && !isTemplateDrafted(templateCode);
        }

        function canUseAttributeSetInDraft(attributeSetId) {
            attributeSetId = Number(attributeSetId);

            return Boolean(getAttributeSet(attributeSetId))
                && isSourceActive(getAttributeSet(attributeSetId))
                && !getUsedAttributeSetIds()[attributeSetId]
                && !isAttributeSetDrafted(attributeSetId);
        }

        function readDragPayload(event) {
            return dragDrop.read(event.dataTransfer, 'application/json');
        }

        function acceptsDropZone(zone, payload) {
            if (!payload || !payload.type || !zone) {
                return false;
            }

            if (zone === 'template-pair' || zone === 'draft') {
                return payload.type === 'template' || payload.type === 'attribute_set';
            }

            if (zone.indexOf('draft-template:') === 0) {
                return payload.type === 'template';
            }

            if (zone.indexOf('draft-set:') === 0) {
                return payload.type === 'attribute_set';
            }

            if (zone === 'template-source') {
                return payload.type === 'mapped_template' || payload.type === 'draft_template';
            }

            if (zone === 'attribute-set-source') {
                return payload.type === 'mapped_attribute_set' || payload.type === 'draft_attribute_set';
            }

            return false;
        }

        function getTemplate(code) {
            return templates.find(function (template) {
                return template.code === String(code);
            }) || null;
        }

        function getAttributeSet(id, templateCode) {
            if (isPendingAttributeSet(id)) {
                return {
                    id: pendingAttributeSetMarker,
                    name: String(templateCode || $t('Nowy zestaw atrybutów')),
                    active: true,
                    pending_create: true
                };
            }

            id = Number(id);
            return attributeSets.find(function (attributeSet) {
                return Number(attributeSet.id) === id;
            }) || null;
        }

        function templateDisplayName(template) {
            var name = String(template.name || '').trim();

            return name || template.code;
        }

        function attributeSetDisplayName(attributeSet) {
            var name = String(attributeSet.name || '').trim();

            return name || attributeSetIdentifier(attributeSet);
        }

        function attributeSetIdentifier(attributeSet) {
            return attributeSet.pending_create
                ? $t('Nowy zestaw atrybutów')
                : '#' + attributeSet.id;
        }

        function isPendingAttributeSet(attributeSetId) {
            return String(attributeSetId || '') === pendingAttributeSetMarker;
        }

        function hasPendingAttributeSetMappings() {
            return Object.keys(mappings).some(function (templateCode) {
                return isPendingAttributeSet(mappingAttributeSetId(mappings[templateCode]));
            });
        }

        function mappingAttributeSetId(mapping) {
            return mapping;
        }

        function getUsedAttributeSetIds() {
            var used = {};
            Object.keys(mappings).forEach(function (templateCode) {
                var attributeSetId = mappingAttributeSetId(mappings[templateCode]);

                if (!isPendingAttributeSet(attributeSetId)) {
                    used[Number(attributeSetId)] = true;
                }
            });

            return used;
        }

        });
    };
});
