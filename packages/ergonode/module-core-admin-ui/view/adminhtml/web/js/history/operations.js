define(['mage/translate'], function ($t) {
    'use strict';

    function escape(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
        });
    }

    function text(value) { return escape(value); }

    var operationLabels = {
        save: $t('Mapping saved'), auto_map: $t('Automatic mapping'), synchronize: $t('Synchronization'),
        refresh_snapshot: $t('Snapshot refreshed'), delete_snapshot: $t('Snapshot removed'),
        save_options: $t('Option mappings saved'), refresh_options: $t('Options refreshed'),
        delete_option_snapshot: $t('Option snapshot removed')
    };
    function date(value) {
        if (!value) { return ''; }
        var timestamp = new Date(value.replace(' ', 'T') + 'Z');
        return Number.isNaN(timestamp.getTime()) ? value : timestamp.toLocaleString(document.documentElement.lang || undefined);
    }

    function card(operation, selectedId, historyUrl) {
        var id = Number(operation.operation_id);
        var label = $t(operationLabels[operation.operation_code] || operation.operation_code);
        var href = '';
        if (historyUrl) {
            var url = new URL(historyUrl, window.location.href);
            url.searchParams.set('operation_id', id);
            href = url.href;
        }
        var control = href ? 'a' : 'button';
        var attributes = href ? 'href="' + escape(href) + '"' : 'type="button" data-action="select" aria-pressed="' + (id === selectedId) + '"';
        return '<div class="veah-operation' + (id === selectedId ? ' is-selected' : '') + '">' +
            '<' + control + ' class="veah-operation-select" ' + attributes + ' data-id="' + id + '">' +
            '<span class="veah-operation-top"><strong>' + escape(label) + '</strong><span class="veah-status">' + text(operation.status === 'failed' ? $t('Failed') : $t('Completed')) + '</span></span>' +
            '<span class="veah-operation-meta"><time>' + escape(date(operation.finished_at || operation.started_at)) + '</time>' +
            '<span class="veah-operation-origin">' + escape(String(operation.origin).toUpperCase()) + '</span></span>' +
            '<span class="veah-operation-meta">' + escape(operation.actor_name || String(operation.origin).toUpperCase()) +
            '</span>' +
            '<span class="veah-operation-count">' + escape($t('%1 changes').replace('%1', operation.change_count)) + '</span></' + control + '>' + (href ? '' :
            '<button type="button" class="veah-operation-details" data-action="details" data-id="' + id + '" aria-label="' +
            escape($t('Change list: %1').replace('%1', label + ', ' + date(operation.started_at))) + '"><span class="veah-list-icon" aria-hidden="true"></span></button>') + '</div>';
    }

    return {card: card};
});
