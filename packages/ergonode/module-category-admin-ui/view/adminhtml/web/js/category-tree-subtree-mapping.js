define([], function () {
    'use strict';

    function branchIdentifiers(items, rootIdentifier, identifierField, parentField) {
        var childrenByParent = {};
        var root = String(rootIdentifier || '');
        var result = root ? [root] : [];
        var visited = {};
        var queue = root ? [root] : [];

        items.forEach(function (item) {
            var parent = String(item[parentField] || '');

            childrenByParent[parent] = childrenByParent[parent] || [];
            childrenByParent[parent].push(String(item[identifierField] || ''));
        });

        visited[root] = true;
        while (queue.length) {
            (childrenByParent[queue.shift()] || []).forEach(function (identifier) {
                if (!identifier || visited[identifier]) {
                    return;
                }

                visited[identifier] = true;
                result.push(identifier);
                queue.push(identifier);
            });
        }

        return result;
    }

    function descendants(categories, rootCode) {
        return branchIdentifiers(categories, rootCode, 'code', 'source_parent_code').slice(1);
    }

    return {
        branchIdentifiers: branchIdentifiers,
        descendants: descendants,

        setBranchActive: function (categories, rootCode, active) {
            var branch = {};

            [String(rootCode || '')].concat(descendants(categories, rootCode)).forEach(function (code) {
                branch[code] = true;
            });
            categories.forEach(function (category) {
                if (branch[String(category.code || '')]) {
                    category.active = !!active;
                }
            });
        },

        applyAssignments: function (categories, suggestions, scopeCodes) {
            var suggestionsByCode = {};
            var scope = {};
            var stats = {mapped: 0, unmatched: 0};

            suggestions.forEach(function (suggestion) {
                suggestionsByCode[String(suggestion.code || '')] = suggestion;
            });
            scopeCodes.forEach(function (code) {
                scope[String(code || '')] = true;
            });

            categories.forEach(function (category) {
                var suggestion;

                if (!scope[category.code] || !suggestionsByCode[category.code]) {
                    return;
                }

                suggestion = suggestionsByCode[category.code];
                category.magento_category_id = suggestion.magento_category_id
                    ? Number(suggestion.magento_category_id)
                    : null;
                category.mapping_source = String(suggestion.mapping_source || 'unmatched');
                category.sync_status = String(suggestion.sync_status || category.sync_status || 'pending');
                category.sync_message = String(suggestion.sync_message || '');

                if (!category.active) {
                    return;
                }
                if (category.magento_category_id) {
                    stats.mapped++;
                } else {
                    stats.unmatched++;
                }
            });

            return stats;
        }
    };
});
