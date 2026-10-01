define([], function () {
    'use strict';

    var configurationSelector = '[data-role="category-tree-configuration"]';

    function collect(list) {
        return Array.prototype.map.call(list.querySelectorAll(configurationSelector), function (configuration) {
            return Number(configuration.getAttribute('data-category-tree-id') || 0);
        });
    }

    function find(list, categoryTreeId) {
        return list.querySelector(
            configurationSelector + '[data-category-tree-id="' + Number(categoryTreeId) + '"]'
        );
    }

    function restore(list, categoryTreeIds) {
        categoryTreeIds.forEach(function (categoryTreeId) {
            var configuration = find(list, categoryTreeId);

            if (configuration) {
                list.appendChild(configuration);
            }
        });
    }

    function clearDragState(list, state) {
        state.draggedId = 0;
        state.beforeDrag = [];
        Array.prototype.forEach.call(list.querySelectorAll(configurationSelector), function (configuration) {
            configuration.classList.remove('is-dragging', 'is-drop-target');
        });
    }

    function bind(list, options, scope) {
        var state = {draggedId: 0, beforeDrag: [], busy: false};

        if (!list) {
            return;
        }

        function persist(previousOrder) {
            state.busy = true;
            list.classList.add('is-saving');
            Promise.resolve(options.save(collect(list)))
                .then(function (response) {
                    if (!response || !response.success) {
                        throw new Error(response && response.message ? response.message : options.failureMessage);
                    }
                    if (options.onSuccess) {
                        options.onSuccess(response.message || options.successMessage);
                    }
                })
                .catch(function (error) {
                    restore(list, previousOrder);
                    if (options.onError) {
                        options.onError(error && error.message ? error.message : options.failureMessage);
                    }
                })
                .then(function () {
                    state.busy = false;
                    list.classList.remove('is-saving');
                });
        }

        scope.listen(list, 'dragstart', function (event) {
            var configuration = event.target.closest(configurationSelector);

            if (!configuration || state.busy) {
                return;
            }
            state.draggedId = Number(configuration.getAttribute('data-category-tree-id') || 0);
            state.beforeDrag = collect(list);
            configuration.classList.add('is-dragging');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(state.draggedId));
            }
        });
        scope.listen(list, 'dragover', function (event) {
            var configuration = event.target.closest(configurationSelector);

            if (!configuration || !state.draggedId || state.busy) {
                return;
            }
            event.preventDefault();
            configuration.classList.add('is-drop-target');
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
        });
        scope.listen(list, 'dragleave', function (event) {
            var configuration = event.target.closest(configurationSelector);

            if (configuration) {
                configuration.classList.remove('is-drop-target');
            }
        });
        scope.listen(list, 'drop', function (event) {
            var source = find(list, state.draggedId);
            var target = event.target.closest(configurationSelector);

            event.preventDefault();
            if (!source || !target || source === target || state.busy) {
                clearDragState(list, state);
                return;
            }
            if (
                Array.prototype.indexOf.call(list.children, source)
                < Array.prototype.indexOf.call(list.children, target)
            ) {
                target.after(source);
            } else {
                target.before(source);
            }
            persist(state.beforeDrag);
            clearDragState(list, state);
        });
        scope.listen(list, 'dragend', function () {
            clearDragState(list, state);
        });
    }

    return {
        bind: bind,
        collect: collect
    };
});
