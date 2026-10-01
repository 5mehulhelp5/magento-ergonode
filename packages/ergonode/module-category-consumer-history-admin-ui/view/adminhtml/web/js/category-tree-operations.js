define(['mage/translate'], function ($t) {
    'use strict';

    function render(operationList, options) {
        var operations = options.operations || [];
        var operationsPagination = options.pagination || {};
        var selectedOperation = options.selectedOperation;
        var display = operations.slice();

        if (selectedOperation && !display.some(function (operation) {
            return Number(operation.operation_id) === Number(selectedOperation.operation_id);
        })) {
            display.unshift(selectedOperation);
        }
        display = display.filter(function (operation) {
            return !options.changesOnly || Number(operation.change_count) > 0;
        });

        operationList.replaceChildren();
        operationList.append(createCurrentOperation());
        display.forEach(function (operation) {
            operationList.append(createOperation(operation));
        });
        if (!display.length && !options.loading && options.loaded !== false) {
            operationList.append(createEmptyHistory());
        }
        if (options.loaded !== false) {
            operationList.append(createOperationsPagination());
        }

        function createCurrentOperation() {
            var button = createAction(options.currentUrl);
            var title = document.createElement('strong');
            var description = document.createElement('span');
            var selected = !options.selectedOperationId;

            button.className = 'vech-operation vech-operation-current' + (selected ? ' is-selected' : '');
            button.dataset.operationId = '';
            if (selected) {
                button.setAttribute('aria-current', 'true');
            }
            title.textContent = $t('Current state');
            description.textContent = $t('Without historical highlights');
            button.append(title, description);
            return button;
        }

        function createOperation(operation) {
            var row = document.createElement('div');
            var button = createAction(options.operationUrl && options.operationUrl(operation.operation_id));
            var top = document.createElement('span');
            var title = document.createElement('strong');
            var status = document.createElement('span');
            var metadata = document.createElement('span');
            var date = document.createElement('span');
            var origin = document.createElement('span');
            var counts = document.createElement('span');
            var categoryCount = Number((operation.summary || {}).categories || 0);
            var metadataParts = [
                formatDate(operation.finished_at || operation.started_at),
                originLabel(operation.origin)
            ];
            var selected = Number(options.selectedOperationId) === Number(operation.operation_id);

            button.className = 'vech-operation' + (selected ? ' is-selected' : '');
            button.dataset.operationId = String(operation.operation_id);
            if (selected) {
                button.setAttribute('aria-current', 'true');
            }
            top.className = 'vech-operation-top';
            title.textContent = operationLabel(operation.operation_code);
            status.className = 'vech-status vech-status-' + safeToken(operation.status);
            status.textContent = $t(operation.status || 'success');
            top.append(title, status);
            metadata.className = 'vech-operation-meta';
            date.textContent = metadataParts[0];
            origin.className = 'vech-operation-origin';
            origin.textContent = metadataParts[1];
            metadata.append(date, origin);
            metadata.title = metadataParts.join(' · ');
            if (operation.actor_name) {
                metadata.title += ' · ' + $t('Actor') + ': ' + operation.actor_name;
            }
            if ((operation.operation_summary || {}).trees > 1) {
                metadata.title += ' · ' + $t('%1 trees in this run')
                    .replace('%1', String(operation.operation_summary.trees));
            }
            counts.className = 'vech-operation-counts';
            counts.textContent = $t('%1 changes in %2 categories')
                .replace('%1', String(operation.change_count || 0))
                .replace('%2', String(categoryCount));
            button.append(top, metadata, counts);
            button.addEventListener('click', function () {
                if (options.onSelect) {
                    options.onSelect(Number(operation.operation_id));
                }
            });

            row.className = 'vech-operation-row' + (selected ? ' is-selected' : '');
            row.append(button);
            if (options.onDetails) {
                var details = createAction();

                details.className = 'vech-operation-details';
                details.dataset.detailsOperationId = String(operation.operation_id);
                details.setAttribute('aria-haspopup', 'dialog');
                details.setAttribute('aria-label', $t('Change list: %1')
                    .replace('%1', title.textContent + ' · ' + metadataParts[0]));
                details.textContent = $t('Change list');
                details.addEventListener('click', function () {
                    options.onDetails(Number(operation.operation_id));
                });
                row.append(details);
            }

            return row;
        }

        function createOperationsPagination() {
            var pagination = document.createElement('div');
            var summary = document.createElement('span');
            var button = document.createElement('button');
            var total = Math.max(operations.length, Number(operationsPagination.total || 0));
            var remaining = Math.max(0, total - operations.length);
            var pageSize = Math.max(1, Number(operationsPagination.page_size || 10));
            var nextCount = Math.min(pageSize, remaining);

            pagination.className = 'vech-operation-pagination';
            summary.className = 'vech-operation-pagination-summary';
            summary.textContent = $t('%1 of %2 operations · %3 remaining')
                .replace('%1', String(operations.length))
                .replace('%2', String(total))
                .replace('%3', String(remaining));
            pagination.append(summary);
            if (operationsPagination.has_more && operationsPagination.next_before_id && remaining > 0) {
                button.type = 'button';
                button.className = 'vech-load-older';
                button.disabled = Boolean(options.loading);
                button.textContent = options.loading
                    ? $t('Loading...')
                    : $t('Load %1 older').replace('%1', String(nextCount));
                button.addEventListener('click', options.onLoadOlder);
                pagination.append(button);
            }

            return pagination;
        }

        function createEmptyHistory() {
            var empty = document.createElement('div');

            empty.className = 'vech-history-empty';
            if (options.changesOnly && (operations.length || selectedOperation)) {
                empty.textContent = $t('No changes to show.');
                return empty;
            }
            empty.innerHTML = '<strong></strong><span></span>';
            empty.firstElementChild.textContent = $t('No recorded operations');
            empty.lastElementChild.textContent = $t('History starts after this module is installed.');

            return empty;
        }

    }

    function createAction(url) {
        var action = document.createElement(url ? 'a' : 'button');

        if (url) {
            action.href = url;
        } else {
            action.type = 'button';
        }

        return action;
    }

    function operationLabel(code) {
        var labels = {
            save: 'Admin save',
            refresh_snapshot: 'Snapshot refresh',
            remove_snapshot: 'Removed from list',
            synchronize: 'Category synchronization',
            synchronize_data: 'Category data synchronization',
            synchronize_reset: 'Full category synchronization'
        };

        return $t(labels[code] || code);
    }

    function originLabel(origin) {
        var labels = {admin: 'Admin', cli: 'CLI', cron: 'Cron'};

        return $t(labels[origin] || origin);
    }

    function formatDate(value) {
        var normalized = value ? String(value).replace(' ', 'T') + 'Z' : '';
        var date = normalized ? new Date(normalized) : null;

        return date && !Number.isNaN(date.getTime()) ? date.toLocaleString() : (value || '—');
    }

    function safeToken(value) {
        return String(value || 'unknown').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();
    }

    return {
        render: render,
        operationLabel: operationLabel,
        originLabel: originLabel,
        formatDate: formatDate,
        safeToken: safeToken
    };
});
