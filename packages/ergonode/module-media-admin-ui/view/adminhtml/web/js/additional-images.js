define(['mage/translate', 'text!Ergonode_MediaAdminUi/template/additional-images.html'], function ($t, template) {
    'use strict';
    return function (config, element) {
        element.innerHTML = template;
        const labels = {
            caption: $t('Additional gallery images'),
            'attribute-label': $t('Ergonode Image attribute'),
            'position-label': $t('Position in Magento gallery'),
            'actions-label': $t('Actions'),
            add: $t('Add image')
        };
        Object.entries(labels).forEach(([role, label]) => { element.querySelector(`[data-role="${role}"]`).textContent = label; });
        const empty = document.createElement('input');
        empty.type = 'hidden';
        empty.name = config.name + '[__empty]';
        empty.value = '';
        element.append(empty);
        const tbody = element.querySelector('tbody');
        const add = element.querySelector('[data-role="add"]');
        let nextId = 0;
        function validate() {
            const codes = new Set();
            const positions = new Set();
            tbody.querySelectorAll('tr').forEach(row => {
                const select = row.querySelector('select');
                const input = row.querySelector('input');
                select.setCustomValidity(codes.has(select.value) ? $t('Each Image attribute can be used only once.') : '');
                input.setCustomValidity(positions.has(input.value) ? $t('Each gallery position can be used only once.') : '');
                codes.add(select.value);
                positions.add(input.value);
            });
        }
        function appendRule(rule = {}) {
            const id = nextId++;
            const row = document.createElement('tr');
            const attributeCell = document.createElement('td');
            const positionCell = document.createElement('td');
            const actionCell = document.createElement('td');
            const select = document.createElement('select');
            select.className = 'admin__control-select required-entry';
            select.name = `${config.name}[${id}][attribute]`;
            select.required = true;
            select.setAttribute('aria-label', $t('Ergonode Image attribute'));
            select.add(new Option($t('Choose an Image attribute'), ''));
            Object.entries(config.options).forEach(([value, label]) => select.add(new Option(label, value)));
            if (rule.attribute && !Object.hasOwn(config.options, rule.attribute)) {
                select.add(new Option(rule.attribute + ' — ' + $t('Unavailable'), rule.attribute));
            }
            select.value = rule.attribute || '';
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '2';
            input.max = '65535';
            input.required = true;
            input.className = 'admin__control-text required-entry validate-digits validate-digits-range digits-range-2-65535';
            input.name = `${config.name}[${id}][position]`;
            input.setAttribute('aria-label', $t('Position in Magento gallery'));
            input.value = String(rule.position || 2);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = $t('Remove image');
            remove.className = 'action-delete';
            remove.addEventListener('click', () => { row.remove(); validate(); add.focus(); });
            select.addEventListener('change', validate);
            input.addEventListener('input', validate);
            attributeCell.append(select);
            positionCell.append(input);
            actionCell.append(remove);
            row.append(attributeCell, positionCell, actionCell);
            tbody.append(row);
            validate();
            return select;
        }
        (config.rows || []).forEach(appendRule);
        add.addEventListener('click', () => appendRule().focus());
        if (config.error) {
            const error = element.querySelector('[data-role="error"]');
            error.textContent = config.error;
            error.hidden = false;
            add.disabled = true;
        }
    };
});
