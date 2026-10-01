define([], function () {
    'use strict';

    function index(items, keyField, parentField, compare) {
        var byId = Object.create(null);
        var children = Object.create(null);
        var roots = [];

        items.forEach(function (item) {
            byId[String(item[keyField])] = item;
        });
        items.forEach(function (item) {
            var parent = String(item[parentField] || '');

            if (!children[parent]) {
                children[parent] = [];
            }
            children[parent].push(item);
            if (!byId[parent]) {
                roots.push(item);
            }
        });
        Object.keys(children).forEach(function (parent) {
            children[parent].sort(compare);
        });
        roots.sort(compare);

        return {byId: byId, children: children, roots: roots, keyField: keyField};
    }

    // Filter the complete model before creating DOM, so collapsed descendants still
    // participate in search, counters and the visibility of their ancestors.
    function project(tree, options) {
        function visit(items) {
            var nodes = [];
            var count = 0;

            items.forEach(function (item) {
                var branch = visit(tree.children[String(item[tree.keyField])] || []);
                var matches = options.matches(item);
                var context = options.isContext ? options.isContext(item) : false;
                var itemCount;

                if (options.isExcluded(item)) {
                    branch.nodes.forEach(function (node) {
                        nodes.push(node);
                    });
                    count += branch.count;
                    return;
                }
                if ((!matches || context) && !branch.count) {
                    return;
                }

                itemCount = branch.count + (matches && !context ? 1 : 0);
                nodes.push({item: item, children: branch.nodes, count: itemCount, context: context});
                count += itemCount;
            });

            return {nodes: nodes, count: count};
        }

        return visit(tree.roots);
    }

    return {index: index, project: project};
});
