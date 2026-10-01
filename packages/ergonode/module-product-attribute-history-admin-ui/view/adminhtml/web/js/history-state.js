define([], function () {
    'use strict';

    function rows(state, side, includeMapped) {
        if (!state) {
            return [];
        }
        var changes = (state.changes || []).filter(function (change) {
            return state.isOptionView ? change.entity === 'option' : change.entity !== 'option';
        });
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
                optionChanges: state.isOptionView ? [] : (state.changes || []).filter(function (change) {
                    return change.entity === 'option' && (change.side === side && change.attribute_code === item.code ||
                        change.side === opposite && change.attribute_code === mappedCode);
                }),
                actions: actions,
                linked: linked || null,
                excluded: item.active === false || Boolean(linked && linked.active === false),
                change: own || linkedChange || null
            });
        }).filter(function (item) {
            return includeMapped || side === 'target' || !item.mapped_code || !item.active;
        }).sort(function (left, right) {
            return left.label.localeCompare(right.label) || left.code.localeCompare(right.code);
        });
    }

    function tone(item) {
        if (!item.actions.length && item.optionChanges && item.optionChanges.length) {
            var tones = Array.from(new Set(item.optionChanges.map(function (change) {
                return tone({actions: change.actions, excluded: change.after && change.after.active === false});
            })));
            return tones.length === 1 ? tones[0] : 'changed';
        }
        if (item.actions.includes('disconnected')) { return 'disconnected'; }
        if (item.actions.includes('deleted')) { return 'deleted'; }
        if ((item.excluded || item.actions.includes('excluded')) && item.actions.length) { return 'excluded'; }
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

    function attribute(state, side, code) {
        var change = (state.changes || []).find(function (item) {
            return item.entity !== 'option' && item.side === side && item.code === code;
        });
        return {item: (state[side] || []).find(function (item) { return item.code === code; }) ||
            (change && (change.after || change.before)), change: change};
    }

    function optionView(state, context) {
        var selected = attribute(state, context.side, context.code);
        var opposite = context.side === 'source' ? 'target' : 'source';
        var linked = (selected.item && selected.item.mapped_code) ||
            (selected.change && selected.change.before && selected.change.before.mapped_code);
        var parents = {};
        parents[context.side] = context.code;
        parents[opposite] = linked || null;
        return {
            isOptionView: true,
            parents: parents,
            source: (state.options && state.options.source[parents.source]) || [],
            target: (state.options && state.options.target[parents.target]) || [],
            changes: (state.changes || []).filter(function (change) {
                return change.entity === 'option' && change.attribute_code === parents[change.side];
            })
        };
    }

    function groups(state, side) {
        return rows(state, side, true).map(function (item) {
            var options = rows(optionView(state, {side: side, code: item.code}), side);
            var count = state.option_counts ? (state.option_counts[side][item.code] || 0) : options.filter(function (option) { return !option.deleted; }).length;
            return {attribute: item, options: options, count: count};
        }).filter(function (group) {
            var item = group.attribute;
            return side === 'target' || !item.mapped_code || !item.active || group.options.length || group.count;
        });
    }

    return {rows: rows, tone: tone, findVisibleChange: findVisibleChange, optionView: optionView, groups: groups};
});
