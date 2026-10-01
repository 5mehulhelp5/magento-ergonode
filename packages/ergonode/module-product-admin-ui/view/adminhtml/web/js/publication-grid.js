define([
    'require',
    'jquery',
    'mage/translate',
    'mage/template',
    'text!Ergonode_ProductAdminUi/template/publication-grid.html',
    'Ergonode_ProductAdminUi/js/publication-progress',
    'Ergonode_ProductAdminUi/js/publication-process',
    'Ergonode_ProductAdminUi/js/product-row-actions'
], function (localRequire, $, $t, renderTemplate, template, createProgress, createProcess, createRowActions) {
    'use strict';

    return function (config, element) {
        element.insertAdjacentHTML('beforeend', renderTemplate(template, {$t: $t}));
        node('actions-label').textContent = $t('Actions');
        node('actions-placeholder').textContent = $t('Actions');
        var page = 1;
        var pageSize = 20;
        var search = '';
        var filters = {};
        var sort = 'product_id';
        var direction = 'ASC';
        var total = 0;
        var rows = [];
        var selected = new Set();
        var excluded = new Set();
        var all = false;
        var busy = false;
        var actions = [];
        var processes = {};
        var preflights = {};
        async function prepare(action) {
            if (!action.preflight) { return true; }
            if (!preflights[action.code]) {
                preflights[action.code] = new Promise(function (resolve, reject) {
                    localRequire([action.preflight.component], function (createPreflight) {
                        resolve(createPreflight(action.preflight));
                    }, reject);
                });
            }
            return (await preflights[action.code]).ensure();
        }
        function getProcess(action) {
            if (!processes[action.code]) {
                var progress = createProgress(action.progress);
                processes[action.code] = createProcess(progress, async function (ids) {
                    if (!await prepare(action)) {
                        throw new Error($t('Login cancelled. The next batch was not sent.'));
                    }
                    return request(action.url, {ids: JSON.stringify(ids)}, 'POST');
                }, function () { busy = false; load(); });
            }
            return processes[action.code];
        }
        function available(action, item) {
            return action.ready && (!item || !action.requires_mapping || Boolean(item.ergonode_sku));
        }
        function renderActions() {
            var select = node('publish-selection');
            select.replaceChildren(node('actions-placeholder'));
            var notice = node('readiness');
            notice.replaceChildren();
            actions.forEach(function (action) {
                var option = document.createElement('option');
                option.value = action.code; option.textContent = $t(action.label); option.disabled = !action.ready;
                select.appendChild(option);
                if (!action.ready && action.message) {
                    var line = document.createElement('p');
                    line.textContent = $t(action.label) + ': ' + action.message;
                    if (action.configuration_url) {
                        var link = document.createElement('a');
                        link.href = action.configuration_url; link.target = '_blank'; link.rel = 'noopener';
                        link.textContent = ' ' + $t('Open configuration'); line.appendChild(link);
                    }
                    notice.appendChild(line);
                }
            });
            notice.hidden = !notice.children.length;
        }

        function node(role) { return element.querySelector('[data-role="' + role + '"]'); }
        function pages() { return Math.max(1, Math.ceil(total / pageSize)); }
        function criteria() { return Object.assign({}, filters, {sort: sort, direction: direction}); }
        function clearSelection() { all = false; selected.clear(); excluded.clear(); }
        function selectionMenu(open, focus) {
            node('selection-options').hidden = !open;
            node('selection-toggle').setAttribute('aria-expanded', String(open));
            if (focus) { node(open ? 'select-page' : 'selection-toggle').focus(); }
        }
        function chosen(id) { return all ? !excluded.has(id) : selected.has(id); }
        function count() { return all ? Math.max(0, total - excluded.size) : selected.size; }
        function closeRowMenus() {
            element.querySelectorAll('.vepp-action-menu:popover-open').forEach(function (menu) { menu.hidePopover(); });
        }
        element.addEventListener('scroll', closeRowMenus, true);
        window.addEventListener('resize', closeRowMenus);
        function loading(text) { node('loading-text').textContent = text; node('loading').hidden = !text; }
        function message(text) { node('message').textContent = text; node('message').hidden = !text; }
        function request(url, data, method) {
            return new Promise(function (resolve, reject) {
                $.ajax({url: url, type: method || 'GET', dataType: 'json', data: Object.assign({form_key: window.FORM_KEY}, data)})
                    .done(resolve).fail(function () { reject(new Error($t('Unable to receive a response. Check your connection and admin session.'))); });
            });
        }
        function controls() {
            element.querySelectorAll('button, input, select').forEach(function (control) { control.disabled = busy; });
            element.querySelectorAll('.vepp-action-split summary').forEach(function (toggle) {
                toggle.setAttribute('aria-disabled', String(busy));
            });
            node('publish-selection').disabled = busy || !actions.some(function (action) { return action.ready; }) || count() === 0;
            element.querySelectorAll('[data-product-action]').forEach(function (button) {
                var action = actions.find(function (entry) { return entry.code === button.dataset.productAction; });
                var item = rows.find(function (entry) { return entry.product_id === Number(button.dataset.productId); });
                button.disabled = busy || !action || !available(action, item);
            });
            node('first').disabled = node('previous').disabled = busy || page <= 1;
            node('last').disabled = busy || page >= pages();
            node('page-checkbox').disabled = node('selection-toggle').disabled = busy || !rows.length;
            node('next').disabled = busy || page * pageSize >= total;
            node('selection-count').textContent = $t('Selected: %1').replace('%1', String(count())) + (all ? ' ' + $t('(all results)') : '');
            var checked = rows.filter(function (item) { return chosen(item.product_id); }).length;
            node('page-checkbox').checked = rows.length > 0 && checked === rows.length;
            node('page-checkbox').indeterminate = checked > 0 && checked < rows.length;
            element.querySelectorAll('[data-product-checkbox]').forEach(function (checkbox) {
                checkbox.checked = chosen(Number(checkbox.dataset.productCheckbox));
                checkbox.closest('tr').classList.toggle('is-selected', checkbox.checked);
            });
        }
        function toggle(id, checked) {
            if (all) { if (checked) { excluded.delete(id); } else { excluded.add(id); } }
            else if (checked) { selected.add(id); } else { selected.delete(id); }
        }
        function cell(row, text) {
            var td = document.createElement('td');
            td.textContent = text;
            row.appendChild(td);
            return td;
        }
        function renderFilterOptions(options) {
            element.querySelectorAll('select[data-filter]').forEach(function (select) {
                var value = select.value;
                select.replaceChildren(select.options[0]);
                options[select.dataset.filter].forEach(function (item) {
                    var option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = item.label;
                    select.appendChild(option);
                });
                select.value = value;
            });
        }
        function render() {
            node('rows').replaceChildren();
            rows.forEach(function (item) {
                var tr = document.createElement('tr');
                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.dataset.productCheckbox = item.product_id;
                checkbox.setAttribute('aria-label', $t('Select %1').replace('%1', item.sku));
                checkbox.addEventListener('change', function () { toggle(item.product_id, checkbox.checked); controls(); });
                cell(tr, '').appendChild(checkbox);
                cell(tr, item.product_id).className = 'vepp-id-cell';
                var image = document.createElement('img');
                image.src = item.thumbnail; image.alt = ''; image.loading = 'lazy'; image.width = 48; image.height = 48;
                cell(tr, '').appendChild(image);
                ['name', 'sku', 'ergonode_sku', 'attribute_set_name', 'type_id'].forEach(function (key) {
                    cell(tr, item[key] === '' ? '—' : item[key]);
                });
                var rowActions = cell(tr, '');
                rowActions.className = 'vepp-row-actions';
                rowActions.appendChild(createRowActions(actions, item, function (action) { start(action, [item.product_id]); }));
                node('rows').appendChild(tr);
            });
            if (!rows.length) {
                var empty = document.createElement('tr');
                cell(empty, $t('No products match your filters.')).colSpan = 9;
                node('rows').appendChild(empty);
            }
            node('page-info').textContent = total ? $t('%1–%2 of %3 products').replace('%1', String((page - 1) * pageSize + 1)).replace('%2', String(Math.min(page * pageSize, total))).replace('%3', String(total)) : $t('0 products');
            node('page-number').value = page;
            node('page-number').max = pages();
            node('page-total').textContent = $t('of %1').replace('%1', String(pages()));
            var activeFilters = Object.keys(filters).length + (search ? 1 : 0);
            node('filter-info').textContent = activeFilters ? $t('Active filters: %1').replace('%1', String(activeFilters)) : $t('All products');
            element.querySelectorAll('[data-sort]').forEach(function (button) {
                var active = button.dataset.sort === sort;
                button.parentElement.setAttribute('aria-sort', active ? (direction === 'ASC' ? 'ascending' : 'descending') : 'none');
                button.querySelector('[data-sort-indicator]').textContent = active ? (direction === 'ASC' ? '↑' : '↓') : '';
            });
            controls();
        }
        async function load() {
            if (busy) { return; }
            busy = true; selectionMenu(false); controls(); message(''); loading($t('Loading products…'));
            node('rows').setAttribute('aria-busy', 'true');
            try {
                var response = await request(config.dataUrl, {page: page, page_size: pageSize, search: search, criteria: criteria()});
                if (!response.success) { throw new Error(response.message); }
                renderFilterOptions(response.data.filter_options);
                rows = response.data.items; total = response.data.total; page = response.data.page;
                actions = response.data.actions;
                renderActions();
                message(''); render();
            } catch (error) { actions = []; renderActions(); message(error.message); }
            finally { busy = false; loading(''); node('rows').setAttribute('aria-busy', 'false'); controls(); }
        }
        async function start(action, ids) {
            if (busy || !available(action)) { return; }
            closeRowMenus();
            busy = true; controls(); message(''); loading($t('Preparing the product list…'));
            try {
                loading('');
                if (!await prepare(action)) {
                    busy = false; controls();
                    return;
                }
                loading($t('Preparing the product list…'));
                if (!ids) {
                    var response = await request(config.snapshotUrl, {selection: JSON.stringify({
                        all: all, selected: Array.from(selected), excluded: Array.from(excluded), search: search, criteria: criteria()
                    })}, 'POST');
                    if (!response.success) { throw new Error(response.message); }
                    ids = response.ids;
                }
                loading('');
                if (!ids.length) { busy = false; controls(); return; }
                await getProcess(action).start(ids, function (id) {
                    var item = rows.find(function (row) { return row.product_id === id; });
                    return {product_id: id, code: item ? item.sku : String(id), label: item ? item.name : $t('Product ID %1').replace('%1', String(id))};
                });
            } catch (error) { loading(''); message(error.message); busy = false; controls(); }
        }
        node('search-form').addEventListener('submit', function (event) {
            event.preventDefault(); if (busy) { return; }
            search = node('search').value.trim(); filters = {};
            element.querySelectorAll('[data-filter]').forEach(function (input) {
                if (input.value.trim()) { filters[input.dataset.filter] = input.value.trim(); }
            });
            page = 1; clearSelection(); load();
        });
        node('clear-filters').addEventListener('click', function () {
            search = ''; filters = {}; node('search').value = '';
            element.querySelectorAll('[data-filter]').forEach(function (input) { input.value = ''; });
            page = 1; clearSelection(); load();
        });
        element.querySelectorAll('[data-sort]').forEach(function (button) {
            button.addEventListener('click', function () {
                direction = sort === button.dataset.sort && direction === 'ASC' ? 'DESC' : 'ASC';
                sort = button.dataset.sort; page = 1; load();
            });
        });
        node('selection-toggle').addEventListener('click', function () { selectionMenu(node('selection-options').hidden); });
        node('selection-toggle').addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') { event.preventDefault(); selectionMenu(true, true); }
        });
        element.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !node('selection-options').hidden) { selectionMenu(false, true); }
        });
        element.addEventListener('focusout', function (event) {
            if (!element.querySelector('.vepp-multicheck').contains(event.relatedTarget)) { selectionMenu(false); }
        });
        element.addEventListener('click', function (event) {
            if (!event.target.closest('.vepp-multicheck')) { selectionMenu(false); }
        });
        node('page-form').addEventListener('submit', function (event) {
            event.preventDefault(); if (busy) { return; }
            page = Math.max(1, Math.min(pages(), Number(node('page-number').value) || 1)); load();
        });
        node('first').addEventListener('click', function () { page = 1; load(); });
        node('last').addEventListener('click', function () { page = pages(); load(); });
        node('page-size').addEventListener('change', function () { pageSize = Number(this.value); page = 1; load(); });
        node('previous').addEventListener('click', function () { page--; load(); });
        node('next').addEventListener('click', function () { page++; load(); });
        node('select-page').addEventListener('click', function () { rows.forEach(function (item) { toggle(item.product_id, true); }); selectionMenu(false, true); controls(); });
        node('page-checkbox').addEventListener('change', function () {
            var checked = this.checked; rows.forEach(function (item) { toggle(item.product_id, checked); }); controls();
        });
        node('select-all').addEventListener('click', function () { all = true; selected.clear(); excluded.clear(); selectionMenu(false, true); controls(); });
        node('clear-selection').addEventListener('click', function () { clearSelection(); selectionMenu(false, true); controls(); });
        node('publish-selection').addEventListener('change', function (event) {
            var action = event.target.value;
            event.target.value = '';
            var selectedAction = actions.find(function (entry) { return entry.code === action; });
            if (selectedAction && count() > 0) { start(selectedAction); }
        });
        window.addEventListener('beforeunload', function (event) {
            if (Object.values(processes).some(function (process) { return process.isRunning(); })) { event.preventDefault(); event.returnValue = ''; }
        });
        load();
    };
});
