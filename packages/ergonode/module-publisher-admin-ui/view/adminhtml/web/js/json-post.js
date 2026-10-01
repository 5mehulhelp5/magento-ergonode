define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    function post(url, config, data, fallbackMessage) {
        return new Promise(function (resolve, reject) {
            var payload = {};

            if (!url) {
                reject(new Error($t('Missing Magento action URL.')));
                return;
            }
            Object.keys(data || {}).forEach(function (key) {
                payload[key] = data[key];
            });
            payload.form_key = config && config.form_key
                ? config.form_key
                : (window.FORM_KEY || '');

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                timeout: 60000,
                data: payload
            }).done(resolve).fail(function (xhr) {
                reject(new Error(
                    xhr && xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : (fallbackMessage || $t('Unable to connect to Magento.'))
                ));
            });
        });
    }

    return {post: post};
});
