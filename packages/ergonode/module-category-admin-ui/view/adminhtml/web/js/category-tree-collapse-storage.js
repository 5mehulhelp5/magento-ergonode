define([], function () {
    'use strict';

    var KEY_PREFIX = 'ergonode-category-tree-mapping:collapsed:v1:';

    function getKey(categoryTreeId) {
        categoryTreeId = Number(categoryTreeId || 0);

        return categoryTreeId > 0 ? KEY_PREFIX + String(categoryTreeId) : '';
    }

    function normalizeTreeState(value) {
        var normalized = {};

        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return normalized;
        }

        Object.keys(value).forEach(function (key) {
            if (value[key] === true) {
                normalized[key] = true;
            }
        });

        return normalized;
    }

    function load(categoryTreeId, storage) {
        var key = getKey(categoryTreeId);
        var decoded;
        var raw;

        if (!key || !storage || typeof storage.getItem !== 'function') {
            return null;
        }

        try {
            raw = storage.getItem(key);
            if (raw === null) {
                return null;
            }
            decoded = JSON.parse(raw);
        } catch (error) {
            return null;
        }

        if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)) {
            return null;
        }

        return {
            source: normalizeTreeState(decoded.source),
            magento: normalizeTreeState(decoded.magento)
        };
    }

    function save(categoryTreeId, state, storage) {
        var key = getKey(categoryTreeId);
        var payload;

        if (!key || !storage || typeof storage.setItem !== 'function') {
            return false;
        }

        payload = {
            source: normalizeTreeState(state && state.source),
            magento: normalizeTreeState(state && state.magento)
        };

        try {
            storage.setItem(key, JSON.stringify(payload));
        } catch (error) {
            return false;
        }

        return true;
    }

    return {
        load: load,
        save: save
    };
});
