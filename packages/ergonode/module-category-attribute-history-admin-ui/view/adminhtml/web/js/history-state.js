define([], function () {
    'use strict';

    function rows(state, side) {
        if (!state) {
            return [];
        }
        var changes = state.changes || [];
        var entries = (state[side] || []).map(function (item) { return Object.assign({}, item); });
        changes.filter(function (change) {
            return change.side === side && !change.after && change.before;
        }).forEach(function (change) {
            entries.push(Object.assign({}, change.before, {deleted: true, active: false}));
        });

        return entries.map(function (item) {
            var own = changes.find(function (change) { return change.side === side && change.code === item.code; });
            var opposite = side === 'source' ? 'target' : 'source';
            var mappedCode = item.mapped_code || (own && own.before && own.before.mapped_code);
            var linkedChange = changes.find(function (change) {
                return change.side === opposite && change.code === mappedCode;
            });
            var linked = (state[opposite] || []).find(function (attribute) { return attribute.code === mappedCode; }) ||
                (linkedChange && (linkedChange.after || linkedChange.before));
            var actions = Array.from(new Set([].concat(own ? own.actions : [], linkedChange ? linkedChange.actions : [])));

            return Object.assign(item, {
                actions: actions,
                linked: linked || null,
                excluded: item.active === false || Boolean(linked && linked.active === false),
                change: own || linkedChange || null
            });
        }).filter(function (item) {
            return side === 'target' || !item.mapped_code || !item.active;
        }).sort(function (left, right) {
            return left.label.localeCompare(right.label) || left.code.localeCompare(right.code);
        });
    }

    function tone(item) {
        if (item.actions.includes('disconnected')) { return 'disconnected'; }
        if (item.actions.includes('deleted')) { return 'deleted'; }
        if (item.excluded && item.actions.length) { return 'excluded'; }
        if (item.actions.includes('connected') || item.actions.includes('reconnected') || item.actions.includes('included')) {
            return 'connected';
        }
        if (item.actions.includes('created')) { return 'created'; }
        return item.actions.length ? 'changed' : '';
    }

    function findVisibleChange(state, change) {
        var sourceRows = rows(state, 'source');
        if (change.side === 'source' && sourceRows.some(function (item) { return item.code === change.code; })) {
            return {side: 'source', code: change.code};
        }
        if (change.side === 'target') { return {side: 'target', code: change.code}; }
        var snapshot = change.after || change.before;
        return snapshot && snapshot.mapped_code ? {side: 'target', code: snapshot.mapped_code} : null;
    }

    function lacksDraftStatus(state) {
        return Boolean(state && (state.lacks_draft_status || [].concat(state.source || [], state.target || []).some(function (item) {
            return !Object.prototype.hasOwnProperty.call(item, 'is_draft');
        })));
    }

    return {rows: rows, tone: tone, findVisibleChange: findVisibleChange, lacksDraftStatus: lacksDraftStatus};
});
