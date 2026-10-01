define([], function () {
    'use strict';

    function normalizeCode(value) {
        var normalized = String(value || '');

        if (typeof normalized.normalize === 'function') {
            normalized = normalized.normalize('NFKD');
        }
        normalized = normalized.replace(/[Łł]/g, function (character) {
            return character === 'Ł' ? 'L' : 'l';
        }).replace(/[ĐđØøÞþÆæŒœß]/g, function (character) {
            return {
                'Đ': 'D', 'đ': 'd', 'Ø': 'O', 'ø': 'o', 'Þ': 'Th', 'þ': 'th',
                'Æ': 'Ae', 'æ': 'ae', 'Œ': 'Oe', 'œ': 'oe', 'ß': 'ss'
            }[character];
        }).replace(/[\u0300-\u036f]/g, '');

        return normalized.toLowerCase().replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '').slice(0, 128);
    }

    return {
        fromPathLabels: function (pathLabels) {
            return pathLabels.map(normalizeCode).filter(function (value) {
                return value !== '';
            }).join('__').slice(0, 128);
        }
    };
});
