define([], function () {
    'use strict';

    var states = new WeakMap();

    function state(root) {
        if (!states.has(root)) {
            states.set(root, {
                payload: null,
                sourceElement: null,
                mappedElement: null
            });
        }

        return states.get(root);
    }

    function write(dataTransfer, payload, mimeType) {
        var serialized = JSON.stringify(payload);

        dataTransfer.setData(mimeType || 'application/json', serialized);
        dataTransfer.setData('text/plain', serialized);

        return serialized;
    }

    function read(dataTransfer, mimeType) {
        var raw;

        if (!dataTransfer) {
            return null;
        }

        try {
            raw = dataTransfer.getData(mimeType || 'application/json') || dataTransfer.getData('text/plain');

            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function isLeaving(element, event) {
        var relatedTarget = event.relatedTarget;
        var rect;

        if (relatedTarget && element.contains(relatedTarget)) {
            return false;
        }

        if (typeof event.clientX !== 'number' || typeof event.clientY !== 'number') {
            return true;
        }

        rect = element.getBoundingClientRect();

        return event.clientX < rect.left || event.clientX > rect.right ||
            event.clientY < rect.top || event.clientY > rect.bottom;
    }

    return {
        state: state,
        clear: function (root) {
            states.delete(root);
        },
        write: write,
        read: read,
        isLeaving: isLeaving
    };
});
