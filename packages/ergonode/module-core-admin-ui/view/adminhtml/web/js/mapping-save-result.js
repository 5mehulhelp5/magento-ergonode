define([], function () {
    'use strict';

    var storageKeyPrefix = 'ergonode.mapping.save-result.';

    function getStorage() {
        try {
            return window.sessionStorage;
        } catch (error) {
            return null;
        }
    }

    function storageKey(scope) {
        return storageKeyPrefix + String(scope || 'default');
    }

    function persist(scope, response) {
        var storage = getStorage();

        if (!storage || !response || response.tone !== 'warning' || !response.message) {
            return;
        }

        storage.setItem(storageKey(scope), JSON.stringify({
            tone: 'warning',
            message: String(response.message)
        }));
    }

    function consume(scope) {
        var storage = getStorage();
        var key = storageKey(scope);
        var serialized;
        var result;

        if (!storage) {
            return null;
        }

        serialized = storage.getItem(key);
        storage.removeItem(key);
        if (!serialized) {
            return null;
        }

        try {
            result = JSON.parse(serialized);
        } catch (error) {
            return null;
        }

        return result && result.tone === 'warning' && result.message
            ? result
            : null;
    }

    function show(messageComponent, result) {
        if (!messageComponent || !result) {
            return;
        }

        messageComponent.show('warning', result.message, 0);
    }

    return {
        consume: consume,
        persist: persist,
        show: show
    };
});
