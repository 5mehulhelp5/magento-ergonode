define(['mage/translate'], function ($t) {
    'use strict';

    var REQUEST_TIMEOUT_MS = 60000;

    function isHtmlResponse(response) {
        var contentType = response.headers && typeof response.headers.get === 'function'
            ? response.headers.get('content-type') || ''
            : '';

        return contentType.toLowerCase().indexOf('text/html') !== -1;
    }

    function parseResponse(response) {
        if (!response || typeof response.json !== 'function') {
            throw new Error($t('Nieprawidłowa odpowiedź serwera.'));
        }
        if (response.redirected || isHtmlResponse(response)) {
            throw new Error($t(
                'Sesja administratora Magento wygasła. Zaloguj się ponownie i odśwież stronę.'
            ));
        }

        return Promise.resolve()
            .then(function () {
                return response.json();
            })
            .catch(function () {
                throw new Error($t('Nieprawidłowa odpowiedź serwera.'));
            });
    }

    function post(url, config, data) {
        var body;
        var controller;
        var fetchAction = config && config.fetch ? config.fetch : window.fetch.bind(window);
        var options;
        var timeoutId;
        var timeoutPromise;

        if (!url) {
            return Promise.reject(new Error($t('Brak URL akcji.')));
        }

        controller = new window.AbortController();
        body = new URLSearchParams();
        body.set('form_key', config && config.form_key ? config.form_key : (window.FORM_KEY || ''));
        Object.keys(data || {}).forEach(function (key) {
            body.set(key, data[key]);
        });

        options = {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        };
        options.signal = controller.signal;

        timeoutPromise = new Promise(function (resolve, reject) {
            timeoutId = window.setTimeout(function () {
                controller.abort();
                reject(new Error($t('Operacja przekroczyła limit 60 sekund.')));
            }, REQUEST_TIMEOUT_MS);
        });

        return Promise.race([
            Promise.resolve().then(function () {
                return fetchAction(url, options);
            }).then(parseResponse),
            timeoutPromise
        ]).then(function (response) {
            if (!response || response.success === false) {
                throw new Error(response && response.message ? response.message : $t('Operacja nie powiodła się.'));
            }

            return response;
        }).finally(function () {
            window.clearTimeout(timeoutId);
        });
    }

    function paginate(options, cursor, pageSize) {
        var data = options.data ? options.data(cursor || '', pageSize) : {};

        data.cursor = cursor || '';
        data.page_size = String(pageSize || options.pageSize || 200);

        return post(options.url, options.config || {}, data).then(function (response) {
            if (typeof options.onPage === 'function') {
                options.onPage(response);
            }

            if (response.has_more) {
                return paginate(options, response.cursor || '', response.page_size || pageSize || options.pageSize || 200);
            }

            return response;
        });
    }

    return {
        post: post,
        paginate: paginate
    };
});
