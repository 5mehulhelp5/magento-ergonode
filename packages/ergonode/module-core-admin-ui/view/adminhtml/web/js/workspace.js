define(['mage/translate'], function ($t) {
    'use strict';

    var mounted = new WeakMap();

    function closestWithin(root, target, selector) {
        var match = target && target.closest ? target.closest(selector) : null;

        return match && (match === root || root.contains(match)) ? match : null;
    }

    function createScope(root) {
        var cleanups = [];
        var claims = Object.create(null);
        var destroyed = false;

        return {
            root: root,

            listen: function (target, type, listener, options) {
                if (!target || !target.addEventListener || destroyed) {
                    return function () {};
                }

                target.addEventListener(type, listener, options);

                var remove = function () {
                    target.removeEventListener(type, listener, options);
                };

                cleanups.push(remove);

                return remove;
            },

            delegate: function (type, selector, listener, options) {
                return this.listen(root, type, function (event) {
                    var match = closestWithin(root, event.target, selector);

                    if (match) {
                        listener(event, match);
                    }
                }, options);
            },

            cleanup: function (callback) {
                if (typeof callback === 'function') {
                    cleanups.push(callback);
                }
            },

            claim: function (element, key) {
                if (!element || destroyed) {
                    return false;
                }

                if (!claims[key]) {
                    claims[key] = new WeakSet();
                }
                if (claims[key].has(element)) {
                    return false;
                }

                claims[key].add(element);

                return true;
            },

            destroy: function () {
                if (destroyed) {
                    return;
                }

                destroyed = true;
                cleanups.reverse().forEach(function (cleanup) {
                    cleanup();
                });
                cleanups = [];
                claims = Object.create(null);
                mounted.delete(root);
            },

            isDestroyed: function () {
                return destroyed;
            }
        };
    }

    function mount(root, setup) {
        var scope;

        if (!root || !root.addEventListener) {
            throw new Error($t('Workspace wymaga elementu root.'));
        }

        if (mounted.has(root)) {
            return mounted.get(root);
        }

        scope = createScope(root);
        mounted.set(root, scope);

        try {
            if (typeof setup === 'function') {
                setup(scope);
            }
        } catch (error) {
            scope.destroy();
            throw error;
        }

        return scope;
    }

    return {
        mount: mount,
        get: function (root) {
            return mounted.get(root) || null;
        }
    };
});
