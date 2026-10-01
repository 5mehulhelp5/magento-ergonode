define([
    'jquery',
    'mage/translate',
    'Ergonode_CategoryConsumerHistoryAdminUi/js/category-tree-operations'
], function ($, $t, operationsView) {
    'use strict';

    return function (config, element) {
        var workspace = element.closest('#ergonode-category-tree-mapping');
        var operationList = element.querySelector('[data-role="operation-list"]');
        var message = element.querySelector('[data-role="history-message"]');
        var categoryTreeId = 0;
        var operations = [];
        var pagination = {};
        var pending = null;
        var generation = 0;
        var loaded = false;

        if (!workspace) {
            return;
        }
        $(workspace).on('ergonode:category-mapping:ready.historyPanel', function (event, api) {
            selectTree(api.getCategoryTreeId(), true);
        });
        $(workspace).on([
            'ergonode:category-mapping:saved.historyPanel',
            'ergonode:category-mapping:refreshed.historyPanel',
            'ergonode:category-mapping:snapshot-removed.historyPanel'
        ].join(' '), function () {
            selectTree(categoryTreeId, true);
        });
        if (workspace.veaCategoryMappingApi) {
            selectTree(workspace.veaCategoryMappingApi.getCategoryTreeId());
        }

        function selectTree(treeId, refresh) {
            if (!treeId || (Number(treeId) === categoryTreeId && !refresh)) {
                return;
            }
            categoryTreeId = Number(treeId);
            generation += 1;
            if (pending) {
                pending.abort();
                pending = null;
            }
            operations = [];
            pagination = {};
            loaded = false;
            loadPage();
        }

        function historyUrl(operationId) {
            var url = new URL(config.urls.history, window.location.href);

            url.searchParams.set('category_tree_id', String(categoryTreeId));
            url.searchParams.set('operation_id', String(operationId));

            return url.href;
        }

        function render() {
            element.setAttribute('aria-busy', pending ? 'true' : 'false');
            operationsView.render(operationList, {
                operations: operations,
                pagination: pagination,
                selectedOperationId: null,
                loading: Boolean(pending),
                loaded: loaded,
                operationUrl: historyUrl,
                onLoadOlder: function () {
                    loadPage(pagination.next_before_id);
                }
            });
        }

        function loadPage(beforeOperationId) {
            var data = {category_tree_id: categoryTreeId};
            var requestGeneration = generation;

            if (pending) {
                return;
            }
            if (beforeOperationId) {
                data.before_operation_id = beforeOperationId;
            }
            message.replaceChildren();
            message.hidden = loaded;
            message.textContent = loaded ? '' : $t('Loading...');
            pending = $.ajax({url: config.urls.operations, data: data, dataType: 'json', method: 'GET'});
            render();
            pending.done(function (response) {
                if (requestGeneration !== generation) {
                    return;
                }
                if (!response || !response.success || !response.page) {
                    showError(beforeOperationId);
                    return;
                }
                response.page.items.forEach(function (operation) {
                    if (!operations.some(function (existing) {
                        return Number(existing.operation_id) === Number(operation.operation_id);
                    })) {
                        operations.push(operation);
                    }
                });
                pagination = response.page;
                loaded = true;
                message.hidden = true;
                message.replaceChildren();
            }).fail(function (xhr, status) {
                if (requestGeneration === generation && status !== 'abort') {
                    showError(beforeOperationId);
                }
            }).always(function () {
                if (requestGeneration === generation) {
                    pending = null;
                    render();
                }
            });
        }

        function showError(beforeOperationId) {
            var retry = document.createElement('button');

            retry.type = 'button';
            retry.className = 'vech-load-older';
            retry.textContent = $t('Retry');
            retry.addEventListener('click', function () {
                loadPage(beforeOperationId);
            });
            message.textContent = $t('History could not be loaded.') + ' ';
            message.append(retry);
            message.hidden = false;
        }
    };
});
