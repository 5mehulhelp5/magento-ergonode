define(['Ergonode_CoreAdminUi/js/bulk-publish-progress', 'mage/translate'], function (createProgress, $t) {
    'use strict';

    var instance = 0;

    return function (options) {
        var progress = createProgress(options);
        progress.element.closest('.modal-popup').classList.add('vepp-progress-modal');
        // Each direction has its own dialog; keep heading references unique.
        var suffix = '-product-' + (++instance);
        progress.element.querySelectorAll('[id]').forEach(function (heading) {
            var previous = heading.id;
            heading.id += suffix;
            progress.element.querySelectorAll('[aria-labelledby]').forEach(function (section) {
                if (section.getAttribute('aria-labelledby') === previous) {
                    section.setAttribute('aria-labelledby', heading.id);
                }
            });
        });
        var current = progress.element.querySelector('[data-role="publish-current-items"]');
        var history = document.createElement('div');
        var entries = new Map();
        var batchLabel = '';
        var labels = {
            processing: $t('Processing'), success: $t('Completed'), failed: $t('Failed'),
            warning: $t('Needs attention'), unconfirmed: $t('Unconfirmed result')
        };
        Object.assign(labels, options && options.statusLabels);
        history.className = 'vepp-progress-history';
        history.setAttribute('aria-label', $t('Previous batch results'));
        history.hidden = true;
        current.closest('section').after(history);

        function text(tag, value, className) {
            var node = document.createElement(tag);
            node.textContent = value;
            node.className = className || '';
            return node;
        }

        function icon(status) {
            if (status === 'processing') {
                var spinner = text('span', '', 'vepp-spinner');
                spinner.setAttribute('aria-hidden', 'true');
                return spinner;
            }
            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('aria-hidden', 'true');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', 'currentColor');
            svg.setAttribute('stroke-width', '1.8');
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', status === 'success' ? 'M9 12l2 2 4-4M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0' :
                'M12 3 2 21h20L12 3zm0 6v5m0 3v1');
            svg.append(path);
            return svg;
        }

        function render(entry, item, status) {
            var code = String(item.code || entry.item.code || entry.item.product_id);
            var name = String(item.label || entry.item.label || code);
            var label = name === code ? code : code + ' · ' + name;
            var message = item.message || (status === 'success' ? $t('The operation completed successfully.') :
                (status === 'processing' ? $t('Waiting for the current batch result.') : $t('The operation result has not been confirmed.')));
            entry.node.className = 'vepp-progress-product is-' + status;
            entry.node.dataset.publicationStatus = status;
            entry.summary.replaceChildren(icon(status), text('span', label, 'vepp-progress-product-label'),
                text('span', labels[status], 'vepp-progress-product-status'));
            entry.message.textContent = message;
        }

        return Object.assign({}, progress, {
            open: function (total, controls) {
                history.replaceChildren(); history.hidden = true; entries.clear(); batchLabel = '';
                progress.open(total, controls);
            },
            showBatch: function (index, count, items) {
                if (current.children.length) {
                    var batch = document.createElement('details');
                    var list = document.createElement('ol');
                    list.append(...current.children);
                    batch.append(text('summary', batchLabel), list);
                    history.append(batch); history.hidden = false;
                }
                batchLabel = $t('Batch %1 of %2 (%3 products)').replace('%1', String(index)).replace('%2', String(count)).replace('%3', String(items.length));
                progress.showBatch(index, count, items);
                current.replaceChildren(); entries.clear();
                items.forEach(function (item) {
                    var node = document.createElement('li');
                    var details = document.createElement('details');
                    var summary = document.createElement('summary');
                    var message = text('p', '', 'vepp-progress-product-message');
                    var entry = {node: node, summary: summary, message: message, item: item};
                    node.dataset.productId = item.product_id;
                    details.append(summary, message); node.append(details); current.append(node);
                    entries.set(item.product_id, entry);
                    render(entry, item, 'processing');
                });
            },
            applyBatch: function (items) {
                progress.applyBatch(items.map(function (item) {
                    return ['success', 'failed', 'warning'].includes(item.status) ? item :
                        Object.assign({}, item, {status: 'warning'});
                }));
                items.forEach(function (item) {
                    var entry = entries.get(item.product_id);
                    if (entry) { render(entry, item, labels[item.status] ? item.status : 'unconfirmed'); }
                });
            },
            fail: function (message) {
                entries.forEach(function (entry) {
                    if (entry.node.dataset.publicationStatus === 'processing') {
                        render(entry, {message: message}, 'unconfirmed');
                    }
                });
                progress.fail(message);
            }
        });
    };
});
