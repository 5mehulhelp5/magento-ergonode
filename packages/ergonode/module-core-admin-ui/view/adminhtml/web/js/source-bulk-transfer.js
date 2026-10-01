define([
    'Ergonode_CoreAdminUi/js/entity-options',
    'mage/translate'
], function (entityOptions, $t) {
    'use strict';

    var cardSelector = '[data-role="entity-card"]';
    var panelSelector = '[data-source-panel]';
    var selectionSelector = '[data-role="source-bulk-select"]';

    function sourceLabel(panel) {
        return panel && panel.getAttribute('data-source-panel') === 'magento'
            ? 'Magento'
            : 'Ergonode';
    }

    function cardLabel(card) {
        return card.getAttribute('data-label') || card.getAttribute('data-code') || '';
    }

    function isCardActive(card) {
        var toggle = card.querySelector(
            '[data-role="source-active-toggle"], [data-role="attribute-active-toggle"]'
        );

        return !toggle || toggle.getAttribute('aria-pressed') === 'true';
    }

    function isSelectable(card, options) {
        options = options || {};

        if (!card || card.getAttribute('data-mapped') === '1'
            || card.getAttribute('aria-disabled') === 'true'
            || card.classList.contains('is-mapped')
            || !isCardActive(card)) {
            return false;
        }

        return !options.isSelectable || options.isSelectable(card);
    }

    function isVisible(card) {
        return !card.hidden && !card.classList.contains('is-filter-hidden');
    }

    function selection(card) {
        return card ? card.querySelector(selectionSelector) : null;
    }

    function panelCards(panel) {
        return Array.prototype.slice.call(panel.querySelectorAll(cardSelector));
    }

    function selectableCards(panel, options, visibleOnly) {
        return panelCards(panel).filter(function (card) {
            return isSelectable(card, options) && (!visibleOnly || isVisible(card));
        });
    }

    function selectedCards(panel, options) {
        return selectableCards(panel, options, false).filter(function (card) {
            var input = selection(card);

            return !!(input && input.checked);
        });
    }

    function resolveSelectionState(availableCount, selectedVisibleCount, selectedCount) {
        return {
            allChecked: availableCount > 0 && selectedVisibleCount === availableCount,
            disabled: selectedCount === 0
        };
    }

    function setCardSelected(card, checked) {
        var input = selection(card);

        if (!input) {
            return;
        }
        input.checked = checked;
        card.classList.toggle('is-bulk-selected', checked);
    }

    function createCardSelection(card) {
        var label = document.createElement('label');
        var input = document.createElement('input');
        var marker = document.createElement('span');
        var description = $t('Zaznacz element: %1').replace('%1', cardLabel(card));

        label.className = 'veui-source-bulk-selection';
        label.title = description;
        label.setAttribute('draggable', 'false');
        label.setAttribute('data-role', 'source-bulk-selection');
        input.type = 'checkbox';
        input.setAttribute('data-role', 'source-bulk-select');
        input.setAttribute('aria-label', description);
        marker.setAttribute('aria-hidden', 'true');
        label.append(input, marker);
        card.classList.add('has-bulk-selection');
        card.insertBefore(label, card.firstChild);

        return input;
    }

    function ensureCardSelection(card) {
        var input = selection(card) || createCardSelection(card);
        var description = $t('Zaznacz element: %1').replace('%1', cardLabel(card));

        input.setAttribute('aria-label', description);
        input.parentElement.title = description;

        return input;
    }

    function createSelectAllAction() {
        return entityOptions.createAction({
            disabled: true,
            iconClass: 'veui-entity-options-selection-icon',
            label: $t('Zaznacz wszystkie'),
            role: 'source-bulk-select-all'
        });
    }

    function updateSelectAllAction(button, panel, allChecked) {
        var label = allChecked ? $t('Odznacz wszystkie') : $t('Zaznacz wszystkie');
        var description = allChecked
            ? $t('Odznacz wszystkie widoczne elementy: %1')
            : $t('Zaznacz wszystkie widoczne elementy: %1');
        var labelNode = button.querySelector('.veui-entity-options-action-label');

        if (labelNode.textContent !== label) {
            labelNode.textContent = label;
        }
        button.setAttribute('aria-label', label);
        button.title = description.replace('%1', sourceLabel(panel));
        button.classList.toggle('is-all-selected', allChecked);
    }

    function createAddToMappingAction(panel) {
        var description = $t('Dodaj zaznaczone elementy z %1 do mapowania')
            .replace('%1', sourceLabel(panel));

        return entityOptions.createAction({
            ariaLabel: description,
            disabled: true,
            label: $t('Dodaj do mapowania'),
            role: 'source-bulk-add-to-mapping',
            title: description
        });
    }

    function ensurePanelControls(panel) {
        var optionsMenu = panel.querySelector('.veui-source-options .veui-entity-options-menu');
        var selectAll = optionsMenu
            ? optionsMenu.querySelector('[data-role="source-bulk-select-all"]')
            : null;
        var addToMapping = optionsMenu
            ? optionsMenu.querySelector('[data-role="source-bulk-add-to-mapping"]')
            : null;
        if (!optionsMenu) {
            return false;
        }
        if (!addToMapping) {
            addToMapping = createAddToMappingAction(panel);
            optionsMenu.insertBefore(addToMapping, optionsMenu.firstChild);
        }
        if (!selectAll) {
            optionsMenu.insertBefore(createSelectAllAction(), addToMapping);
        }

        return true;
    }

    function syncPanel(panel, options) {
        var all = panelCards(panel);
        var available = selectableCards(panel, options, true);
        var selected = selectedCards(panel, options);
        var selectedVisible = available.filter(function (card) {
            var input = selection(card);

            return !!(input && input.checked);
        });
        var state = resolveSelectionState(available.length, selectedVisible.length, selected.length);
        var selectAll = panel.querySelector('[data-role="source-bulk-select-all"]');
        var addToMapping = panel.querySelector('[data-role="source-bulk-add-to-mapping"]');

        all.forEach(function (card) {
            var input = ensureCardSelection(card);
            var selectable = isSelectable(card, options);

            input.disabled = !selectable;
            if (!selectable) {
                setCardSelected(card, false);
            } else {
                card.classList.toggle('is-bulk-selected', input.checked);
            }
        });
        if (selectAll) {
            selectAll.disabled = available.length === 0;
            updateSelectAllAction(selectAll, panel, state.allChecked);
        }
        if (addToMapping) {
            addToMapping.disabled = state.disabled;
            addToMapping.setAttribute('data-selected-count', String(selected.length));
        }
    }

    function enhance(root, options) {
        root.querySelectorAll(panelSelector).forEach(function (panel) {
            if (!ensurePanelControls(panel)) {
                return;
            }
            panelCards(panel).forEach(ensureCardSelection);
            syncPanel(panel, options);
        });
    }

    function bind(scope, root, options) {
        var observer;
        var frame = null;
        var scheduled = false;

        options = options || {};

        function scheduleEnhance() {
            if (scheduled) {
                return;
            }
            scheduled = true;
            frame = window.requestAnimationFrame(function () {
                frame = null;
                scheduled = false;
                enhance(root, options);
            });
        }

        enhance(root, options);
        scope.delegate('change', selectionSelector, function (event, input) {
            var card = input.closest(cardSelector);
            var panel = input.closest(panelSelector);

            if (!card || !panel) {
                return;
            }
            setCardSelected(card, input.checked);
            syncPanel(panel, options);
        });
        scope.delegate('click', '[data-role="source-bulk-select-all"]', function (event, button) {
            var panel = button.closest(panelSelector);
            var available;
            var allChecked;

            event.preventDefault();
            if (!panel || button.disabled) {
                return;
            }
            available = selectableCards(panel, options, true);
            allChecked = available.every(function (card) {
                var input = selection(card);

                return !!(input && input.checked);
            });
            available.forEach(function (card) {
                setCardSelected(card, !allChecked);
            });
            syncPanel(panel, options);
        });
        scope.delegate('click', '[data-role="source-bulk-add-to-mapping"]', function (event, button) {
            var panel = button.closest(panelSelector);
            var transferred = 0;

            event.preventDefault();
            if (!panel || button.disabled || typeof options.transfer !== 'function') {
                return;
            }
            selectedCards(panel, options).reverse().forEach(function (card) {
                if (options.transfer(card) !== false) {
                    transferred++;
                    setCardSelected(card, false);
                }
            });
            syncPanel(panel, options);
            if (transferred > 0 && typeof options.afterTransfer === 'function') {
                options.afterTransfer(transferred, panel);
            }
        });

        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver(scheduleEnhance);
            observer.observe(root, {
                attributeFilter: ['aria-disabled', 'aria-pressed', 'data-mapped', 'hidden'],
                attributes: true,
                childList: true,
                subtree: true
            });
            scope.cleanup(function () {
                observer.disconnect();
                if (frame !== null) {
                    window.cancelAnimationFrame(frame);
                }
            });
        }

        return {
            refresh: function (panel) {
                if (panel) {
                    syncPanel(panel, options);
                    return;
                }
                enhance(root, options);
            }
        };
    }

    return {
        bind: bind,
        isSelectable: isSelectable,
        resolveSelectionState: resolveSelectionState
    };
});
