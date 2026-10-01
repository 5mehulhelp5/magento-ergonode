define([], function () {
    'use strict';

    function normalize(value) {
        var code = String(value || '');

        if (typeof code.normalize === 'function') {
            code = code.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
        }
        code = code.replace(/[łŁ]/g, 'l');

        return code.toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .slice(0, 128);
    }

    function candidateFromAttributeSet(name, id) {
        var fallback = 'attribute_set_' + String(id || 'new');

        return normalize(name) || fallback;
    }

    function findCreationCollision(name, id, templates) {
        var candidateCode = candidateFromAttributeSet(name, id);
        var collection = Array.isArray(templates) ? templates : [];
        var codeCollision = collection.find(function (template) {
            return String(template.code || '') === candidateCode;
        });
        var nameCollision;

        if (codeCollision) {
            return {
                candidateCode: candidateCode,
                source: 'code',
                template: codeCollision
            };
        }

        collection.some(function (template) {
            var names = Array.isArray(template.names) ? template.names : [];

            if (!names.length && String(template.name || '').trim() !== '') {
                names = [template.name];
            }

            return names.some(function (templateName) {
                if (normalize(templateName) !== candidateCode) {
                    return false;
                }

                nameCollision = {
                    candidateCode: candidateCode,
                    matchedName: String(templateName),
                    source: 'name',
                    template: template
                };

                return true;
            });
        });

        return nameCollision || null;
    }

    return {
        candidateFromAttributeSet: candidateFromAttributeSet,
        findCreationCollision: findCreationCollision,
        normalize: normalize
    };
});
