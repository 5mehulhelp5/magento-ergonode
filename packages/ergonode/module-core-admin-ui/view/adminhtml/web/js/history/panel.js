define(['mage/translate', 'Ergonode_CoreAdminUi/js/history/operations'], function ($t, operationsView) {
    'use strict';

    return function (config, element) {
        var list = element.querySelector('[data-role="history-operations"]');
        var message = element.querySelector('[data-role="history-message"]');
        var operations = [];
        var page = {total: 0, has_more: false};
        var loading = false;
        var loaded = false;
        var disposed = false;

        function render() {
            list.innerHTML = '<div class="veah-current is-selected" aria-current="true"></div>' +
                operations.map(function (operation) { return operationsView.card(operation, 0, config.urls.history); }).join('');
            list.firstElementChild.textContent = $t('Current state');
            if (!operations.length) {
                var empty = document.createElement('p');
                empty.className = 'veah-empty';
                empty.textContent = loading ? $t('Loading...') : loaded ?
                    $t('New operations will appear here after mappings are saved or synchronized.') : '';
                list.appendChild(empty);
            }
            if (!loaded) { return; }
            var pagination = document.createElement('div');
            pagination.className = 'veah-pagination';
            var count = document.createElement('span');
            count.textContent = $t('%1 of %2 operations').replace('%1', operations.length).replace('%2', page.total);
            pagination.appendChild(count);
            if (page.has_more) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'veah-button';
                button.dataset.action = 'older';
                button.disabled = loading;
                button.textContent = loading ? $t('Loading...') : $t('Load 10 older');
                pagination.appendChild(button);
            }
            list.appendChild(pagination);
        }

        function load() {
            if (loading || disposed) { return; }
            loading = true;
            render();
            var url = new URL(config.urls.operations, window.location.href);
            if (operations.length) { url.searchParams.set('before_id', operations[operations.length - 1].operation_id); }
            fetch(url.href, {
                credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (response) {
                if (!response.ok) { throw new Error('History request failed'); }
                return response.json();
            }).then(function (response) {
                if (disposed) { return; }
                if (!response.page || !Array.isArray(response.page.items)) { throw new Error('Invalid history response'); }
                page = response.page;
                var ids = new Set(operations.map(function (operation) { return Number(operation.operation_id); }));
                operations = operations.concat(page.items.filter(function (operation) { return !ids.has(Number(operation.operation_id)); }));
                loaded = true;
                message.hidden = true;
            }).catch(function () {
                if (disposed) { return; }
                message.textContent = $t('History could not be loaded. Try again.');
                var retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'veah-button';
                retry.dataset.action = 'retry';
                retry.textContent = $t('Try again');
                message.appendChild(retry);
                message.hidden = false;
            }).finally(function () {
                loading = false;
                if (!disposed) { render(); }
            });
        }

        function click(event) {
            if (event.target.closest('[data-action="older"], [data-action="retry"]')) { load(); }
        }

        element.addEventListener('click', click);
        load();
        return {destroy: function () { disposed = true; element.removeEventListener('click', click); }};
    };
});
