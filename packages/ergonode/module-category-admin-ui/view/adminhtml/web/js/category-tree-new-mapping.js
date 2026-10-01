define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'text!ui/template/modal/modal-popup.html'
], function ($, modal, $t, popupTemplate) {
    'use strict';

    function optionLabel(option) {
        var code = String(option && option.value || '').trim();
        var label = String(option && option.label || code).trim();

        if (!code || label.toLowerCase() === code.toLowerCase()) {
            return label;
        }

        return label + ' · ' + code;
    }

    function showStatus(dialog, type, message) {
        dialog.querySelectorAll('[data-role="tree-options-status"]').forEach(function (element) {
            element.hidden = false;
            element.classList.toggle('is-success', type === 'success');
            element.classList.toggle('is-error', type === 'error');
            element.textContent = message || '';
        });
    }

    function replaceTreeOptions(root, select, options) {
        var selectedValue = select.value;
        var availableValues = {};
        var labels = {};

        select.replaceChildren();
        (options || []).forEach(function (option) {
            var code = String(option && option.value || '');
            var optionElement = document.createElement('option');

            optionElement.value = code;
            optionElement.textContent = optionLabel(option);
            select.appendChild(optionElement);
            availableValues[code] = true;
            if (code) {
                labels[code] = String(option && option.label || code);
            }
        });
        select.value = availableValues[selectedValue] ? selectedValue : '';

        root.querySelectorAll('[data-role="category-tree-configuration"][data-tree-code]')
            .forEach(function (card) {
                var code = card.getAttribute('data-tree-code') || '';
                var label = card.querySelector('[data-role="configuration-option-label"]');

                if (label && labels[code]) {
                    label.textContent = labels[code];
                }
            });
    }

    function setBusy(button, busy) {
        if (!button) {
            return;
        }

        button.disabled = !!busy;
        button.classList.toggle('is-working', !!busy);
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function initRootPicker(select, scope) {
        var picker = select && select.closest('[data-role="mapping-root-picker"]');
        var trigger = picker && picker.querySelector('[data-role="mapping-root-picker-trigger"]');
        var value = picker && picker.querySelector('[data-role="mapping-root-picker-value"]');
        var panel = picker && picker.querySelector('[data-role="mapping-root-picker-options"]');
        var error = picker && picker.querySelector('[data-role="mapping-root-picker-error"]');
        var options = panel
            ? Array.prototype.slice.call(panel.querySelectorAll('[data-role="mapping-root-picker-option"]'))
            : [];

        if (!picker || !trigger || !value || !panel || options.length === 0) {
            return null;
        }

        select.required = false;
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        trigger.hidden = false;
        picker.classList.add('is-enhanced');

        function optionValue(option) {
            return String(option.getAttribute('data-value') || '');
        }

        function isDisabled(option) {
            return option.getAttribute('aria-disabled') === 'true';
        }

        function close(focusTrigger) {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (focusTrigger) {
                trigger.focus();
            }
        }

        function open(focusDirection) {
            var selectedIndex;
            var focusIndex;

            if (trigger.disabled) {
                return;
            }
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            if (!focusDirection) {
                return;
            }
            selectedIndex = options.findIndex(function (option) {
                return option.getAttribute('aria-selected') === 'true';
            });
            focusIndex = focusDirection === 'last'
                ? options.length - 1
                : Math.min(Math.max(selectedIndex + 1, 0), options.length - 1);
            options[focusIndex].focus();
        }

        function sync() {
            var selectedValue = String(select.value || '');
            var selectedOption = options.filter(function (option) {
                return optionValue(option) === selectedValue;
            })[0] || options[0];

            options.forEach(function (option) {
                option.setAttribute('aria-selected', option === selectedOption ? 'true' : 'false');
            });
            value.textContent = selectedOption
                ? String(selectedOption.querySelector('span').textContent || '')
                : '';
            trigger.classList.toggle('has-value', selectedValue !== '');
            trigger.removeAttribute('aria-invalid');
            if (error) {
                error.hidden = true;
            }
        }

        function choose(option) {
            if (isDisabled(option)) {
                return false;
            }

            select.value = optionValue(option);
            select.dispatchEvent(new Event('change', {bubbles: true}));
            sync();
            close(true);

            return true;
        }

        function focusSibling(option, offset) {
            var index = options.indexOf(option);
            var nextIndex = Math.min(Math.max(index + offset, 0), options.length - 1);

            options[nextIndex].focus();
        }

        scope.listen(trigger, 'click', function () {
            if (panel.hidden) {
                open();
            } else {
                close(false);
            }
        });
        scope.listen(trigger, 'keydown', function (event) {
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
                return;
            }

            event.preventDefault();
            open(event.key === 'ArrowUp' ? 'last' : 'next');
        });
        options.forEach(function (option) {
            scope.listen(option, 'click', function () {
                choose(option);
            });
            scope.listen(option, 'keydown', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    focusSibling(option, event.key === 'ArrowDown' ? 1 : -1);
                } else if (event.key === 'Home' || event.key === 'End') {
                    event.preventDefault();
                    options[event.key === 'Home' ? 0 : options.length - 1].focus();
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    choose(option);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    close(true);
                } else if (event.key === 'Tab') {
                    close(false);
                }
            });
        });
        scope.listen(select, 'change', sync);
        scope.listen(document, 'click', function (event) {
            if (!picker.contains(event.target)) {
                close(false);
            }
        });
        sync();

        return {
            close: close,
            focus: function () {
                trigger.focus();
            },
            setDisabled: function (disabled) {
                trigger.disabled = !!disabled;
                close(false);
            },
            sync: sync,
            validate: function () {
                var valid = select.disabled || String(select.value || '') !== '';

                trigger.setAttribute('aria-invalid', valid ? 'false' : 'true');
                if (error) {
                    error.hidden = valid;
                }

                return valid;
            }
        };
    }

    function refreshTreeOptions(config, root, dialog, select, button) {
        if (!config.urls || !config.urls.tree_options_refresh || (button && button.disabled)) {
            return;
        }

        setBusy(button, true);
        $.ajax({
            url: config.urls.tree_options_refresh,
            type: 'POST',
            dataType: 'json',
            timeout: 60000,
            data: {
                form_key: config.form_key || window.FORM_KEY || ''
            }
        }).done(function (response) {
            var treeCount;

            if (!response || response.success !== true) {
                showStatus(
                    dialog,
                    'error',
                    response && response.message
                        ? response.message
                        : $t('Nie udało się pobrać drzew z Ergonode.')
                );
                return;
            }

            replaceTreeOptions(root, select, response.options || []);
            treeCount = (response.options || []).filter(function (option) {
                return String(option && option.value || '') !== '';
            }).length;
            showStatus(
                dialog,
                'success',
                response.message || $t('Pobrano drzewa Ergonode: ') + treeCount + '.'
            );
        }).fail(function () {
            showStatus(dialog, 'error', $t('Nie udało się pobrać drzew z Ergonode.'));
        }).always(function () {
            setBusy(button, false);
        });
    }

    function init(config, root, scope) {
        var dialog = root.querySelector('[data-role="new-mapping-modal"]');
        var treeSelect;
        var rootSelect;
        var form;
        var cancelButton;
        var refreshButton;
        var deleteButton;
        var deleteForm;
        var deleteFormId;
        var confirmDeleteButton;
        var categoryTreeId;
        var sortOrder;
        var lockedTreeCode;
        var lockedRootCategoryId;
        var rootSelectPicker;
        var widget;
        var wasInactive = false;

        function updateReactivationNotice() {
            var notice = dialog.querySelector('[data-role="mapping-reactivation-notice"]');

            if (notice) {
                notice.hidden = !wasInactive || !form.querySelector('[name="is_active"]').checked;
            }
        }

        if (!dialog || dialog.getAttribute('data-initialized') === 'true') {
            return null;
        }

        treeSelect = dialog.querySelector('[data-role="new-mapping-tree-code"]');
        rootSelect = dialog.querySelector('[data-role="new-mapping-root-category-id"]');
        form = dialog.querySelector('[data-role="new-mapping-form"]');
        cancelButton = dialog.querySelector('[data-role="cancel-new-mapping"]');
        refreshButton = dialog.querySelector('[data-role="refresh-tree-options"]');
        deleteButton = dialog.querySelector('[data-role="delete-mapping"]');
        deleteForm = dialog.querySelector('[data-role="delete-category-tree-form"]');
        deleteFormId = deleteForm && deleteForm.querySelector('[data-role="delete-category-tree-id"]');
        confirmDeleteButton = deleteForm && deleteForm.querySelector('[data-role="confirm-delete-mapping"]');
        categoryTreeId = form && form.querySelector('[data-role="mapping-category-tree-id"]');
        sortOrder = form && form.querySelector('[data-role="mapping-sort-order"]');
        lockedTreeCode = form && form.querySelector('[data-role="locked-tree-code"]');
        lockedRootCategoryId = form && form.querySelector('[data-role="locked-root-category-id"]');
        if (!treeSelect || !rootSelect || !form || !cancelButton || !categoryTreeId) {
            return null;
        }
        rootSelectPicker = initRootPicker(rootSelect, scope);

        function resetStatus() {
            dialog.querySelectorAll('[data-role="tree-options-status"]').forEach(function (element) {
                element.hidden = true;
                element.classList.remove('is-success', 'is-error');
                element.textContent = '';
            });
        }

        function setMode(mapping) {
            var editing = !!(mapping && Number(mapping.categoryTreeId || 0) > 0);
            var intro = dialog.querySelector('[data-role="mapping-intro"]');
            var treeActions = dialog.querySelector('[data-role="mapping-tree-actions"]');
            var rootPicker = rootSelect.closest('[data-role="mapping-root-picker"]');

            form.reset();
            resetStatus();
            form.dataset.mode = editing ? 'edit' : 'create';
            wasInactive = editing && !mapping.isActive;
            widget.modal('setTitle', editing ? $t('Edit Mapping') : $t('New Mapping'));

            if (intro) {
                intro.textContent = editing
                    ? $t('Manage synchronization for this connection. The tree and Magento root are fixed.')
                    : $t('Connect an Ergonode tree to a Magento root. This connection cannot be changed after saving.');
            }
            if (treeActions) {
                treeActions.hidden = editing;
            }
            categoryTreeId.value = editing ? String(mapping.categoryTreeId) : '0';
            treeSelect.value = editing ? String(mapping.treeCode || '') : '';
            rootSelect.value = editing ? String(mapping.rootCategoryId || '') : '';
            treeSelect.disabled = editing;
            treeSelect.hidden = editing;
            rootSelect.disabled = editing;

            if (rootPicker) {
                rootPicker.hidden = editing;
            }
            [['mapping-tree-summary', treeSelect], ['mapping-root-summary', rootSelect]]
                .forEach(function (entry) {
                    var summary = dialog.querySelector('[data-role="' + entry[0] + '"]');
                    var option = entry[1].selectedOptions[0];

                    if (summary) {
                        summary.hidden = !editing;
                        summary.textContent = option ? option.textContent : '';
                    }
                });
            if (rootSelectPicker) {
                rootSelectPicker.sync();
                rootSelectPicker.setDisabled(editing);
            }

            if (sortOrder) {
                sortOrder.value = editing ? String(mapping.sortOrder || 0) : '0';
                sortOrder.disabled = !editing;
            }
            if (lockedTreeCode) {
                lockedTreeCode.value = editing ? String(mapping.treeCode || '') : '';
                lockedTreeCode.disabled = !editing;
            }
            if (lockedRootCategoryId) {
                lockedRootCategoryId.value = editing ? String(mapping.rootCategoryId || '') : '';
                lockedRootCategoryId.disabled = !editing;
            }
            form.querySelector('[name="is_active"]').checked = editing ? !!mapping.isActive : true;
            updateReactivationNotice();
            if (deleteButton) {
                deleteButton.hidden = !editing;
            }
            if (refreshButton) {
                refreshButton.hidden = editing;
            }
            if (deleteFormId) {
                deleteFormId.value = editing ? String(mapping.categoryTreeId) : '0';
            }
        }

        function mappingFromCard(card) {
            return {
                categoryTreeId: Number(card.getAttribute('data-category-tree-id') || 0),
                treeCode: card.getAttribute('data-tree-code') || '',
                rootCategoryId: Number(card.getAttribute('data-root-category-id') || 0),
                sortOrder: Number(card.getAttribute('data-sort-order') || 0),
                isActive: card.getAttribute('data-is-active') === '1',
                removeMissing: card.getAttribute('data-remove-missing') === '1'
            };
        }

        function open(mapping) {
            var focusTarget;

            setMode(mapping || null);
            widget.modal('openModal');
            focusTarget = mapping ? form.querySelector('[name="is_active"]') : treeSelect;
            window.setTimeout(function () {
                focusTarget.focus();
            }, 0);
        }

        widget = $(dialog);
        modal({
            type: 'popup',
            // Keep Magento's markup and focus handling with a valid dialog host.
            popupTpl: popupTemplate.replace(/<(\/?)aside\b/g, '<$1div'),
            responsive: true,
            innerScroll: true,
            clickableOverlay: false,
            modalClass: 'vec-new-mapping-modal-shell',
            title: $t('New Mapping'),
            buttons: [],
            closed: function () {
                setMode(null);
            }
        }, widget);

        root.querySelectorAll('[data-role="open-new-mapping"]').forEach(function (button) {
            scope.listen(button, 'click', function () {
                open(null);
            });
        });
        root.querySelectorAll('[data-role="open-edit-mapping"]').forEach(function (button) {
            scope.listen(button, 'click', function () {
                open(mappingFromCard(button.closest('[data-role="category-tree-configuration"]')));
            });
        });
        scope.listen(cancelButton, 'click', function () {
            widget.modal('closeModal');
        });
        scope.listen(form.querySelector('[name="is_active"]'), 'change', updateReactivationNotice);
        if (deleteButton && confirmDeleteButton) {
            scope.listen(deleteButton, 'click', function () {
                confirmDeleteButton.click();
            });
        }
        if (refreshButton) {
            scope.listen(refreshButton, 'click', function () {
                refreshTreeOptions(config, root, dialog, treeSelect, refreshButton);
            });
        }
        scope.listen(form, 'submit', function (event) {
            var saveButton = form.querySelector('[data-role="save-new-mapping"]');

            if (rootSelectPicker && !rootSelectPicker.validate()) {
                event.preventDefault();
                rootSelectPicker.focus();
                return;
            }
            if (form.checkValidity()) {
                setBusy(saveButton, true);
            }
        });
        dialog.setAttribute('data-initialized', 'true');
        if (scope && typeof scope.cleanup === 'function') {
            scope.cleanup(function () {
                dialog.removeAttribute('data-initialized');
            });
        }
        setMode(null);

        return {
            open: open,
            refresh: function () {
                refreshTreeOptions(config, root, dialog, treeSelect, refreshButton);
            }
        };
    }

    return {
        init: init,
        initRootPicker: initRootPicker,
        optionLabel: optionLabel,
        replaceTreeOptions: replaceTreeOptions
    };
});
