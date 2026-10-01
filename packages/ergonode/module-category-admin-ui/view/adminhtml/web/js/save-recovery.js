define(['jquery'], function ($) {
    'use strict';

    return function saveWithRecovery(send, recover) {
        var result = $.Deferred();

        attempt(false);

        return result.promise();

        function attempt(retried) {
            send().done(function (response) {
                if (!response || response.success || retried || typeof recover !== 'function') {
                    result.resolve(response);
                    return;
                }

                Promise.resolve().then(function () {
                    return recover(response);
                }).then(function (retry) {
                    if (retry === true) {
                        attempt(true);
                    } else {
                        result.resolve(response);
                    }
                }).catch(function () {
                    result.resolve(response);
                });
            }).fail(function () {
                result.reject.apply(result, arguments);
            });
        }
    };
});
