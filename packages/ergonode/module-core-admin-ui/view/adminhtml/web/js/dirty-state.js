define([], function () {
    'use strict';

    function create(serialize) {
        var initial = '';
        var captured = false;

        function signature() {
            return JSON.stringify(serialize());
        }

        return {
            capture: function () {
                initial = signature();
                captured = true;
            },
            isDirty: function () {
                return captured && signature() !== initial;
            }
        };
    }

    return {
        create: create
    };
});
