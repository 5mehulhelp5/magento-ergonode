define([
    'jquery',
    'mage/translate',
    'text!Ergonode_MediaAdminUi/template/media-scan.html'
], function ($, $t, template) {
    'use strict';

    return function (config, element) {
        element.innerHTML = template;
        const find = role => element.querySelector('[data-role="' + role + '"]');
        const text = (role, value) => { find(role).textContent = value; };
        const labels = {
            required: $t('Full scan required'),
            pending: $t('Waiting for the background worker'),
            running: $t('Scanning local media'),
            complete: $t('Scan completed'),
            failed: $t('Scan failed')
        };
        let timer;
        let busy = false;
        let active = false;
        let actionError = '';
        const staticLabels = {
            'Local media index': $t('Local media index'),
            'The scan runs in the background. You can leave this page.':
                $t('The scan runs in the background. You can leave this page.'),
            'Refresh status': $t('Refresh status'),
            'Error details': $t('Error details')
        };
        element.querySelectorAll('[data-label]').forEach(node => {
            node.textContent = staticLabels[node.dataset.label];
        });
        find('progress').setAttribute('aria-label', $t('Estimated scan progress'));
        text('status', $t('Loading scan status'));
        text('start', $t('Scan media'));

        function fail(message) {
            find('errors').hidden = false;
            find('errors').open = true;
            text('error', message);
        }

        function render(scan) {
            active = ['pending', 'running'].includes(scan.status);
            text('status', labels[scan.status] || scan.status);
            text('blocking', scan.blocked
                ? $t('Shared media synchronization is waiting for the first successful full scan.')
                : $t('The scan requirement does not block media synchronization.'));
            text('counts', $t('Checked: %1 files. Estimated scope from the database: about %2 paths.')
                .replace('%1', scan.processed).replace('%2', scan.estimated_total));
            if (scan.status === 'complete') {
                text('counts', $t('Checked: %1 files. Missing index entries removed: %2.')
                    .replace('%1', scan.processed).replace('%2', scan.removed));
            }
            const progress = find('progress');
            progress.hidden = !active && scan.status !== 'complete';
            if (scan.percent === null) {
                progress.removeAttribute('value');
            } else {
                progress.value = scan.percent;
            }
            const remaining = scan.estimated_remaining_seconds;
            text('time', remaining === null
                ? (active ? $t('Remaining time is being estimated. The scope may change during the scan.') : '')
                : $t('Approximately %1 min remaining; elapsed: %2 s.')
                    .replace('%1', Math.max(1, Math.ceil(remaining / 60))).replace('%2', scan.elapsed_seconds));
            text('last-success', scan.last_completed_at === null
                ? $t('No successful full scan yet.')
                : $t('Last successful full scan: %1')
                    .replace('%1', new Date(scan.last_completed_at * 1000).toLocaleString()));
            text('start', scan.last_completed_at === null ? $t('Scan media') : $t('Refresh index'));
            find('start').disabled = active;
            find('errors').hidden = !scan.error && !actionError;
            text('error', actionError || scan.error || '');
        }

        function poll() {
            clearTimeout(timer);
            if (busy) {
                return;
            }
            busy = true;
            find('refresh').disabled = true;
            $.ajax({url: config.statusUrl, type: 'GET', dataType: 'json', cache: false})
                .done(response => {
                    if (!response || response.success !== true || !response.scan) {
                        fail($t('Unable to read scan status. Refresh the status or sign in again.'));
                        return;
                    }
                    render(response.scan);
                })
                .fail(() => fail($t('Unable to read scan status. Refresh the status or sign in again.')))
                .always(() => {
                    busy = false;
                    find('refresh').disabled = false;
                    if (active) {
                        timer = setTimeout(() => {
                            if (element.isConnected) {
                                poll();
                            }
                        }, 3000);
                    }
                });
        }

        find('refresh').addEventListener('click', poll);
        find('start').addEventListener('click', () => {
            if (busy || active) {
                return;
            }
            clearTimeout(timer);
            busy = true;
            find('start').disabled = true;
            actionError = '';
            $.ajax({
                url: config.startUrl, type: 'POST', dataType: 'json',
                data: {form_key: window.FORM_KEY}
            }).done(response => {
                if (!response || response.success !== true) {
                    actionError = response && response.message ? response.message : $t('Unable to request a scan.');
                    fail(actionError);
                }
            }).fail(() => {
                actionError = $t('Unable to request a scan. Refresh the status before trying again.');
                fail(actionError);
            })
                .always(() => {
                    busy = false;
                    find('start').disabled = active;
                    poll();
                });
        });
        poll();
    };
});
