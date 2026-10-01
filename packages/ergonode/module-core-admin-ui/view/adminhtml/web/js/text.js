define([], function () {
    'use strict';

    function normalize(value) {
        return (value || '').toString().trim().toLowerCase();
    }

    function escapeHtml(value) {
        return (value || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    return {
        normalize: normalize,
        escapeHtml: escapeHtml
    };
});
