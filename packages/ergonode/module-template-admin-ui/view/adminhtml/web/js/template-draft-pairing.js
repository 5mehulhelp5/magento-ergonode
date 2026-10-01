define([], function () {
    'use strict';

    function findComplementary(drafts, side) {
        var ownKey = side === 'attribute_set' ? 'attributeSetId' : 'templateCode';
        var oppositeKey = ownKey === 'templateCode' ? 'attributeSetId' : 'templateCode';
        var matches = (Array.isArray(drafts) ? drafts : []).filter(function (draft) {
            return !String(draft[ownKey] || '') && Boolean(String(draft[oppositeKey] || ''));
        });

        return matches.length === 1 ? matches[0] : null;
    }

    return {
        findComplementary: findComplementary
    };
});
