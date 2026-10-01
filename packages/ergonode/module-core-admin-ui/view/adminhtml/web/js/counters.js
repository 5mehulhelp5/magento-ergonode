define([], function () {
    'use strict';

    function set(root, selector, value) {
        var counter = root ? root.querySelector(selector) : null;

        if (counter) {
            counter.textContent = String(Math.max(0, Number(value) || 0));
        }
    }

    return {
        set: set
    };
});
