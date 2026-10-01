define([], function () {
    'use strict';

    return {
        buildTree: buildTree,
        buildMagentoTree: buildMagentoTree,
        buildErgonodeTree: buildErgonodeTree
    };

    function buildErgonodeTree(sourceItems, changes, tree) {
        var roots = buildTree(sourceItems.map(sourceTreeItem), changes.filter(function (change) {
            return change.entity_type === 'source';
        }).map(function (change) {
            return Object.assign({}, change, {
                before: change.before ? sourceTreeItem(change.before) : null,
                after: change.after ? sourceTreeItem(change.after) : null
            });
        }));

        roots.forEach(function (node) {
            prepareSourceNode(node, null);
        });

        if (tree && tree.tree_code && Number(tree.root_category_id) > 0) {
            var root = treeNode({
                identifier: tree.tree_code,
                label: tree.tree_code,
                parent_identifier: null,
                magento_category_id: tree.root_category_id,
                magento_label: tree.root_label,
                active: tree.is_active
            }, null, null);

            root.key = '__configured_root__:' + tree.tree_code;
            root.isConfiguredRoot = true;
            root.entityType = 'source';
            root.changeKeys = [];
            root.children = roots;

            return [root];
        }

        return roots;
    }

    function sourceTreeItem(item) {
        var treeItem = Object.assign({}, item);

        if (Object.prototype.hasOwnProperty.call(item, 'source_parent_identifier')) {
            treeItem.parent_identifier = item.source_parent_identifier;
        }
        if (Object.prototype.hasOwnProperty.call(item, 'source_sort_order')) {
            treeItem.sort_order = item.source_sort_order;
        }

        return treeItem;
    }

    function prepareSourceNode(node, createdAncestor) {
        node.entityType = 'source';
        node.changeKeys = ['source:' + node.identifier];
        if (node.ghost) {
            createdAncestor = null;
        }
        node.createdAncestor = !node.change ? createdAncestor : null;
        if (!node.ghost && node.change && (node.change.actions || []).indexOf('created') !== -1) {
            createdAncestor = node.item;
        }
        node.children.forEach(function (child) {
            prepareSourceNode(child, createdAncestor);
        });
    }

    function buildMagentoTree(sourceItems, targetItems, changes) {
        var sourceChanges = changes.filter(function (change) {
            return change.entity_type === 'source';
        });
        var targetChanges = changes.filter(function (change) {
            return change.entity_type === 'target';
        });
        var tree = createTree(targetItems, targetChanges);
        var roots = tree.roots;
        var targetNodes = Object.create(null);
        var sourceChangesByIdentifier = Object.create(null);
        var projectedSource = Object.create(null);
        var previousLayout = {
            tree: tree,
            source: beforeItems(sourceItems, sourceChanges),
            target: beforeItems(targetItems, targetChanges),
            nodes: Object.create(null),
            changes: sourceChangesByIdentifier
        };
        var previousSourceLayout = Object.assign({}, previousLayout, {
            source: Object.create(null),
            nodes: Object.create(null),
            keySuffix: 'source-layout-from'
        });

        Object.keys(previousLayout.source).forEach(function (identifier) {
            previousSourceLayout.source[identifier] = sourceTreeItem(previousLayout.source[identifier]);
        });

        roots.forEach(function (root) {
            prepareTargetNode(root, targetNodes);
        });
        sourceChanges.forEach(function (change) {
            sourceChangesByIdentifier[change.entity_identifier] = change;
        });
        sourceItems.forEach(function (item) {
            projectedSource[item.identifier] = {
                item: item,
                change: sourceChangesByIdentifier[item.identifier] || null
            };
        });
        sourceChanges.forEach(function (change) {
            if (!projectedSource[change.entity_identifier] && (change.after || change.before)) {
                projectedSource[change.entity_identifier] = {
                    item: change.after || change.before,
                    change: change
                };
            }
        });
        Object.keys(projectedSource).forEach(function (identifier) {
            var projection = projectedSource[identifier];
            var mappingIdentifier = mappedTargetIdentifier(projection.item, projection.change);
            var targetNode = mappingIdentifier ? targetNodes[mappingIdentifier] : null;

            if (targetNode) {
                attachSource(targetNode, projection.item, projection.change);
            }
            appendPreviousMapping(roots, targetNodes, projection.change, previousLayout);
            appendPreviousSourceMovement(roots, targetNodes, projection.change, previousSourceLayout);
        });

        sortTree(roots);
        return roots;
    }

    function prepareTargetNode(node, targetNodes) {
        var identifier = String(node.identifier);

        if (!node.changes) {
            node.changes = node.change ? [node.change] : [];
            node.changeKeys = ['target:' + identifier];
            node.sourceItems = [];
            if (node.item.category_code) {
                node.changeKeys.push('source:' + node.item.category_code);
            }
        }
        if (!targetNodes[identifier] || targetNodes[identifier].ghost) {
            targetNodes[identifier] = node;
        }
        node.children.forEach(function (child) {
            prepareTargetNode(child, targetNodes);
        });
    }

    function mappedTargetIdentifier(item, change) {
        var snapshot = item || {};

        if (change && (change.actions || []).indexOf('deleted') !== -1) {
            snapshot = change.before || snapshot;
        } else if (change && change.after) {
            snapshot = change.after;
        } else if (change && change.before) {
            snapshot = change.before;
        }

        return snapshot.magento_category_id ? String(snapshot.magento_category_id) : '';
    }

    function attachSource(node, sourceItem, change) {
        if (!node.sourceItems.some(function (item) {
            return item.identifier === sourceItem.identifier;
        })) {
            node.sourceItems.push(sourceItem);
        }
        appendUnique(node.changeKeys, 'source:' + sourceItem.identifier);
        if (change) {
            appendNodeChange(node, change);
        }
    }

    function appendNodeChange(node, change) {
        var exists = node.changes.some(function (candidate) {
            return candidate.entity_type === change.entity_type &&
                candidate.entity_identifier === change.entity_identifier;
        });

        if (!exists) {
            node.changes.push(change);
        }
        node.change = combineChanges(node.changes);
    }

    function combineChanges(changes) {
        var preferred = changes.find(function (change) {
            return change.entity_type === 'source';
        }) || changes[0];
        var actions = [];

        changes.forEach(function (change) {
            (change.actions || []).forEach(function (action) {
                appendUnique(actions, action);
            });
        });

        return {
            entity_type: preferred.entity_type,
            entity_identifier: preferred.entity_identifier,
            category_code: preferred.category_code,
            actions: actions,
            before: preferred.before,
            after: preferred.after,
            related_changes: changes
        };
    }

    function appendPreviousMapping(roots, targetNodes, change, previousLayout) {
        var actions = change ? (change.actions || []) : [];
        var beforeIdentifier;
        var afterIdentifier;
        var previousTarget;

        if (!change || !hasMappingAction(actions) || !change.before) {
            return;
        }
        beforeIdentifier = change.before.magento_category_id
            ? String(change.before.magento_category_id)
            : '';
        afterIdentifier = change.after && change.after.magento_category_id
            ? String(change.after.magento_category_id)
            : '';
        if (!beforeIdentifier || beforeIdentifier === afterIdentifier) {
            return;
        }
        previousTarget = targetNodes[beforeIdentifier];
        if (actions.indexOf('disconnected') === -1) {
            previousTarget = previousLayout.tree.previousNode(beforeIdentifier);
            if (previousTarget) {
                prepareTargetNode(previousTarget, targetNodes);
            }
        }
        if (!previousTarget) {
            return;
        }
        if (actions.indexOf('disconnected') !== -1) {
            attachSource(previousTarget, change.before, change);
            return;
        }
        if (hasMovementAction(actions)) {
            appendProjectedMovement(targetNodes[beforeIdentifier],
                appendPreviousMagentoLocation(roots, targetNodes, change.before, previousLayout));
            return;
        }
        previousTarget.children.push({
            key: 'source:' + change.entity_identifier + '::mapping-from',
            identifier: change.entity_identifier,
            parentIdentifier: previousTarget.identifier,
            item: {
                identifier: change.entity_identifier,
                label: change.before.label || change.entity_identifier,
                sort_order: Number.MAX_SAFE_INTEGER,
                is_source_only: true
            },
            change: combineChanges([change]),
            changes: [change],
            changeKeys: ['source:' + change.entity_identifier],
            ghost: 'mapping-from',
            sourceItems: [change.before],
            children: []
        });
    }

    function appendProjectedMovement(current, previous) {
        var action;

        if (!current || current.ghost || current === previous) {
            return;
        }
        if (current.parentIdentifier !== previous.parentIdentifier) {
            action = 'moved';
        } else if (current.item.sort_order !== previous.item.sort_order) {
            action = 'reordered';
        } else {
            return;
        }
        // A former mapping can still exist in Magento, attached to a different source category.
        // Describe that row's displayed transition without assigning the remapped source's name to it.
        appendNodeChange(current, {
            entity_type: 'target',
            entity_identifier: current.identifier,
            actions: [action],
            before: Object.assign({}, previous.item, {parent_identifier: previous.parentIdentifier}),
            after: current.item
        });
    }

    function appendPreviousSourceMovement(roots, targetNodes, change, layout) {
        var actions = change ? (change.actions || []) : [];
        var before;

        if (!change || !change.before || !change.after || !change.after.magento_category_id ||
            (actions.indexOf('source_moved') === -1 && actions.indexOf('source_reordered') === -1)) {
            return;
        }
        // Source movement and remapping are separate transitions. Keep the current mapped identity
        // at its former source location as well as the old mapping in the former effective layout.
        before = Object.assign({}, sourceTreeItem(change.before), {
            magento_category_id: change.after.magento_category_id
        });
        appendPreviousMagentoLocation(roots, targetNodes, before, layout);
    }

    function appendPreviousMagentoLocation(roots, targetNodes, sourceItem, layout) {
        var identifier = sourceItem.identifier;
        var change = layout.changes[identifier] || null;
        var targetIdentifier = sourceItem.magento_category_id ? String(sourceItem.magento_category_id) : '';
        var targetItem = layout.target[targetIdentifier];
        var previousTarget = targetItem ? layout.tree.previousNode(targetIdentifier) : null;
        var sourceParent = sourceItem.parent_identifier == null ? null : layout.source[sourceItem.parent_identifier];
        var parent;
        var node;
        var root;

        if (layout.nodes[identifier]) {
            return layout.nodes[identifier];
        }
        if (sourceParent) {
            parent = appendPreviousMagentoLocation(roots, targetNodes, sourceParent, layout);
        } else if (sourceItem.parent_identifier && targetItem) {
            // Incomplete source history cannot supply an ancestor: retain the recorded Magento location.
            parent = layout.tree.previousNode(targetItem.parent_identifier);
        } else {
            root = roots.find(function (candidate) {
                return !candidate.ghost;
            }) || roots[0];
            parent = root ? layout.tree.previousNode(root.identifier) : null;
        }
        if (parent) {
            prepareTargetNode(parent, targetNodes);
        }
        if (previousTarget && (previousTarget.ghost || !change || !(hasMovementAction(change.actions || []) ||
            hasMappingAction(change.actions || []) || (change.actions || []).indexOf('renamed') !== -1)) &&
            (parent ? parent.children.indexOf(previousTarget) !== -1 : roots.indexOf(previousTarget) !== -1)) {
            prepareTargetNode(previousTarget, targetNodes);
            if (previousTarget.ghost) {
                attachSource(previousTarget, sourceItem, change);
            }
            layout.nodes[identifier] = previousTarget;
            return previousTarget;
        }
        node = {
            key: 'source:' + identifier + '::' + (layout.keySuffix || 'layout-from'),
            identifier: targetIdentifier || identifier,
            parentIdentifier: parent ? parent.identifier : null,
            item: targetItem ? Object.assign({}, targetItem, {category_code: identifier}) : Object.assign({}, sourceItem, {
                is_source_only: true
            }),
            change: change ? combineChanges([change]) : null,
            changes: change ? [change] : [],
            changeKeys: ['source:' + identifier],
            ghost: 'from',
            sourceItems: [sourceItem],
            children: []
        };
        if (targetIdentifier) {
            node.changeKeys.unshift('target:' + targetIdentifier);
        }
        // The source snapshot owns the manual layout order; the saved Magento path remains mapping metadata.
        node.item.sort_order = sourceItem.sort_order;
        layout.nodes[identifier] = node;
        appendTreeNode(roots, parent, node);

        return node;
    }

    function hasMappingAction(actions) {
        return ['connected', 'disconnected', 'reconnected'].some(function (action) {
            return actions.indexOf(action) !== -1;
        });
    }

    function appendUnique(items, value) {
        if (items.indexOf(value) === -1) {
            items.push(value);
        }
    }

    function buildTree(items, changes) {
        return createTree(items, changes).roots;
    }

    function beforeItems(items, changes) {
        var indexed = Object.create(null);

        items.forEach(function (item) {
            indexed[item.identifier] = item;
        });
        changes.forEach(function (change) {
            if (change.before) {
                indexed[change.entity_identifier] = change.before;
            } else {
                delete indexed[change.entity_identifier];
            }
        });

        return indexed;
    }

    function createTree(items, changes) {
        var changesByIdentifier = Object.create(null);
        var currentNodes = Object.create(null);
        var previousNodes = Object.create(null);
        var previousItems = beforeItems(items, changes);
        var roots = [];

        changes.forEach(function (change) {
            changesByIdentifier[change.entity_identifier] = change;
        });
        items.forEach(function (item) {
            currentNodes[item.identifier] = treeNode(item, changesByIdentifier[item.identifier] || null, null);
        });
        items.forEach(function (item) {
            var parent = item.parent_identifier == null ? null : currentNodes[item.parent_identifier];

            appendTreeNode(roots, parent, currentNodes[item.identifier]);
        });
        changes.forEach(function (change) {
            if (change.before && (hasMovementAction(change.actions || []) ||
                (change.actions || []).indexOf('deleted') !== -1)) {
                previousNode(change.entity_identifier);
            }
        });

        sortTree(roots);
        return {roots: roots, previousNode: previousNode};

        function previousNode(identifier) {
            if (identifier == null) {
                return null;
            }
            var item = previousItems[identifier];
            var current = currentNodes[identifier];
            var change = changesByIdentifier[identifier] || null;
            var parent;
            var node;

            if (!item) {
                return null;
            }
            if (previousNodes[identifier]) {
                return previousNodes[identifier];
            }
            parent = previousNode(item.parent_identifier);
            if (current && !(change && hasMovementAction(change.actions || [])) &&
                current.item.label === item.label && current.item.sort_order === item.sort_order &&
                (parent ? parent.children.indexOf(current) !== -1 : roots.indexOf(current) !== -1)) {
                previousNodes[identifier] = current;
                return current;
            }
            node = treeNode(item, change, current ? 'from' : 'deleted');
            previousNodes[identifier] = node;
            appendTreeNode(roots, parent, node);

            return node;
        }
    }

    function treeNode(item, change, ghost) {
        return {
            key: item.identifier + (ghost ? '::' + ghost : ''),
            identifier: item.identifier,
            parentIdentifier: item.parent_identifier,
            item: item,
            change: change,
            ghost: ghost,
            children: []
        };
    }

    function appendTreeNode(roots, parent, node) {
        var siblings = parent ? parent.children : roots;

        siblings.push(node);

    }

    function sortTree(nodes) {
        nodes.sort(compareNodes);
        nodes.forEach(function (node) {
            sortTree(node.children);
        });
    }

    function hasMovementAction(actions) {
        return ['moved', 'reordered', 'source_moved', 'source_reordered'].some(function (action) {
            return actions.indexOf(action) !== -1;
        });
    }

    function compareNodes(left, right) {
        var position = Number(left.item.sort_order || 0) - Number(right.item.sort_order || 0);

        return position || String(left.item.label || left.identifier)
            .localeCompare(String(right.item.label || right.identifier));
    }
});
