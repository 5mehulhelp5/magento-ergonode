define([
    'mage/translate',
    'Ergonode_PublisherAdminUi/js/json-post'
], function ($t, jsonPost) {
    'use strict';

    return function createPublicationReadiness(config, onChange) {
        var state = config.write_readiness || {
            ready: false,
            message: $t('Checking the Ergonode write configuration…'),
            configuration_url: ''
        };
        var notice = null;
        var checking = null;

        function render() {
            if (notice) {
                notice.hidden = state.ready === true;
                notice.querySelector('[data-role="write-readiness-message"]').textContent = state.message || '';
                var link = notice.querySelector('a');

                link.hidden = !state.configuration_url;
                link.href = state.configuration_url || '#';
                notice.querySelector('button').disabled = checking !== null;
            }
            if (onChange) {
                onChange(state);
            }
        }

        function ensure() {
            if (checking) {
                return checking;
            }
            checking = jsonPost.post(config.urls.status, config).then(function (response) {
                if (!response || !response.success || !response.write_readiness) {
                    throw new Error($t('Unable to check the Ergonode write configuration. Try again.'));
                }
                state = response.write_readiness;
            }).catch(function (error) {
                state = {
                    ready: false,
                    message: error.message || $t('Unable to check the Ergonode write configuration. Try again.'),
                    configuration_url: state.configuration_url || ''
                };
            }).then(function () {
                checking = null;
                render();

                return state.ready === true;
            });
            render();

            return checking;
        }

        return {
            isReady: function () { return state.ready === true; },
            ensure: ensure,
            mount: function (element) {
                notice = document.createElement('div');
                notice.className = 'vec-publication-readiness';
                notice.setAttribute('data-role', 'write-readiness');
                notice.setAttribute('role', 'status');
                notice.innerHTML = '<span data-role="write-readiness-message"></span>'
                    + '<a class="veui-message-action" target="_blank" rel="noopener noreferrer"></a>'
                    + '<button type="button" class="veui-button"></button>';
                notice.querySelector('a').textContent = $t('Open configuration');
                notice.querySelector('button').textContent = $t('Check again');
                notice.querySelector('button').addEventListener('click', ensure);
                element.prepend(notice);
                render();
            }
        };
    };
});
