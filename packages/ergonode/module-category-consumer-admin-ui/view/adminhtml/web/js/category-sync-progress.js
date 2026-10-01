define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'text!ui/template/modal/modal-popup.html',
    'Ergonode_CategoryConsumerAdminUi/js/category-sync-progress-meter',
    'Ergonode_CategoryConsumerAdminUi/js/category-sync-progress-results'
], function ($, modal, $t, popupTemplate, createMeter, createResults) {
    'use strict';

    return function createCategorySyncProgress(controls) {
        controls = controls || {};
        var dialog = document.createElement('div');
        var running = false, pausePending = false, startedAt = 0, timer = null;
        var scope = 'all', latest = {}, updateKey = '', updatedAt = 0;
        var meter = createMeter();
        var labels = {
            starting: $t('Starting synchronization…'),
            validating: $t('Validating synchronization settings…'),
            fetching_tree_changes: $t('Checking category tree changes in Ergonode…'),
            fetching_tree: $t('Fetching the category tree from Ergonode…'),
            comparing_tree: $t('Comparing Ergonode and Magento categories…'),
            applying_tree: $t('Reconciling Magento categories…'),
            creating_category: $t('Creating a Magento category…'),
            moving_category: $t('Updating category order and product indexes…'),
            deleting_categories: $t('Removing missing categories…'),
            saving_cursors: $t('Saving synchronization progress…'),
            saving_tree_cursor: $t('Saving category tree synchronization progress…'),
            checking_data: $t('Checking category data settings…'),
            data_skipped: $t('Category data is unchanged: no updates are configured.'),
            fetching_data_changes: $t('Checking category data changes in Ergonode…'),
            fetching_category_data: $t('Fetching category data from Ergonode…'),
            updating_category_data: $t('Updating category data in Magento…'),
            saving_data_cursor: $t('Saving category data synchronization progress…')
        };

        dialog.className = 'vec-sync-progress';
        dialog.innerHTML = '<p class="vec-sync-progress-scope" data-role="sync-progress-scope"></p>'
            + '<div class="vec-sync-progress-body">'
            + '<div class="vec-sync-progress-heading">'
            + '<p class="vec-sync-progress-status" data-role="sync-progress-status" '
            + 'role="status" aria-live="polite" aria-atomic="true" tabindex="-1"></p>'
            + '<strong class="vec-sync-progress-percent" data-role="sync-progress-percent" hidden></strong></div>'
            + '<p class="vec-sync-progress-context" data-role="sync-progress-tree"></p>'
            + '<div class="vec-sync-progress-bar" data-role="sync-progress-bar" role="progressbar">'
            + '<span data-role="sync-progress-fill"></span></div>'
            + '<div class="vec-sync-progress-metrics" data-role="sync-progress-metrics">'
            + '<div><p data-role="sync-progress-count"></p><p class="vec-sync-progress-secondary" data-role="sync-progress-rate"></p></div>'
            + '<div class="vec-sync-progress-estimate"><p data-role="sync-progress-eta"></p>'
            + '<p class="vec-sync-progress-secondary" data-role="sync-progress-eta-scope"></p></div></div>'
            + '<dl class="vec-sync-progress-operations" data-role="sync-progress-operations" hidden>'
            + '<div><dt data-role="sync-progress-created-label"></dt><dd data-role="sync-progress-created">0</dd></div>'
            + '<div><dt data-role="sync-progress-moved-label"></dt><dd data-role="sync-progress-moved">0</dd></div>'
            + '<div><dt data-role="sync-progress-deleted-label"></dt><dd data-role="sync-progress-deleted">0</dd></div></dl>'
            + '<ol class="vec-sync-progress-stages" data-role="sync-progress-stages"></ol>'
            + '<p class="vec-sync-progress-data-item" data-role="sync-progress-item" hidden></p>'
            + '<p class="vec-sync-progress-secondary" data-role="sync-progress-download"></p>'
            + '<div class="vec-sync-progress-results" data-role="sync-progress-results" hidden></div></div>'
            + '<div class="vec-sync-progress-footer"><div>'
            + '<p class="vec-sync-progress-time" data-role="sync-progress-time"></p>'
            + '<p class="vec-sync-progress-secondary" data-role="sync-progress-updated"></p></div>'
            + '<div class="vec-sync-progress-actions">'
            + '<button type="button" class="veui-button veui-button-toolbar veui-button-primary" data-role="sync-progress-pause" hidden></button>'
            + '<button type="button" class="veui-button veui-button-toolbar veui-button-primary" data-role="sync-progress-resume" hidden></button>'
            + '<button type="button" class="veui-button veui-button-toolbar" data-role="sync-progress-close" hidden></button></div></div>';

        function element(role) { return dialog.querySelector('[data-role="sync-progress-' + role + '"]'); }
        function text(role, value) { element(role).textContent = value; }
        var bar = element('bar'), status = element('status');
        var pauseButton = element('pause'), resumeButton = element('resume'), closeButton = element('close');
        var results = createResults(element('results'));
        var stepNodes = [];

        text('created-label', $t('Added to Magento'));
        text('moved-label', $t('Moved in Magento'));
        text('deleted-label', $t('Removed from Magento'));
        pauseButton.textContent = $t('Pause');
        resumeButton.textContent = $t('Resume');
        closeButton.textContent = $t('Close');
        pauseButton.addEventListener('click', function () { if (controls.pause) { controls.pause(); } });
        resumeButton.addEventListener('click', function () { if (controls.resume) { controls.resume(); } });
        closeButton.addEventListener('click', close);
        bar.setAttribute('aria-label', $t('Category synchronization progress'));
        element('stages').setAttribute('aria-label', $t('Synchronization stages'));
        document.body.appendChild(dialog);
        var widget = $(dialog);
        modal({
            type: 'popup',
            popupTpl: popupTemplate.replace(/<(\/?)aside\b/g, '<$1div'),
            responsive: true,
            innerScroll: true,
            clickableOverlay: false,
            // Magento uses this target to wrap keyboard focus while busy.
            modalCloseBtn: '[data-role="sync-progress-status"]',
            modalCloseBtnHandler: function () {},
            focus: '[data-role="sync-progress-status"]',
            modalClass: 'vec-sync-progress-shell',
            title: $t('Category synchronization'),
            buttons: [],
            keyEventHandlers: {escapeKey: close}
        }, widget);

        function close() { if (!running) { widget.modal('closeModal'); } }
        function stopTimer() { window.clearInterval(timer); timer = null; }
        function isData(stage) {
            return ['checking_data', 'data_skipped', 'fetching_data_changes', 'fetching_category_data',
                'updating_category_data', 'saving_data_cursor'].indexOf(stage) !== -1;
        }

        function updateElapsed() {
            var seconds = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
            var duration = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
            text('time', $t('Elapsed time: %1').replace('%1', duration));
            var age = Math.max(0, Math.floor((Date.now() - updatedAt) / 1000));
            text('updated', !running || !updateKey ? '' : (age ?
                $t('Last progress update: %1 sec ago').replace('%1', age) : $t('Last progress update: just now')));
            renderMeter();
        }

        function renderMeter() {
            var snapshot = meter.get(Date.now());
            var paused = dialog.getAttribute('data-state') === 'paused';
            var visible = running || paused;
            bar.hidden = !visible;
            element('metrics').hidden = !visible;
            element('percent').hidden = !visible || !snapshot.known;
            bar.setAttribute('data-determinate', String(snapshot.known));
            ['aria-valuenow', 'aria-valuemin', 'aria-valuemax'].forEach(function (name) { bar.removeAttribute(name); });
            if (snapshot.known) {
                bar.setAttribute('aria-valuemin', '0');
                bar.setAttribute('aria-valuemax', String(snapshot.total));
                bar.setAttribute('aria-valuenow', String(snapshot.processed));
                element('fill').style.width = snapshot.percent + '%';
            } else {
                element('fill').style.width = '';
            }
            text('percent', snapshot.known ? snapshot.percent + '%' : '');
            text('count', snapshot.known ? $t('Processed: %1 of %2')
                .replace('%1', snapshot.processed).replace('%2', snapshot.total) : '');
            text('rate', !paused && !pausePending && snapshot.rate > 0 ? $t('Recent speed: %1 categories / sec')
                .replace('%1', Number(snapshot.rate.toFixed(1))) : '');
            var estimate = snapshot.known ? $t('Estimating remaining time…') : $t('Waiting for the stage total…');
            if (snapshot.known && snapshot.processed === snapshot.total) {
                estimate = $t('Finishing this stage…');
            } else if (snapshot.stale) {
                estimate = $t('Waiting for progress…');
            } else if (snapshot.remaining !== null) {
                var seconds = Math.max(5, Math.ceil(snapshot.remaining / 5) * 5);
                estimate = seconds < 60 ? $t('About %1 sec remaining').replace('%1', seconds)
                    : $t('About %1 min remaining').replace('%1', Math.ceil(seconds / 60));
            }
            text('eta', paused ? $t('Estimate paused') : pausePending ? $t('Waiting for a safe pause…') : estimate);
            text('eta-scope', paused ? $t('Resume to continue this stage') : $t('Estimate for this stage'));
        }

        function renderStages() {
            var stage = latest.stage || 'starting';
            var data = isData(stage);
            var index = data ? 2 : (['starting', 'validating', 'fetching_tree_changes', 'fetching_tree'].indexOf(stage) !== -1 ? 0 : 1);
            element('stages').hidden = !running && dialog.getAttribute('data-state') !== 'paused';
            stepNodes.forEach(function (step) {
                var active = step.index === index;
                step.node.setAttribute('data-state', step.index < index ? 'complete' : active ? 'active' : 'waiting');
                if (active) { step.node.setAttribute('aria-current', 'step'); }
                else { step.node.removeAttribute('aria-current'); }
                step.marker.textContent = step.index < index ? '✓' : String(step.number);
            });
        }

        function open(nextScope, force) {
            stopTimer();
            scope = nextScope;
            running = true;
            pausePending = false;
            latest = {};
            updateKey = '';
            startedAt = updatedAt = Date.now();
            meter.reset();
            results.reset();
            element('operations').hidden = scope === 'data';
            ['created', 'moved', 'deleted'].forEach(function (role) { text(role, '0'); });
            dialog.setAttribute('data-state', 'running');
            text('scope', (force ? $t('Sync (force)') : $t('Sync')) + ' · ' + $t('All active tree mappings'));
            status.textContent = scope === 'tree' ? $t('Synchronizing category trees…') :
                scope === 'data' ? $t('Synchronizing category data…') : $t('Synchronizing categories…');
            closeButton.hidden = resumeButton.hidden = true;
            pauseButton.hidden = !controls.pause;
            pauseButton.disabled = false;
            ['tree', 'download', 'item'].forEach(function (role) { text(role, ''); element(role).hidden = true; });
            element('stages').textContent = '';
            stepNodes = [];
            [$t('Download'), $t('Reconcile categories'), $t('Category data')].forEach(function (label, index) {
                if ((scope === 'data' && index < 2) || (scope === 'tree' && index === 2)) { return; }
                var step = document.createElement('li');
                var marker = document.createElement('span');
                var caption = document.createElement('span');
                marker.className = 'vec-sync-stage-marker';
                caption.textContent = label;
                step.appendChild(marker);
                step.appendChild(caption);
                element('stages').appendChild(step);
                stepNodes.push({node: step, marker: marker, index: index, number: stepNodes.length + 1});
            });
            if (scope === 'data') { latest.stage = 'checking_data'; }
            renderStages();
            updateElapsed();
            timer = window.setInterval(updateElapsed, 1000);
            widget.modal('openModal');
            status.focus();
        }

        function update(progress) {
            latest = progress;
            if (progress.operations) {
                ['created', 'moved', 'deleted'].forEach(function (role) {
                    text(role, String(Number(progress.operations[role] || 0)));
                });
            }
            var data = isData(progress.stage);
            var key = [progress.updated_at, progress.stage, progress.processed, progress.pages, progress.item].join(':');
            if (key !== updateKey) { updateKey = key; updatedAt = Date.now(); }
            if (Number(progress.started_at) > 0) { startedAt = Number(progress.started_at) * 1000; }
            if (progress.pause_requested && running) { requestPause(true); }
            else if (!pausePending && labels[progress.stage]) { status.textContent = labels[progress.stage]; }
            text('tree', data ? $t('All mapped categories') : progress.tree_code ? $t('Tree %1 of %2: %3')
                .replace('%1', progress.tree_number || 1).replace('%2', progress.tree_total || 1).replace('%3', progress.tree_code) : '');
            element('tree').hidden = !element('tree').textContent;
            text('download', !data && progress.pages ? $t('Downloaded: %1 categories · %2 pages')
                .replace('%1', progress.downloaded || 0).replace('%2', progress.pages) : '');
            element('download').hidden = !element('download').textContent;
            text('item', data && progress.item ? $t('Current category: %1').replace('%1', progress.item) : '');
            element('item').hidden = !element('item').textContent;
            if (!pausePending) { meter.update(progress, Date.now()); }
            results.update(progress, status.textContent, data, false);
            renderStages();
            updateElapsed();
        }

        function requestPause(pending, message) {
            pausePending = pending;
            pauseButton.disabled = pending;
            dialog.setAttribute('data-state', pending ? 'pausing' : 'running');
            status.textContent = pending ? $t('Pause requested. Finishing the current operation…') :
                (message || $t('Could not request a pause. Please try again.'));
            meter.reset();
            meter.update(latest, Date.now());
            renderMeter();
        }

        function finish(outcome, message, response) {
            running = false;
            pausePending = false;
            stopTimer();
            if (response) { update(response); }
            dialog.setAttribute('data-state', outcome);
            status.textContent = message;
            results.update(Object.assign({}, latest, {state: outcome}), message, isData(latest.stage), true);
            closeButton.hidden = false;
            pauseButton.hidden = true;
            resumeButton.hidden = outcome !== 'paused' || !controls.resume;
            renderStages();
            updateElapsed();
            status.focus();
        }

        function destroy() {
            running = false;
            stopTimer();
            widget.modal('option', 'transitionEvent', '');
            widget.modal('closeModal');
            widget.modal('destroy');
            widget.closest('.vec-sync-progress-shell').remove();
            dialog.remove();
        }

        return {element: dialog, open: open, finish: finish, update: update, requestPause: requestPause, destroy: destroy};
    };
});
