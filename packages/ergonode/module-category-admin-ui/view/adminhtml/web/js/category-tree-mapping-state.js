define([], function () {
    'use strict';

    function normalizeMagentoId(magentoId) {
        magentoId = Number(magentoId || 0);

        return magentoId > 0 ? magentoId : null;
    }

    // The owner rebuilds this index after removing or replacing model rows.
    // Assignment and addition keep it current during synchronous bulk edits.
    function createIndex(categories) {
        var byCode = Object.create(null);
        var byMagentoId = Object.create(null);

        function add(category) {
            var id = normalizeMagentoId(category.magento_category_id);

            byCode[category.code] = category;
            if (id !== null) {
                if (!byMagentoId[id]) {
                    byMagentoId[id] = [];
                }
                byMagentoId[id].push(category);
            }
        }

        categories.forEach(add);

        return {
            add: add,
            find: function (code) {
                return byCode[code] || null;
            },
            findByMagentoId: function (id) {
                return (byMagentoId[normalizeMagentoId(id)] || [])[0] || null;
            },
            assign: function (code, magentoId) {
                var target = byCode[code];
                var nextId = normalizeMagentoId(magentoId);
                var previousId;
                var changed = false;

                if (!target) {
                    return false;
                }
                previousId = normalizeMagentoId(target.magento_category_id);
                if (nextId !== null) {
                    (byMagentoId[nextId] || []).forEach(function (category) {
                        if (category !== target) {
                            category.magento_category_id = null;
                            changed = true;
                        }
                    });
                    byMagentoId[nextId] = [target];
                }
                if (previousId !== nextId) {
                    if (previousId !== null) {
                        byMagentoId[previousId] = (byMagentoId[previousId] || []).filter(function (category) {
                            return category !== target;
                        });
                    }
                    target.magento_category_id = nextId;
                    changed = true;
                }

                return changed;
            }
        };
    }

    return {
        createIndex: createIndex,
        findCategoryById: function (categories, categoryId) {
            categoryId = normalizeMagentoId(categoryId);

            if (categoryId === null) {
                return null;
            }

            return categories.filter(function (category) {
                return Number(category.id || 0) === categoryId;
            })[0] || null;
        },

        findByMagentoId: function (categories, magentoId) {
            magentoId = normalizeMagentoId(magentoId);

            if (magentoId === null) {
                return null;
            }

            return categories.filter(function (category) {
                return Number(category.magento_category_id || 0) === magentoId;
            })[0] || null;
        },

        assign: function (categories, code, magentoId) {
            return createIndex(categories).assign(code, magentoId);
        },

        remove: function (categories, code) {
            var removed = null;
            var index = -1;

            categories.some(function (category, categoryIndex) {
                if (category.code !== code) {
                    return false;
                }
                removed = category;
                index = categoryIndex;

                return true;
            });

            if (!removed) {
                return false;
            }

            categories.forEach(function (category) {
                if (category.parent_code === code) {
                    category.parent_code = removed.parent_code || null;
                }
                if (category.source_parent_code === code) {
                    category.source_parent_code = removed.source_parent_code || null;
                }
            });
            categories.splice(index, 1);

            return true;
        },

        count: function (categories) {
            return categories.filter(function (category) {
                return normalizeMagentoId(category.magento_category_id) !== null;
            }).length;
        }
    };
});
