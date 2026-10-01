define([], function () {
    'use strict';

    function normalizeKey(key) {
        return String(key || '');
    }

    function collapseBranch(collapsedNodes, key, getChildKeys, visited) {
        key = normalizeKey(key);
        if (!key || visited[key]) {
            return;
        }

        visited[key] = true;
        collapsedNodes[key] = true;
        (getChildKeys(key) || []).forEach(function (childKey) {
            collapseBranch(collapsedNodes, childKey, getChildKeys, visited);
        });
    }

    return {
        isExpanded: function (collapsedNodes, key, forceExpanded) {
            return !!forceExpanded || collapsedNodes[normalizeKey(key)] !== true;
        },

        toggle: function (collapsedNodes, key) {
            key = normalizeKey(key);

            if (!key) {
                return true;
            }

            if (collapsedNodes[key] === true) {
                delete collapsedNodes[key];
                return true;
            }

            collapsedNodes[key] = true;

            return false;
        },

        collapseBranch: function (collapsedNodes, key, getChildKeys) {
            collapseBranch(collapsedNodes, key, getChildKeys, {});

            return false;
        }
    };
});
