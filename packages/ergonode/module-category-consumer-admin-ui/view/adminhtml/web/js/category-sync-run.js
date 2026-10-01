define(['mage/translate'], function ($t) {
    'use strict';

    return function createSyncRun(options) {
        var runId, scope, force, categoryTreeId;
        var active = false, paused = false, timer = null, generation = 0;
        var failures = 0, waiting = 0;

        function schedule(attempt) {
            if (active && attempt === generation) {
                timer = window.setTimeout(function () { poll(attempt); }, 1000);
            }
        }

        function finish(response, attempt) {
            if (!active || attempt !== generation) {
                return;
            }
            active = false;
            paused = response.state === 'paused';
            window.clearTimeout(timer);
            options.onFinish(response);
        }

        function unconfirmed(attempt) {
            finish({state: 'error', confirmed: false, message: $t(
                'The synchronization result could not be confirmed. It may still be running. Check Synchronizations before retrying.'
            )}, attempt);
        }

        function receive(response, attempt) {
            if (!active || attempt !== generation || !response || !response.state) {
                return false;
            }
            if (['success', 'warning', 'error', 'paused'].indexOf(response.state) !== -1) {
                finish(response, attempt);
            } else {
                options.onUpdate(response);
            }
            return true;
        }

        function payload() {
            return {run_id: runId, category_tree_id: categoryTreeId};
        }

        function poll(attempt) {
            options.request(options.urls.sync_status, payload(), 10000)
                .done(function (response) {
                    if (!active || attempt !== generation) {
                        return;
                    }
                    if (!receive(response, attempt)) {
                        failures++;
                    } else {
                        failures = 0;
                        waiting = response.state === 'waiting' ? waiting + 1 : 0;
                    }
                    if (failures >= 3 || waiting >= 15) {
                        unconfirmed(attempt);
                    }
                })
                .fail(function () {
                    if (active && attempt === generation && ++failures >= 3) {
                        unconfirmed(attempt);
                    }
                })
                .always(function () { schedule(attempt); });
        }

        function execute(resume) {
            var attempt = ++generation;
            var data = payload();

            active = true;
            paused = false;
            failures = 0;
            waiting = 0;
            window.clearTimeout(timer);
            options.onStart(scope, force);
            data.synchronization_scope = scope;
            data.synchronization_action = force ? 'reset-cursor-and-sync' : 'sync';
            data.resume = resume ? 1 : 0;
            // A long request never owns the displayed completion state: polling can recover it.
            options.request(options.urls.sync, data, 0)
                .done(function (response) { receive(response, attempt); });
            schedule(attempt);
        }

        function start(nextScope, nextForce, nextCategoryTreeId) {
            var bytes;

            if (active) {
                return;
            }
            bytes = window.crypto.getRandomValues(new Uint8Array(16));
            runId = Array.prototype.map.call(bytes, function (byte) {
                return byte.toString(16).padStart(2, '0');
            }).join('');
            scope = nextScope;
            force = nextForce;
            categoryTreeId = nextCategoryTreeId;
            execute(false);
        }

        function pause() {
            var attempt = generation;

            if (!active) {
                return;
            }
            options.onPauseRequest(true);
            options.request(options.urls.sync_pause, payload(), 10000)
                .done(function (response) {
                    if (active && attempt === generation && (!response || !response.success)) {
                        options.onPauseRequest(false, response && response.message);
                    }
                })
                .fail(function () {
                    if (active && attempt === generation) {
                        options.onPauseRequest(false, $t('Could not request a pause. Please try again.'));
                    }
                });
        }

        return {
            start: start,
            pause: pause,
            resume: function () { if (paused) { execute(true); } },
            destroy: function () { active = false; generation++; window.clearTimeout(timer); }
        };
    };
});
