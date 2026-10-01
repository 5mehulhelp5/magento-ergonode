define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, modal, $t) {
    'use strict';

    function option(options, name, fallback) {
        return options && options[name] ? String(options[name]) : fallback;
    }

    return function createBulkPublishProgress(options) {
        var dialog = document.createElement('div');
        var widget;
        var actions;
        var closeButton;
        var active = false;
        var destroyed = false;
        var waits = new Set();
        var closeAfterStop = false;
        var total = 0;
        var completed = 0;
        var successful = 0;
        var warnings = 0;
        var failed = 0;
        var startedAt = 0;
        var closeHandler = null;
        var controls = null;
        var pauseState = 'running';
        var stopping = false;
        var pausedAt = 0;
        var statusMessage = '';
        var labels = {
            title: option(options, 'title', $t('Tworzenie elementów w Ergonode')),
            progressLabel: option(options, 'progressLabel', $t('Postęp tworzenia elementów')),
            unit: option(options, 'unit', $t('elementów')),
            currentBatch: option(options, 'currentBatch', $t('Bieżąca paczka')),
            processingBatch: option(
                options,
                'processingBatch',
                $t('Przetwarzam paczkę %1 z %2 (%3 elementów).')
            ),
            finalizing: option(
                options,
                'finalizing',
                $t('Zapisuję przygotowane mapowania w Magento…')
            ),
            complete: option(
                options,
                'complete',
                $t('Wszystkie elementy zostały utworzone i zapisane.')
            ),
            partial: option(
                options,
                'partial',
                $t('Proces zakończony. Poprawne elementy zapisano, a błędy pozostawiono do ponowienia.')
            )
        };

        dialog.className = 'veui-bulk-publish-progress';
        dialog.style.display = 'none';
        dialog.innerHTML = '<div class="veui-publish-progress-summary">'
            + '<div><strong data-role="publish-progress-title"></strong>'
            + '<span data-role="publish-progress-count"></span></div>'
            + '<span class="veui-publish-progress-percent" data-role="publish-progress-percent">0%</span></div>'
            + '<progress data-role="publish-progress-bar" value="0" max="1" '
            + 'aria-label="' + labels.progressLabel + '"></progress>'
            + '<div class="veui-publish-progress-metrics">'
            + '<span><strong data-role="publish-success-count">0</strong> ' + $t('poprawnych') + '</span>'
            + '<span><strong data-role="publish-warning-count">0</strong> ' + $t('ostrzeżeń') + '</span>'
            + '<span><strong data-role="publish-error-count">0</strong> ' + $t('błędów') + '</span>'
            + '<span><strong data-role="publish-remaining-count">0</strong> ' + $t('pozostało') + '</span>'
            + '</div>'
            + '<div class="veui-publish-progress-status" data-role="publish-progress-status" '
            + 'role="status" aria-live="polite"></div>'
            + '<div class="veui-publish-progress-eta" data-role="publish-progress-eta"></div>'
            + '<section class="veui-publish-progress-batch" aria-labelledby="veui-publish-batch-title">'
            + '<h3 id="veui-publish-batch-title">' + labels.currentBatch + '</h3>'
            + '<ol data-role="publish-current-items" tabindex="0"></ol></section>'
            + '<section class="veui-publish-progress-errors" data-role="publish-errors" '
            + 'aria-labelledby="veui-publish-errors-title" hidden>'
            + '<h3 id="veui-publish-errors-title">' + $t('Błędy wymagające uwagi') + '</h3>'
            + '<ol data-role="publish-error-items" tabindex="0"></ol></section>'
            + '<section class="veui-publish-progress-warnings" data-role="publish-warnings" '
            + 'aria-labelledby="veui-publish-warnings-title" hidden>'
            + '<h3 id="veui-publish-warnings-title">' + $t('Ostrzeżenia') + '</h3>'
            + '<ol data-role="publish-warning-items" tabindex="0"></ol></section>'
            + '<div class="veui-publish-progress-actions">'
            + '<button type="button" class="veui-button" data-role="publish-progress-pause" hidden>'
            + $t('Wstrzymaj') + '</button>'
            + '<button type="button" class="veui-button" data-role="publish-progress-stop" hidden>'
            + $t('Zatrzymaj') + '</button>'
            + '<button type="button" class="veui-button" data-role="publish-progress-close" hidden>'
            + $t('Zamknij') + '</button></div>';
        document.body.appendChild(dialog);
        widget = $(dialog);
        modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            clickableOverlay: false,
            modalCloseBtnHandler: requestClose,
            title: labels.title,
            buttons: [],
            keyEventHandlers: {escapeKey: requestClose},
            modalClass: 'veui-bulk-publish-progress-shell'
        }, widget);
        actions = dialog.querySelector('.veui-publish-progress-actions');
        var shell = dialog.closest('.modal-popup');
        var footer = document.createElement('footer');

        footer.className = 'modal-footer';
        footer.appendChild(actions);
        shell.querySelector('.modal-inner-wrap').appendChild(footer);
        closeButton = shell.querySelector('[data-role="closeBtn"]');
        actions.querySelector('[data-role="publish-progress-pause"]').addEventListener('click', function () {
            if (!controls || stopping || pauseState === 'pausing') {
                return;
            }
            if (pauseState === 'paused') {
                setPauseState('running');
                controls.resume();
            } else {
                setPauseState('pausing');
                controls.pause();
            }
        });
        actions.querySelector('[data-role="publish-progress-stop"]').addEventListener('click', requestStop);
        actions.querySelector('[data-role="publish-progress-close"]').addEventListener('click', requestClose);

        function requestStop() {
            if (!controls || typeof controls.stop !== 'function' || stopping) {
                return;
            }
            stopping = true;
            setPauseState('running');
            controls.stop();
        }

        function requestClose() {
            if (destroyed) {
                return;
            }
            if (active) {
                if (controls && typeof controls.stop === 'function') {
                    closeAfterStop = true;
                    requestStop();
                    renderStatus();
                }
                return;
            }
            widget.modal('closeModal');
            var handler = closeHandler;

            closeHandler = null;
            if (typeof handler === 'function') {
                handler();
            }
        }

        function open(itemCount, processControls) {
            if (destroyed) {
                return;
            }
            active = true;
            closeAfterStop = false;
            total = Math.max(0, Number(itemCount || 0));
            completed = 0;
            successful = 0;
            warnings = 0;
            failed = 0;
            startedAt = Date.now();
            closeHandler = null;
            controls = processControls || null;
            stopping = false;
            pauseState = 'running';
            pausedAt = 0;
            setPauseState('running');
            dialog.querySelector('[data-role="publish-current-items"]').replaceChildren();
            dialog.querySelector('[data-role="publish-error-items"]').replaceChildren();
            dialog.querySelector('[data-role="publish-errors"]').hidden = true;
            dialog.querySelector('[data-role="publish-warning-items"]').replaceChildren();
            dialog.querySelector('[data-role="publish-warnings"]').hidden = true;
            actions.querySelector('[data-role="publish-progress-close"]').hidden = true;
            setStatus($t('Przygotowuję operacje…'));
            render();
            widget.modal('openModal');
        }

        function showBatch(batchIndex, batchCount, items) {
            if (destroyed) {
                return;
            }
            var list = dialog.querySelector('[data-role="publish-current-items"]');

            list.replaceChildren();
            (items || []).forEach(function (item) {
                var entry = document.createElement('li');
                var label = String(item.label || item.code || '');
                var code = String(item.code || '');

                entry.textContent = label && label !== code ? label + ' · ' + code : code;
                list.appendChild(entry);
            });
            setStatus(labels.processingBatch
                .replace('%1', String(batchIndex))
                .replace('%2', String(batchCount))
                .replace('%3', String((items || []).length)));
        }

        function applyBatch(results) {
            if (destroyed) {
                return;
            }
            (results || []).forEach(function (item) {
                completed++;
                if (item.status === 'failed' || item.status === 'blocked' || item.status === 'skipped') {
                    failed++;
                    addError(item);
                } else if (item.status === 'existing' || item.status === 'warning') {
                    warnings++;
                    addWarning(item);
                } else {
                    successful++;
                }
            });
            render();
        }

        function addBlocked(item) {
            if (destroyed) {
                return;
            }
            completed++;
            failed++;
            addError(item);
            render();
        }

        function wait(retryAfter, message) {
            var seconds = Math.max(1, Number(retryAfter || 1));

            if (destroyed) {
                return Promise.reject(new Error('Publication view has been disposed.'));
            }
            return new Promise(function (resolve, reject) {
                var timer;
                var cancel = function () {
                    window.clearTimeout(timer);
                    reject(new Error('Publication view has been disposed.'));
                };

                waits.add(cancel);
                function tick() {
                    setStatus(
                        (message || $t('Ergonode ograniczyło liczbę zapytań.')) + ' '
                        + $t('Ponawiam za %1 s.').replace('%1', String(seconds))
                    );
                    if (pauseState === 'running') {
                        dialog.querySelector('[data-role="publish-progress-eta"]').textContent =
                            $t('Oczekiwanie na ponowne udostępnienie API: %1 s').replace('%1', String(seconds));
                    }
                    if (seconds <= 0) {
                        render();
                        waits.delete(cancel);
                        resolve();
                        return;
                    }
                    seconds--;
                    timer = window.setTimeout(tick, 1000);
                }

                tick();
            });
        }

        function finalizing() {
            if (destroyed) {
                return;
            }
            controls = null;
            setPauseState('running');
            dialog.querySelector('[data-role="publish-current-items"]').replaceChildren();
            setStatus(labels.finalizing);
            renderEta();
        }

        function complete(partial, onClose) {
            if (destroyed) {
                return;
            }
            active = false;
            controls = null;
            setPauseState('running');
            closeHandler = typeof onClose === 'function' ? onClose : null;
            setStatus(partial ? labels.partial : labels.complete);
            actions.querySelector('[data-role="publish-progress-close"]').hidden = false;
            render();
            if (closeAfterStop) {
                requestClose();
            }
        }

        function fail(message, onClose) {
            if (destroyed) {
                return;
            }
            active = false;
            closeAfterStop = false;
            controls = null;
            setPauseState('running');
            closeHandler = typeof onClose === 'function' ? onClose : null;
            addError({
                code: '',
                label: $t('Błąd procesu'),
                message: message || $t('Nie udało się zakończyć procesu.'),
                status: 'failed'
            });
            setStatus($t('Proces został zatrzymany.'));
            actions.querySelector('[data-role="publish-progress-close"]').hidden = false;
            render();
        }

        function stopped(onClose) {
            if (destroyed) {
                return;
            }
            complete(true, onClose);
            setStatus($t('Proces zatrzymany. Zakończone operacje pozostają zapisane; pozostałych nie wykonano.'));
            dialog.querySelector('[data-role="publish-progress-eta"]').textContent = '';
        }

        function addError(item) {
            var list = dialog.querySelector('[data-role="publish-error-items"]');
            var entry = document.createElement('li');
            var title = document.createElement('strong');
            var message = document.createElement('span');

            title.textContent = String(item.label || item.code || $t('Nieznany element'));
            if (item.code) {
                title.textContent += ' · ' + String(item.code);
            }
            message.textContent = String(item.message || $t('Nieznany błąd.'));
            entry.append(title, message);
            list.appendChild(entry);
            dialog.querySelector('[data-role="publish-errors"]').hidden = false;
        }

        function addWarning(item) {
            var list = dialog.querySelector('[data-role="publish-warning-items"]');
            var entry = document.createElement('li');
            var title = document.createElement('strong');
            var message = document.createElement('span');

            title.textContent = String(item.label || item.code || $t('Nieznany element'));
            if (item.code) {
                title.textContent += ' · ' + String(item.code);
            }
            message.textContent = String(item.message || $t('Element wymaga uwagi.'));
            entry.append(title, message);
            list.appendChild(entry);
            dialog.querySelector('[data-role="publish-warnings"]').hidden = false;
        }

        function setStatus(message) {
            statusMessage = String(message || '');
            renderStatus();
        }

        function setPauseState(state) {
            if (destroyed) {
                return;
            }
            var button = actions.querySelector('[data-role="publish-progress-pause"]');
            var stop = actions.querySelector('[data-role="publish-progress-stop"]');

            if (pauseState === 'paused' && state !== 'paused') {
                startedAt += Date.now() - pausedAt;
            }
            if (state === 'paused' && pauseState !== 'paused') {
                pausedAt = Date.now();
            }
            pauseState = state;
            if (closeButton) {
                closeButton.disabled = active && (!controls || typeof controls.stop !== 'function');
                closeButton.title = active ? $t('Zatrzymaj wysyłkę i zamknij po zakończeniu bieżącej paczki') : $t('Zamknij okno');
                closeButton.setAttribute('aria-label', closeButton.title);
            }
            button.hidden = !controls || typeof controls.pause !== 'function';
            button.disabled = stopping;

            stop.hidden = !controls || typeof controls.stop !== 'function';
            stop.disabled = stopping;
            button.setAttribute('aria-disabled', state === 'pausing' ? 'true' : 'false');
            button.textContent = state === 'paused' ? $t('Wznów') : $t('Wstrzymaj');
            renderStatus();
            renderEta();
        }

        function renderStatus() {
            var message = statusMessage;

            if (stopping && controls) {
                message = closeAfterStop ? $t('Kończę bieżącą paczkę, następnie zatrzymam wysyłkę i zamknę okno…') :
                    $t('Zatrzymuję proces po zakończeniu bieżącej paczki…');
            } else if (pauseState === 'pausing') {
                message = $t('Wstrzymuję proces po zakończeniu bieżącej operacji…');
            } else if (pauseState === 'paused') {
                message = $t('Proces wstrzymany. Kliknij „Wznów”, aby kontynuować.');
            }
            dialog.querySelector('[data-role="publish-progress-status"]').textContent = message;
        }

        function render() {
            var percent = total > 0 ? Math.min(100, Math.round((completed / total) * 100)) : 0;
            var bar = dialog.querySelector('[data-role="publish-progress-bar"]');

            bar.max = Math.max(1, total);
            bar.value = Math.min(completed, total);
            dialog.querySelector('[data-role="publish-progress-title"]').textContent =
                $t('Przetworzono %1 z %2').replace('%1', String(completed)).replace('%2', String(total));
            dialog.querySelector('[data-role="publish-progress-count"]').textContent = ' ' + labels.unit;
            dialog.querySelector('[data-role="publish-progress-percent"]').textContent = percent + '%';
            dialog.querySelector('[data-role="publish-success-count"]').textContent = String(successful);
            dialog.querySelector('[data-role="publish-warning-count"]').textContent = String(warnings);
            dialog.querySelector('[data-role="publish-error-count"]').textContent = String(failed);
            dialog.querySelector('[data-role="publish-remaining-count"]').textContent =
                String(Math.max(0, total - completed));
            renderEta();
        }

        function renderEta() {
            var elapsed;
            var remainingMs;

            if (pauseState !== 'running') {
                dialog.querySelector('[data-role="publish-progress-eta"]').textContent =
                    $t('Pozostaw tę kartę otwartą, aby zachować postęp procesu.');
                return;
            }
            if (completed <= 0 || completed >= total) {
                dialog.querySelector('[data-role="publish-progress-eta"]').textContent = completed >= total && total > 0
                    ? $t('Przetwarzanie paczek zakończone.')
                    : $t('Szacowany czas pojawi się po pierwszej paczce.');
                return;
            }
            elapsed = Math.max(1, Date.now() - startedAt);
            remainingMs = (elapsed / completed) * (total - completed);
            dialog.querySelector('[data-role="publish-progress-eta"]').textContent =
                $t('Szacowany czas do końca: %1').replace('%1', formatDuration(remainingMs));
        }

        function formatDuration(milliseconds) {
            var seconds = Math.max(1, Math.ceil(milliseconds / 1000));

            if (seconds < 60) {
                return $t('około %1 s').replace('%1', String(seconds));
            }

            return $t('około %1 min').replace('%1', String(Math.ceil(seconds / 60)));
        }

        function destroy() {
            if (destroyed) {
                return;
            }
            destroyed = true;
            controls = null;
            closeHandler = null;
            waits.forEach(function (cancel) { cancel(); });
            waits.clear();
            if (widget.modal('option', 'isOpen') === true) {
                widget.modal('option', 'transitionEvent', null);
                widget.modal('closeModal');
            }
            widget.modal('destroy');
            $(shell).remove();
        }

        return {
            destroy: destroy,
            element: dialog,
            open: open,
            showBatch: showBatch,
            applyBatch: applyBatch,
            addBlocked: addBlocked,
            wait: wait,
            setPauseState: setPauseState,
            finalizing: finalizing,
            complete: complete,
            fail: fail,
            stopped: stopped
        };
    };
});
