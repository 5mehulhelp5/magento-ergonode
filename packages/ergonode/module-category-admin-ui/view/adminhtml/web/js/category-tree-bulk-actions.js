define([], function () {
    'use strict';

    return {
        resolve: function (source, identifiers, categories, magentoCategories) {
            var selected = Object.create(null);
            var existing = Object.create(null);
            var seen = Object.create(null);

            if (!identifiers || !identifiers.length || (source !== 'ergo' && source !== 'magento')) {
                return {disconnect: false};
            }
            identifiers.forEach(function (identifier) {
                var key = source === 'ergo' ? String(identifier || '') : Number(identifier || 0);

                selected[key] = true;
            });
            if (source === 'magento') {
                (magentoCategories || []).forEach(function (category) {
                    var id = Number(category.id || 0);

                    if (id > 0 && selected[id]) {
                        existing[id] = true;
                    }
                });
            }

            return {disconnect: (categories || []).some(function (category) {
                var code = String(category.code || '');
                var id = Number(category.magento_category_id || 0);

                if (source === 'magento') {
                    return id > 0 && !!existing[id];
                }
                if (seen[code]) {
                    return false;
                }
                seen[code] = true;

                return !!selected[code] && id > 0;
            })};
        }
    };
});
