define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'jquery/ui'
], function ($, $t, alert) {
    'use strict';

    $.widget('ergonode.testConnection', {
        options: {
            url: '',
            elementId: '',
            mode: '',
            environment: '',
            urlFieldId: '',
            apiKeyFieldId: ''
        },

        _create: function () {
            this._on({
                click: this._testConnection
            });
        },

        _testConnection: function () {
            var self = this,
                result = $('#' + this.options.elementId + '_result');

            this.element.prop('disabled', true);
            result.removeClass('success error').text($t('Testing connection...'));

            $.ajax({
                url: this.options.url,
                type: 'POST',
                dataType: 'json',
                timeout: 60000,
                showLoader: true,
                data: {
                    form_key: window.FORM_KEY,
                    mode: this.options.mode,
                    environment: this.options.environment,
                    ergonode_url: $('#' + this.options.urlFieldId).val(),
                    api_key: $('#' + this.options.apiKeyFieldId).val()
                }
            }).done(function (response) {
                if (response.success) {
                    result.addClass('success').text($t('Connection successful.'));
                    return;
                }

                result.addClass('error').text($t('Connection failed.'));
                alert({content: response.message || $t('Unable to test the Ergonode connection.')});
            }).fail(function () {
                result.addClass('error').text($t('Connection failed.'));
                alert({content: $t('Unable to test the Ergonode connection.')});
            }).always(function () {
                self.element.prop('disabled', false);
            });
        }
    });

    return $.ergonode.testConnection;
});
