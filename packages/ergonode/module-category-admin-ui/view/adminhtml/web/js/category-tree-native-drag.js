define(['jquery'], function ($) {
    'use strict';

    var sourceSelector = '[draggable="true"][data-drag-type]';

    return {
        enableSource: function ($source) {
            return $source.attr('draggable', 'true');
        },

        bind: function ($root, onDrop, scope) {
            var activePayload = null;

            $root.on('dragstart.ergonodeCategoryDrag', sourceSelector, function (event) {
                var dataTransfer = event.originalEvent.dataTransfer;

                activePayload = buildPayload($(this));
                $(this).addClass('is-dragging');
                dataTransfer.setData('application/json', JSON.stringify(activePayload));
                dataTransfer.setData('text/plain', JSON.stringify(activePayload));
                dataTransfer.effectAllowed = 'move';
            });

            $root.on('dragend.ergonodeCategoryDrag', sourceSelector, function () {
                $(this).removeClass('is-dragging');
                $root.find('[data-drop-zone]').removeClass('is-over');
                activePayload = null;
            });

            $root.on('dragover.ergonodeCategoryDrag', '[data-drop-zone]', function (event) {
                var zone = String($(this).data('drop-zone') || '');
                var payload = activePayload || readPayload(event);

                if (!acceptsDrop(zone, payload)) {
                    return;
                }

                event.preventDefault();
                event.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('is-over');
            });

            $root.on('dragleave.ergonodeCategoryDrag', '[data-drop-zone]', function (event) {
                if (!isLeavingDropZone(this, event)) {
                    return;
                }

                $(this).removeClass('is-over');
            });

            $root.on('drop.ergonodeCategoryDrag', '[data-drop-zone]', function (event) {
                var zone = String($(this).data('drop-zone') || '');
                var payload = activePayload || readPayload(event);

                if (!acceptsDrop(zone, payload)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                $root.find('[data-drop-zone]').removeClass('is-over');
                activePayload = null;
                onDrop(payload.value || payload.code, Number($(this).data('magento-id') || 0));
            });
            if (scope && typeof scope.cleanup === 'function') {
                scope.cleanup(function () {
                    $root.off('.ergonodeCategoryDrag');
                });
            }
        }
    };

    function buildPayload($source) {
        return {
            type: String($source.data('drag-type') || ''),
            value: String($source.data('drag-value') || ''),
            code: String($source.data('code') || '')
        };
    }

    function readPayload(event) {
        var dataTransfer = event.originalEvent.dataTransfer;
        var raw = dataTransfer.getData('application/json') || dataTransfer.getData('text/plain');

        if (!raw) {
            return null;
        }

        try {
            return JSON.parse(raw);
        } catch (exception) {
            return null;
        }
    }

    function acceptsDrop(zone, payload) {
        return zone === 'magento-target' && payload && payload.type === 'category';
    }

    function isLeavingDropZone(zone, event) {
        var related = event.relatedTarget || event.originalEvent.relatedTarget;

        return !related || (related !== zone && !$.contains(zone, related));
    }
});
