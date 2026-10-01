define([
    'Ergonode_CoreAdminUi/js/autosave',
    'Ergonode_CoreAdminUi/js/request',
    'mage/translate'
], function (autosave, request, $t) {
    'use strict';

    var DEFAULT_DEBOUNCE_MS = 350;

    function create(root, config, options) {
        options = options || {};
        config = config || {};
        var timer = null;
        var debounceMs = typeof options.debounceMs === 'number'
            ? Math.max(0, options.debounceMs)
            : DEFAULT_DEBOUNCE_MS;
        var loader = root && root.querySelector('[data-role="language-loader"]');

        function setBusy(busy) {
            if (root) {
                root.setAttribute('data-language-busy', busy ? 'true' : 'false');
                root.setAttribute('aria-busy', busy ? 'true' : 'false');
            }
            if (loader) {
                loader.hidden = !busy;
            }
        }

        function schedulePersist() {
            timer = null;
            delegate.schedule();
        }

        function schedule() {
            setBusy(true);
            if (timer !== null) {
                window.clearTimeout(timer);
            }
            timer = window.setTimeout(schedulePersist, debounceMs);
        }

        function flush() {
            if (timer !== null) {
                window.clearTimeout(timer);
                timer = null;
                delegate.schedule();
            }

            return delegate.flush();
        }

        var delegate = autosave.create(root, {
            serialize: options.serialize,
            persist: function (snapshot) {
                var payload = Object.assign({}, snapshot, {revision: config.revision});

                return request.post(config.urls && config.urls.save, config, {
                    payload: JSON.stringify(payload)
                }).then(function (response) {
                    if (!response || typeof response.revision !== 'string' || !/^[a-f0-9]{64}$/.test(response.revision)) {
                        throw new Error($t('Missing or invalid language mapping revision. Reload the page.'));
                    }
                    config.revision = response.revision;
                    return response;
                });
            },
            onError: function (error) {
                setBusy(false);
                if (typeof options.onError === 'function') {
                    options.onError(error);
                }
            },
            onSaved: function (response, snapshot) {
                setBusy(false);
                if (typeof options.onSaved === 'function') {
                    options.onSaved(response, snapshot);
                }
            }
        });

        return {
            flush: flush,
            hasError: delegate.hasError,
            retry: function () {
                setBusy(true);
                return delegate.retry();
            },
            schedule: schedule
        };
    }

    return {
        create: create
    };
});
