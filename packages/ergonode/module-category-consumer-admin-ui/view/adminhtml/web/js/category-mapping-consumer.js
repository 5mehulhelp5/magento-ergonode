define([
    'jquery', 'mage/translate',
    'Ergonode_CoreAdminUi/js/workspace',
    'Ergonode_CoreAdminUi/js/synchronization-actions',
    'Ergonode_CategoryConsumerAdminUi/js/category-synchronization-availability',
    'Ergonode_CategoryConsumerAdminUi/js/category-sync-progress',
    'Ergonode_CategoryConsumerAdminUi/js/category-sync-run'
], function ($, $t, workspace, synchronizationActions, synchronizationAvailability,
    createSyncProgress, createSyncRun) {
    'use strict';

    return function (initialize) {
        return function (config, element) {
            var $root = $(element);
            var result = initialize(config, element);
            var api = element.veaCategoryMappingApi;
            var scope = workspace.mount(element);
            if (!api || !scope.claim(element, 'category-consumer')) {
                return result;
            }
            var categoryTreeId = api.getCategoryTreeId();
            var $resetCursorButton = $root.find('[data-synchronization-action="reset-cursor"][data-synchronization-scope="tree"]');
            var syncProgress = null;
            var syncRun = null;
            var syncButton = null;
            var synchronizationPending = false;
            var post = api.post;
            var setBusy = api.setBusy;
            var showMessage = api.notify;
            var applyCategoryTreeConfig = api.applyConfig;
            synchronizationAvailability.bind(scope, element);
            synchronizationAvailability.update(element, config.synchronization_blockers);
            synchronizationActions.bind(scope, element);
            updateResetCursorState(config.has_sync_cursor === true);
            $root.on('ergonode:category-mapping:config.categoryConsumer', function (event, next) {
                categoryTreeId = Number(next.category_tree_id || 0);
                config.synchronization_blockers = next.synchronization_blockers || {};
                synchronizationAvailability.update(element, config.synchronization_blockers);
                updateResetCursorState(next.has_sync_cursor === true);
            });
            $root.on('click.categoryConsumer', '[data-role="sync-category-trees"]', function (event) {
                event.preventDefault(); synchronizeCategoryTrees($(this), false);
            });
            $root.on('click.categoryConsumer', '[data-role="reset-sync-cursor-and-sync"]', function (event) {
                event.preventDefault(); synchronizeCategoryTrees($(this), true);
            });
            $root.on('click.categoryConsumer', '[data-role="reset-sync-cursor"]', function (event) {
                event.preventDefault(); resetCategoryTreeSyncCursor($(this));
            });
            scope.cleanup(function () {
                if (syncRun) { syncRun.destroy(); }
                if (syncProgress) { syncProgress.destroy(); }
                api.setOperationPending(false);
                $root.off('.categoryConsumer');
            });
            return result;

            function synchronizeCategoryTrees($button, resetCursor) {
                var synchronizationScope = String($button.attr('data-synchronization-scope') || 'all');

                if (synchronizationPending || $button.prop('disabled') || $button.attr('aria-disabled') === 'true') {
                    return;
                }
                if (api.isDirty() && !window.confirm($t('You have unsaved changes. Discard them and continue?'))) {
                    return;
                }
                if (resetCursor && !window.confirm($t(
                    'Run Sync (force) for the selected data on all active trees?'
                ))) {
                    return;
                }

                syncButton = $button;
                if (!syncRun) {
                    syncProgress = createSyncProgress({
                        pause: function () { syncRun.pause(); },
                        resume: function () { syncRun.resume(); }
                    });
                    syncRun = createSyncRun({
                        request: post,
                        urls: config.urls,
                        onStart: function (scope, force) {
                            synchronizationPending = true;
                            api.setOperationPending(true);
                            setBusy(syncButton, true);
                            syncProgress.open(scope, force);
                        },
                        onUpdate: function (response) { syncProgress.update(response); },
                        onPauseRequest: function (pending, message) { syncProgress.requestPause(pending, message); },
                        onFinish: function (response) {
                            var outcome = response.state;
                            var message = response.message || $t('Could not update categories.');

                            if (response.config) {
                                if (response.config.status && response.config.status.error) {
                                    outcome = 'error';
                                    message = response.config.status.error;
                                } else {
                                    applyCategoryTreeConfig(response.config);
                                    $root.trigger('ergonode:category-mapping:refreshed');
                                }
                            }
                            synchronizationPending = false;
                            api.setOperationPending(false);
                            setBusy(syncButton, false);
                            finishSynchronization(outcome, message, response);
                        }
                    });
                }
                syncRun.start(synchronizationScope, resetCursor, categoryTreeId);
            }

            function finishSynchronization(outcome, message, response) {
                if (syncProgress) {
                    syncProgress.finish(outcome, message, response);
                }
                showMessage(outcome === 'success' || outcome === 'paused' ? 'success' : 'error', message);
            }

            function resetCategoryTreeSyncCursor($button) {
                if (!window.confirm($t(
                    'Reset the cursor for the selected data? The next Sync will start from the beginning. This applies to all active trees.'
                ))) {
                    return;
                }

                setBusy($button, true);
                post(config.urls.sync_cursor_reset, {
                    synchronization_scope: String($button.attr('data-synchronization-scope') || 'tree')
                })
                    .done(function (response) {
                        if (!response || response.success !== true) {
                            showMessage('error', response && response.message
                                ? response.message
                                : $t('Could not reset the synchronization cursor.'));
                            return;
                        }

                        if ($button.attr('data-synchronization-scope') === 'tree') {
                            updateResetCursorState(false);
                        }
                        showMessage('success', response.message || $t('Cursor reset. The next Sync will start from the beginning.'));
                    })
                    .fail(function () {
                        showMessage('error', $t('Could not reset the synchronization cursor.'));
                    })
                    .always(function () {
                        setBusy($button, false);
                    });
            }

            function updateResetCursorState(available) {
                $resetCursorButton.prop('disabled', !available);
            }

        };
    };
});
