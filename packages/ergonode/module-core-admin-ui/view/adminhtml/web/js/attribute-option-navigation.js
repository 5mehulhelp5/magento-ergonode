define([
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function (confirm, $t) {
    'use strict';

    function request(options) {
        if (!options || typeof options.navigate !== 'function') {
            return;
        }

        if (!options.dirty) {
            options.navigate();
            return;
        }

        confirm({
            title: $t('Zapisz zmiany przed przejściem'),
            content: $t('Masz niezapisane zmiany w mapowaniu atrybutów. Aby przejść do mapowania opcji, najpierw je zapisz.'),
            actions: {
                always: function () {},
                cancel: function () {},
                confirm: function () {
                    if (typeof options.saveAndNavigate === 'function') {
                        options.saveAndNavigate();
                    }
                }
            },
            buttons: [{
                text: $t('Anuluj'),
                class: 'action-secondary action-dismiss',
                click: function (event) {
                    this.closeModal(event);
                }
            }, {
                text: $t('Zapisz i przejdź'),
                class: 'action-primary action-accept',
                click: function (event) {
                    this.closeModal(event, true);
                }
            }]
        });
    }

    return {
        request: request
    };
});
