define(['mage/translate'], function ($t) {
    'use strict';

    function node(tag, className, text) {
        var element = document.createElement(tag);
        element.className = className || '';
        if (text !== undefined) { element.textContent = text; }
        return element;
    }

    function renderConflicts(host, result) {
        var conflicts = (result.conflicts || []).slice(0, 10);
        var groups = new Map();
        conflicts.forEach(function (message) {
            var title = $t('Other conflicts');
            if (/^Stored mapping for ".*" points outside the configured Magento root\.$/.test(message)) {
                title = $t('Mapping outside Magento root');
            } else if (/URL key.*already exists/i.test(message)) {
                title = $t('URL key already in use');
            }
            if (!groups.has(title)) { groups.set(title, []); }
            groups.get(title).push(message);
        });
        groups.forEach(function (messages, title) {
            var details = node('details', 'vec-sync-conflict-group');
            var summary = node('summary');
            summary.appendChild(node('strong', '', title));
            summary.appendChild(node('span', '', $t('Examples shown: %1').replace('%1', messages.length)));
            details.appendChild(summary);
            var list = node('ul');
            messages.forEach(function (message) { list.appendChild(node('li', '', message)); });
            details.appendChild(list);
            host.appendChild(details);
        });
        if (result.conflict_count > conflicts.length) {
            host.appendChild(node('p', 'vec-sync-results-note',
                $t('Showing %1 of %2 conflicts. Full details are available in synchronization history.')
                    .replace('%1', conflicts.length).replace('%2', result.conflict_count)));
        }
    }

    return function createProgressResults(host) {
        var records = new Map(), selected = '', current = '', manualSelection = false;
        var latest = {}, activity = '', globalData = false, detailKey = '';
        var list = node('div', 'vec-sync-tree-list');
        var detail = node('div', 'vec-sync-tree-detail');
        var layout = node('div', 'vec-sync-results-layout');
        var heading = node('h3', 'vec-sync-section-title', $t('Trees'));
        var dataSummary = node('p', 'vec-sync-data-summary');
        list.setAttribute('role', 'group');
        list.setAttribute('aria-label', $t('Tree results'));
        layout.appendChild(list);
        layout.appendChild(detail);
        host.appendChild(heading);
        host.appendChild(layout);
        host.appendChild(dataSummary);

        function add(code) {
            if (!records.has(code)) {
                var button = node('button', 'vec-sync-tree-button');
                var name = node('strong', '', code);
                var state = node('span');
                button.type = 'button';
                button.appendChild(name);
                button.appendChild(state);
                button.addEventListener('click', function () {
                    selected = code;
                    manualSelection = true;
                    render();
                });
                list.appendChild(button);
                records.set(code, {button: button, state: state, result: null});
            }
            return records.get(code);
        }

        function render() {
            host.hidden = !records.size && !dataSummary.textContent;
            layout.hidden = heading.hidden = !records.size;
            dataSummary.hidden = !dataSummary.textContent;
            records.forEach(function (record, code) {
                var result = record.result;
                var conflicts = result && Number(result.conflict_count) || 0;
                record.button.setAttribute('aria-pressed', String(selected === code));
                record.button.setAttribute('data-state', result ? (conflicts ? 'warning' : 'success') : 'pending');
                record.state.textContent = result
                    ? (conflicts ? $t('Conflicts: %1').replace('%1', conflicts) : $t('Completed'))
                    : (code === current && latest.state === 'paused' ? $t('Paused')
                        : code === current && latest.state === 'error' ? $t('Incomplete')
                            : ['success', 'warning'].indexOf(latest.state) !== -1 ? $t('Result unavailable')
                            : code === current && !globalData ? $t('In progress') : $t('Result pending'));
            });
            if (!records.has(selected)) { return; }
            var record = records.get(selected);
            var nextDetailKey = JSON.stringify(record.result ? [selected, record.result]
                : [selected, current, activity, globalData, latest.item]);
            // Keep expanded messages and keyboard focus when polling unchanged results.
            if (nextDetailKey === detailKey) { return; }
            detailKey = nextDetailKey;
            detail.textContent = '';
            detail.appendChild(node('h3', 'vec-sync-section-title', selected));
            if (record.result) {
                var result = record.result;
                var changes = result.stats || {};
                detail.appendChild(node('p', 'vec-sync-tree-summary',
                    $t('Created: %1 · Moved: %2 · Deleted: %3 · Unmatched: %4')
                        .replace('%1', changes.created || 0).replace('%2', changes.moved || 0)
                        .replace('%3', changes.deleted || 0).replace('%4', changes.unmatched || 0)));
                if (result.conflict_count) { renderConflicts(detail, result); }
                else { detail.appendChild(node('p', 'vec-sync-tree-success', $t('No conflicts.'))); }
            } else {
                var card = node('div', 'vec-sync-current-activity');
                card.appendChild(node('p', '', selected === current && !globalData ? activity : $t('Waiting for the tree result.')));
                if (selected === current && !globalData && latest.item && latest.item !== current) {
                    card.appendChild(node('strong', '', latest.item));
                }
                detail.appendChild(card);
            }
        }

        function update(progress, message, isData, final) {
            latest = progress;
            activity = message;
            globalData = isData;
            if (progress.tree_code && !isData) {
                current = String(progress.tree_code);
                add(current);
                if (!manualSelection) { selected = current; }
            }
            var results = progress.stats && progress.stats.results || progress.results || {};
            var treeResults = results.tree && results.tree.tree_results || [];
            treeResults.forEach(function (result) { add(String(result.tree_code)).result = result; });
            if (final && !manualSelection && treeResults.length) {
                selected = String((treeResults.find(function (result) { return result.conflict_count > 0; })
                    || treeResults[0]).tree_code);
            }
            if (!selected && records.size) { selected = records.keys().next().value; }
            if (results.data) {
                dataSummary.textContent = results.data.skipped ? $t('Category data: no updates were configured.') :
                    $t('Category data: fetched %1 · Updated values: %2')
                        .replace('%1', results.data.fetched || 0).replace('%2', results.data.attributes || 0);
            }
            render();
        }

        function reset() {
            records.clear();
            list.textContent = detail.textContent = dataSummary.textContent = '';
            selected = current = detailKey = '';
            manualSelection = false;
            host.hidden = true;
        }

        return {update: update, reset: reset};
    };
});
