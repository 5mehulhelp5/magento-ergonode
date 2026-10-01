define(['mage/translate'], function ($t) {
    'use strict';

    var storageKey = 'ergonode.attribute.refresh.result';

    function empty() {
        return {
            imported: 0,
            changed: 0
        };
    }

    function merge(summary, response) {
        response = response || {};
        summary.imported += Number(response.imported) || 0;
        summary.changed += Number(response.changed) || 0;

        return summary;
    }

    function storage() {
        try {
            return window.sessionStorage || null;
        } catch (error) {
            return null;
        }
    }

    function persist(summary) {
        var target = storage();

        if (!target) {
            return false;
        }

        try {
            target.setItem(storageKey, JSON.stringify(summary));
            return true;
        } catch (error) {
            return false;
        }
    }

    function consume() {
        var target = storage();
        var value;

        if (!target) {
            return null;
        }

        try {
            value = target.getItem(storageKey);
            target.removeItem(storageKey);
            value = value ? JSON.parse(value) : null;
        } catch (error) {
            return null;
        }

        return value && typeof value === 'object' ? merge(empty(), value) : null;
    }

    function message(summary) {
        return $t('Odświeżono atrybuty Ergonode: %1 pobranych, %2 zmienionych.')
            .replace('%1', String(summary.imported))
            .replace('%2', String(summary.changed));
    }

    function show(messageComponent, summary) {
        if (!messageComponent || !summary) {
            return;
        }

        messageComponent.show('success', message(summary));
    }

    return {
        consume: consume,
        empty: empty,
        merge: merge,
        persist: persist,
        show: show
    };
});
