define([
    'mage/translate',
    'Ergonode_CoreAdminUi/js/request'
], function ($t, request) {
    'use strict';

    return function (config, element) {
        var message = element.querySelector('[data-role="synchronization-message"]');
        var messageText = element.querySelector('[data-role="synchronization-message-text"]');
        var processCheckboxes = Array.prototype.slice.call(
            element.querySelectorAll('[data-role="process-select"]:not(:disabled)')
        );
        var resetButton = element.querySelector('[data-role="reset-selected"]');
        var runButton = element.querySelector('[data-role="run-selected"]');
        var selectAll = element.querySelector('[data-role="select-all-processes"]');
        var busy = false;
        var freshness = element.querySelector('[data-role="monitor-freshness"]');
        var refreshTimer;
        var lastObserved = null;
        var polling = false;

        function date(value) {
            return value ? new Date(value.replace(' ', 'T') + (/Z|[+]\d\d:\d\d$/.test(value) ? '' : 'Z')) : null;
        }

        function formattedDate(value) {
            return value ? date(value).toLocaleString() : $t('No recorded data');
        }

        function renderObservation(row, observation) {
            var statuses = {
                not_checked: $t('No recorded check'),
                running: $t('Started — completion not yet recorded'),
                changes_detected: $t('Change detected — refresh in progress'),
                initialized: $t('Initial snapshot loaded'),
                no_changes: $t('No changes'),
                changed: $t('Changes detected and applied'),
                failed: $t('Failed')
            };
            var status = row.querySelector('[data-role="check-status"]');

            observation = observation || {};
            status.textContent = statuses[observation.status] || statuses.not_checked;
            status.classList.toggle('is-error', observation.status === 'failed');
            ['started', 'completed', 'changed'].forEach(function (field) {
                row.querySelector('[data-role="check-' + field + '-at"]').textContent =
                    formattedDate(observation[field + '_at']);
            });
        }

        function renderProcess(process) {
            var row = Array.prototype.find.call(element.querySelectorAll('[data-process-code]'), function (candidate) {
                return candidate.getAttribute('data-process-code') === process.process_code;
            });

            if (!row) {
                return;
            }
            if (row.querySelector('[data-role="observation"]')) {
                renderObservation(row, process.observation);
                return;
            }
            row.querySelector('[data-role="process-cursor"]').textContent = process.cursor || $t('No checkpoint');
            row.querySelector('[data-role="synced-at"]').textContent = formattedDate(process.synced_at);
            row.querySelector('[data-role="reset-at"]').textContent = formattedDate(process.reset_at);
        }

        function refresh() {
            if (!config.urls || !config.urls.status || polling || !element.isConnected) {
                return Promise.resolve();
            }
            window.clearTimeout(refreshTimer);
            polling = true;
            return request.post(config.urls.status, config, {}).then(function (response) {
                if (!Array.isArray(response.processes) || !response.observed_at) {
                    throw new Error($t('Invalid synchronization status response.'));
                }
                response.processes.forEach(function (process) { renderProcess(process); });
                lastObserved = response.observed_at;
                if (freshness) {
                    freshness.textContent = $t('Live updates every 2 seconds. Last read: ') + formattedDate(lastObserved);
                    freshness.classList.remove('is-error');
                }
            }).catch(function (error) {
                if (freshness) {
                    freshness.textContent = $t('Live updates unavailable. Last read: ') + formattedDate(lastObserved) + ' — ' + error.message;
                    freshness.classList.add('is-error');
                }
            }).finally(function () {
                polling = false;
                if (element.isConnected) {
                    refreshTimer = window.setTimeout(refresh, 2000);
                }
            });
        }

        function selectedProcessCodes() {
            return processCheckboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).map(function (checkbox) {
                return checkbox.value;
            });
        }

        function synchronizeControls() {
            var selectedCount = selectedProcessCodes().length;

            if (selectAll) {
                selectAll.checked = processCheckboxes.length > 0 && selectedCount === processCheckboxes.length;
                selectAll.indeterminate = selectedCount > 0 && selectedCount < processCheckboxes.length;
                selectAll.disabled = busy || processCheckboxes.length === 0;
            }
            [resetButton, runButton].forEach(function (button) {
                if (button) {
                    button.disabled = busy || selectedCount === 0;
                    button.setAttribute('aria-busy', busy ? 'true' : 'false');
                }
            });
            processCheckboxes.forEach(function (checkbox) {
                checkbox.disabled = busy;
            });
        }

        function showError(error) {
            if (!message || !messageText) {
                return;
            }
            message.classList.remove('veui-message-success');
            message.classList.add('veui-message-error');
            messageText.textContent = error && error.message
                ? error.message
                : $t('Some selected synchronization operations failed.');
            message.hidden = false;
        }

        function executeSelected(operation) {
            var failures = [];
            var processCodes = selectedProcessCodes();
            var url = config.urls && config.urls[operation];
            var sequence = Promise.resolve();

            if (processCodes.length === 0 || busy) {
                return;
            }
            if (operation === 'reset' && !window.confirm($t(
                'Reset cursors for the selected synchronization processes?'
            ))) {
                return;
            }

            busy = true;
            if (message) {
                message.hidden = true;
            }
            synchronizeControls();
            processCodes.forEach(function (processCode) {
                sequence = sequence.then(function () {
                    return request.post(url, config, {process_code: processCode}).catch(function (error) {
                        failures.push(error);
                    });
                });
            });
            sequence.then(function () {
                if (failures.length > 0) {
                    busy = false;
                    synchronizeControls();
                    showError(failures[0]);
                    refresh();
                    return;
                }
                busy = false;
                synchronizeControls();
                refresh();
            });
        }

        processCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', synchronizeControls);
        });
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                processCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAll.checked;
                });
                synchronizeControls();
            });
        }
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                executeSelected('reset');
            });
        }
        if (runButton) {
            runButton.addEventListener('click', function () {
                executeSelected('run');
            });
        }

        synchronizeControls();
        window.setTimeout(refresh, 0);
    };
});
