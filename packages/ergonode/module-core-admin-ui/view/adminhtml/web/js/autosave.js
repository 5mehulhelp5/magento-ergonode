define([], function () {
    'use strict';

    function create(root, options) {
        var region = root ? root.querySelector('[data-role="autosave-region"]') : null;
        var error = region ? region.querySelector('[data-role="autosave-error"]') : null;
        var pending = false;
        var inFlight = null;
        var blocked = false;
        var lastError = null;
        var waiters = [];

        options = options || {};

        function setState(state) {
            var saving = state === 'saving';

            if (region) {
                region.setAttribute('data-autosave-state', state);
                region.setAttribute('aria-busy', saving ? 'true' : 'false');
                if (saving) {
                    region.setAttribute('inert', '');
                } else {
                    region.removeAttribute('inert');
                }
            }
            if (error) {
                error.hidden = state !== 'error';
            }
        }

        function settleWaiters(failure) {
            var current = waiters.splice(0);

            current.forEach(function (waiter) {
                if (failure) {
                    waiter.reject(failure);
                    return;
                }
                waiter.resolve();
            });
        }

        function handleFailure(failure) {
            inFlight = null;
            pending = true;
            blocked = true;
            lastError = failure;
            setState('error');
            if (typeof options.onError === 'function') {
                options.onError(failure);
            }
            settleWaiters(failure);
        }

        function drain() {
            var saveRequest;
            var snapshot;

            if (inFlight || blocked || !pending) {
                return;
            }

            pending = false;
            setState('saving');
            try {
                snapshot = typeof options.serialize === 'function' ? options.serialize() : {};
                if (typeof options.persist !== 'function') {
                    throw new Error('Autosave requires a persist callback.');
                }
                saveRequest = options.persist(snapshot);
            } catch (failure) {
                handleFailure(failure);
                return;
            }

            inFlight = Promise.resolve(saveRequest);
            inFlight.then(function (response) {
                inFlight = null;
                if (pending) {
                    drain();
                    return;
                }
                if (typeof options.onSaved === 'function') {
                    options.onSaved(response, snapshot);
                }
                setState('saved');
                settleWaiters();
            }).catch(handleFailure);
        }

        function schedule() {
            pending = true;
            blocked = false;
            lastError = null;
            setState('saving');
            drain();
        }

        function retrySave() {
            if (!pending && !blocked) {
                return;
            }
            pending = true;
            blocked = false;
            lastError = null;
            setState('saving');
            drain();
        }

        function flush() {
            if (blocked) {
                return Promise.reject(lastError);
            }
            if (!pending && !inFlight) {
                return Promise.resolve();
            }

            return new Promise(function (resolve, reject) {
                waiters.push({resolve: resolve, reject: reject});
            });
        }

        setState('saved');

        return {
            flush: flush,
            hasError: function () {
                return blocked;
            },
            retry: retrySave,
            schedule: schedule
        };
    }

    return {
        create: create
    };
});
