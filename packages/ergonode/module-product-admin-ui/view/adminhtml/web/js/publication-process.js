define(['mage/translate'], function ($t) {
    'use strict';

    return function createProcess(progress, publishBatch, onFinish) {
        var running = false;
        var pauseRequested = false;
        var stopRequested = false;
        var resumeWaiting = null;

        function release() {
            if (resumeWaiting) {
                resumeWaiting();
                resumeWaiting = null;
            }
        }

        async function boundary() {
            if (pauseRequested && !stopRequested) {
                progress.setPauseState('paused');
                await new Promise(function (resolve) { resumeWaiting = resolve; });
            }
            return !stopRequested;
        }

        async function start(ids, describe) {
            if (running || !ids.length) {
                return;
            }
            running = true;
            pauseRequested = false;
            stopRequested = false;
            var partial = false;
            var activeBatch = [];

            progress.open(ids.length, {
                pause: function () { pauseRequested = true; },
                resume: function () { pauseRequested = false; release(); },
                stop: function () { stopRequested = true; release(); }
            });
            try {
                for (var offset = 0; offset < ids.length; offset += 50) {
                    if (!await boundary()) {
                        progress.stopped();
                        return;
                    }
                    activeBatch = ids.slice(offset, offset + 50);
                    progress.showBatch(offset / 50 + 1, Math.ceil(ids.length / 50), activeBatch.map(describe));
                    var response = await publishBatch(activeBatch);
                    if (!response.success || !Array.isArray(response.items)) {
                        throw new Error(response.message || $t('The operation returned an invalid response.'));
                    }
                    var received = response.items.map(function (item) { return item.product_id; });
                    if (received.length !== activeBatch.length || new Set(received).size !== received.length ||
                        activeBatch.some(function (id) { return !received.includes(id); })) {
                        throw new Error($t('The response does not include a result for every product in the current batch.'));
                    }
                    progress.applyBatch(response.items);
                    partial = partial || response.items.some(function (item) { return item.status !== 'success'; });
                    activeBatch = [];
                }
                if (stopRequested) {
                    progress.stopped();
                } else {
                    progress.complete(partial);
                }
            } catch (error) {
                progress.fail((error.message || $t('Connection lost.')) +
                    ' ' + $t('Process stopped. The current batch may have partially completed — check the products before sending again.'));
            } finally {
                running = false;
                release();
                onFinish();
            }
        }

        return {start: start, isRunning: function () { return running; }};
    };
});
