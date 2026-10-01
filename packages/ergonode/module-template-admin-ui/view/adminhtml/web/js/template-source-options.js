define([
    'Ergonode_CoreAdminUi/js/entity-options',
    'Ergonode_CoreAdminUi/js/visibility-toggle',
    'mage/translate'
], function (entityOptions, visibilityToggle, $t) {
    'use strict';

    var templatePanelSelector = '[data-role="template-drop-source"]';
    var attributeSetPanelSelector = '[data-role="attribute-set-drop-source"]';

    function createAction(options) {
        return entityOptions.createAction(options);
    }

    function buildSortActions() {
        return [
            createAction({
                role: 'source-sort-direction',
                iconClass: 'veui-sort-direction-icon',
                label: $t('Góra'),
                title: $t('Sortuj rosnąco'),
                ariaLabel: $t('Sortuj rosnąco'),
                attributes: {
                    'data-direction': 'asc'
                }
            }),
            createAction({
                role: 'source-sort-toggle',
                iconClass: 'veui-sort-field-icon',
                label: $t('Nazwa'),
                title: $t('Sortuj po nazwie'),
                ariaLabel: $t('Sortuj po nazwie'),
                attributes: {
                    'data-sort-value': 'label'
                }
            })
        ];
    }

    function buildTemplateActions(panel) {
        return [
            createAction({
                role: 'visibility-toggle',
                className: 'veui-visibility-control',
                iconClass: 'veui-visibility-icon',
                label: $t('Excluded'),
                attributes: {
                    'aria-pressed': 'false',
                    'data-show-hint': panel.getAttribute('data-show-excluded-hint') || '',
                    'data-hide-hint': panel.getAttribute('data-hide-excluded-hint') || ''
                }
            })
        ].concat(buildSortActions());
    }

    function sync(root, available) {
        var button = root
            ? root.querySelector('[data-template-source-options] [data-role="visibility-toggle"]')
            : null;

        if (!button) {
            return false;
        }
        if (!available) {
            visibilityToggle.setVisible(button, false);
        }
        button.disabled = !available;

        return available;
    }

    function initializePanel(panel, menuAttribute, actions, fallbackLabel) {
        var tools = panel ? panel.querySelector('.vet-side-tools') : null;
        var menu = tools ? tools.querySelector('[' + menuAttribute + ']') : null;
        var placeholder = tools
            ? tools.querySelector('[data-role="entity-options-placeholder"]')
            : null;

        if (!panel || !tools) {
            return null;
        }
        if (!menu) {
            menu = entityOptions.create({
                actions: actions,
                menuLabel: panel.getAttribute('data-source-options-label') || fallbackLabel
            });
            menu.classList.add('veui-source-options');
            menu.classList.add('vet-source-options');
            menu.setAttribute(menuAttribute, '1');
            tools.appendChild(menu);
        }
        if (placeholder) {
            placeholder.remove();
        }

        return menu;
    }

    function initialize(root, available) {
        var templatePanel = root ? root.querySelector(templatePanelSelector) : null;
        var attributeSetPanel = root ? root.querySelector(attributeSetPanelSelector) : null;
        var templateMenu = initializePanel(
            templatePanel,
            'data-template-source-options',
            templatePanel ? buildTemplateActions(templatePanel) : [],
            $t('Template actions: Ergonode')
        );

        initializePanel(
            attributeSetPanel,
            'data-attribute-set-source-options',
            attributeSetPanel ? buildSortActions() : [],
            $t('Attribute set actions: Magento')
        );
        if (templateMenu) {
            visibilityToggle.initialize(templateMenu);
        }
        sync(root, available);

        return templateMenu;
    }

    return {
        initialize: initialize,
        sync: sync
    };
});
