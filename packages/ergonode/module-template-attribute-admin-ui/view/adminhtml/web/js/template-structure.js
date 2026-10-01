define(['mage/translate'], function ($t) {
    'use strict';

    function node(tag, className, text) {
        var element = document.createElement(tag);
        element.className = className || '';
        if (text !== undefined) { element.textContent = text; }
        return element;
    }

    function card(label, code, type, required, attribute, inSet) {
        var item = node('div', 'vets-attribute');
        var copy = node('div', 'vets-attribute-copy');
        copy.append(node('strong', '', label || code), node('code', '', code));
        item.append(node('span', 'vets-attribute-icon', 'Aa'), copy);
        item.firstChild.setAttribute('aria-hidden', 'true');
        if (required) { item.append(node('span', 'vets-required', $t('Wymagany'))); }
        if (type) { item.append(node('span', 'vets-type', type)); }
        attribute = attribute || {};
        var codes = attribute.ergonode_attribute_codes || [];
        if (codes.length) {
            copy.append(node('span', 'vets-mapping-code', $t('Atrybut Ergonode: ') + codes.join(', ')));
        }
        if (attribute.manual_placement && attribute.missing_from_template) {
            copy.append(node('span', 'vets-retained', $t('Brak w szablonie Ergonode — zachowany ręcznie')));
        }
        if (inSet && codes.length) {
            var actions = node('span', 'vets-placement-actions');
            actions.setAttribute('data-role', 'placement-actions');
            actions.setAttribute('data-attribute-id', attribute.attribute_id);
            item.append(actions);
        }
        return item;
    }

    function group(label, code, attributes, metadata) {
        var section = node('details', 'vets-group');
        var summary = node('summary', 'vets-group-heading');
        section.open = true;
        summary.tabIndex = 0;
        summary.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                section.open = !section.open;
            }
        });
        summary.append(node('span', 'vets-folder'), node('strong', '', label),
            node('span', 'vets-count', String(attributes.length)));
        summary.firstChild.setAttribute('aria-hidden', 'true');
        if (metadata) {
            var ownership = node('span', 'vets-ownership' + (metadata.managed_by_ergonode ? ' is-ergonode' : ''),
                metadata.managed_by_ergonode ? $t('Ergonode') : $t('Magento — zachowana struktura'));
            summary.append(ownership);
        }
        section.append(summary);
        if (metadata && (metadata.ergonode_section_codes || []).length) {
            section.append(node('span', 'vets-mapping-code vets-group-mapping',
                $t('Sekcja Ergonode: ') + metadata.ergonode_section_codes.join(', ')));
        }
        if (code && code !== label) { section.append(node('code', 'vets-section-code', code)); }
        attributes.forEach(function (attribute) {
            section.append(card(attribute.frontend_label || attribute.attribute_code,
                attribute.attribute_code, attribute.frontend_input || '', Number(attribute.is_required) === 1, attribute, Boolean(metadata)));
        });
        if (!attributes.length) { section.append(node('p', 'vets-empty', $t('Brak atrybutów w tej sekcji.'))); }
        return section;
    }

    function sectionName(section) {
        var raw;
        try { raw = JSON.parse(section.raw_json || '{}'); } catch (error) { raw = {}; }
        var names = Array.isArray(raw.name) ? raw.name : [];
        return section.label || (names[0] && names[0].value) || section.section_code;
    }

    function panel(title, subtitle, brand, count, icons) {
        var section = node('section', 'veui-panel vets-panel');
        var header = node('div', 'veui-panel-head vets-panel-head');
        var heading = node('div', 'veui-panel-heading');
        var titleNode = node('strong', 'veui-panel-title');
        var mark = node('span', 'vets-brand');
        if (icons && icons[brand]) {
            var logo = node('img');
            logo.src = icons[brand];
            logo.alt = '';
            mark.append(logo);
        }
        mark.setAttribute('aria-hidden', 'true');
        titleNode.append(mark, node('span', '', title));
        heading.append(titleNode, node('span', 'veui-panel-subtitle', subtitle));
        header.append(heading, node('span', 'vets-total', String(count)));
        var body = node('div', 'vets-panel-body');
        section.append(header, body);
        return {element: section, body: body};
    }

    function render(data) {
        data = data || {};
        var root = node('div', 'veui-workspace vets-workspace');
        var top = node('div', 'vets-context');
        var title = node('div', 'vets-context-names');
        title.append(node('strong', '', data.template || $t('Template')),
            node('span', 'vets-context-arrow', '→'), node('strong', '', data.attributeSet || $t('Zestaw atrybutów')));
        top.append(title, node('span', 'vets-readonly', $t('Tylko podgląd')));
        root.append(top);
        if (data.state === 'loading' || data.state === 'error') {
            var status = node('div', 'vets-status', data.state === 'loading'
                ? $t('Wczytywanie struktury zestawu atrybutów…')
                : (data.message || $t('Nie udało się wczytać struktury zestawu atrybutów.')));
            status.setAttribute('role', data.state === 'error' ? 'alert' : 'status');
            root.append(status);
            return root;
        }
        var attributes = data.attributes || [];
        var source = data.sourceAttributes || [];
        var assigned = attributes.filter(function (a) { return a.attribute_group_id !== null; });
        var unassigned = attributes.filter(function (a) { return a.attribute_group_id === null; });
        var left = panel($t('Ergonode'), $t('Sekcje i atrybuty szablonu'), 'ergonode', source.length, data.icons);
        var middle = panel($t('Magento'), $t('Aktualna struktura zestawu'), 'magento', assigned.length, data.icons);
        var right = panel($t('Poza zestawem'), $t('Pozostałe atrybuty Magento'), 'magento', unassigned.length, data.icons);
        (data.sections || []).forEach(function (section) {
            left.body.append(group(sectionName(section), section.section_code, source.filter(function (a) {
                return a.section_code === section.section_code;
            })));
        });
        var sectionCodes = (data.sections || []).map(function (s) { return s.section_code; });
        var ungrouped = source.filter(function (a) { return sectionCodes.indexOf(a.section_code) === -1; });
        if (ungrouped.length) { left.body.append(group($t('Bez sekcji'), '', ungrouped)); }
        (data.groups || []).forEach(function (item) {
            middle.body.append(group(item.attribute_group_name, '', assigned.filter(function (a) {
                return Number(a.attribute_group_id) === Number(item.attribute_group_id);
            }), item));
        });
        unassigned.forEach(function (a) { right.body.append(card(a.frontend_label, a.attribute_code, a.frontend_input, Number(a.is_required) === 1, a, false)); });
        if (!left.body.childElementCount) { left.body.append(node('p', 'vets-empty', $t('Brak lokalnych danych o strukturze szablonu.'))); }
        if (!middle.body.childElementCount) { middle.body.append(node('p', 'vets-empty', $t('Brak grup w zestawie atrybutów.'))); }
        if (!right.body.childElementCount) { right.body.append(node('p', 'vets-empty', $t('Wszystkie atrybuty należą do tego zestawu.'))); }
        var columns = node('div', 'vets-columns');
        columns.append(left.element, middle.element, right.element);
        root.append(columns);
        return root;
    }

    return {render: render};
});
