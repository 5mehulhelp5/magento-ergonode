define(['Ergonode_CoreAdminUi/js/text'], function (text) {
    'use strict';

    function bind(scope, config) {
        var events = config.events || ['input'];

        events.forEach(function (eventName) {
            scope.delegate(eventName, config.inputSelector, function (event, input) {
                config.update(text.normalize(input.value), input, event);
            });
        });
    }

    function sort(container, selector, config) {
        var items;

        if (!container) {
            return;
        }

        items = Array.prototype.slice.call(container.querySelectorAll(selector));
        items.sort(function (first, second) {
            var firstValue = text.normalize(config.value(first));
            var secondValue = text.normalize(config.value(second));
            var comparison = firstValue.localeCompare(secondValue, config.locale || 'pl', {
                numeric: true,
                sensitivity: 'base'
            });

            if (comparison !== 0) {
                return config.direction === 'desc' ? comparison * -1 : comparison;
            }

            return config.tie ? config.tie(first, second) : 0;
        });
        items.forEach(function (item) {
            container.appendChild(item);
        });
    }

    function filter(items, query, searchable) {
        var normalizedQuery = text.normalize(query);
        var visible = 0;

        Array.prototype.forEach.call(items || [], function (item) {
            var matches = !normalizedQuery || text.normalize(searchable(item)).indexOf(normalizedQuery) !== -1;

            item.hidden = !matches;
            if (matches) {
                visible += 1;
            }
        });

        return visible;
    }

    function bindSort(scope, panel, config) {
        var toggle = panel.querySelector(config.toggleSelector);
        var direction = panel.querySelector(config.directionSelector);

        function renderToggle() {
            var value = toggle ? toggle.getAttribute('data-sort-value') || config.defaultValue : config.defaultValue;
            var label = toggle && config.labelSelector ? toggle.querySelector(config.labelSelector) : null;
            var option = config.options[value] || config.options[config.defaultValue];

            if (!toggle || !option) {
                return;
            }

            if (label) {
                label.textContent = option.label;
            }
            toggle.setAttribute('aria-label', option.ariaLabel);
            toggle.setAttribute('title', option.ariaLabel);
        }

        function renderDirection() {
            var current = direction ? direction.getAttribute('data-direction') || 'asc' : 'asc';
            var label = direction && config.directionLabelSelector
                ? direction.querySelector(config.directionLabelSelector)
                : null;
            var optionLabel = current === 'desc' ? config.descOptionLabel : config.ascOptionLabel;
            var accessibleLabel = current === 'desc' ? config.descLabel : config.ascLabel;

            if (!direction) {
                return;
            }

            if (label && optionLabel) {
                label.textContent = optionLabel;
            }
            direction.setAttribute('aria-label', accessibleLabel);
            direction.setAttribute('title', accessibleLabel);
        }

        if (toggle) {
            scope.listen(toggle, 'click', function () {
                var current = toggle.getAttribute('data-sort-value') || config.defaultValue;
                var next = current === config.values[0] ? config.values[1] : config.values[0];

                toggle.setAttribute('data-sort-value', next);
                renderToggle();
                config.update(panel);
            });
            renderToggle();
        }

        if (direction) {
            scope.listen(direction, 'click', function () {
                var next = direction.getAttribute('data-direction') === 'desc' ? 'asc' : 'desc';

                direction.setAttribute('data-direction', next);
                renderDirection();
                config.update(panel);
            });
            renderDirection();
        }
    }

    return {
        bind: bind,
        bindSort: bindSort,
        sort: sort,
        filter: filter,
        normalize: text.normalize
    };
});
