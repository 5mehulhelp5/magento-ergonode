define([
    'Ergonode_CoreAdminUi/js/option-mapping',
    'mage/translate'
], function (optionMapping, $t) {
    'use strict';

    function parseResponse(response) {
        var contentType = response && response.headers && typeof response.headers.get === 'function'
            ? response.headers.get('content-type') || ''
            : '';

        if (!response || response.redirected || contentType.toLowerCase().indexOf('text/html') !== -1) {
            throw new Error($t('Sesja administratora Magento wygasła. Zaloguj się ponownie i odśwież stronę.'));
        }
        if (response.ok === false || typeof response.json !== 'function') {
            throw new Error($t('Nie udało się załadować mapowania opcji.'));
        }

        return response.json().catch(function () {
            throw new Error($t('Nieprawidłowa odpowiedź serwera.'));
        });
    }

    function load(url) {
        return window.fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(parseResponse).then(function (response) {
            if (!response || response.success === false) {
                throw new Error(response && response.message
                    ? response.message
                    : $t('Nie udało się załadować mapowania opcji.'));
            }

            return response;
        });
    }

    function destroyWorkspace(state) {
        if (state.workspace && state.workspace.veaWorkspace) {
            state.workspace.veaWorkspace.destroy();
        }
        state.workspace = null;
    }

    function create(scope, root, $, modal) {
        var element = document.createElement('div');
        var state;

        element.className = 'vea-option-mapping-modal-host';
        element.innerHTML = [
            '<div class="vea-option-mapping-modal-loading" data-role="option-mapping-modal-loading"',
            ' role="status">',
            '<span class="vea-context-loading-spinner" aria-hidden="true"></span>',
            '<span>', $t('Ładowanie mapowania opcji'), '</span>',
            '</div>',
            '<div data-role="option-mapping-modal-content" hidden></div>',
            '<div class="vea-option-mapping-modal-error" data-role="option-mapping-modal-error"',
            ' role="alert" hidden></div>'
        ].join('');
        state = {
            root: root,
            element: element,
            widget: $(element),
            content: element.querySelector('[data-role="option-mapping-modal-content"]'),
            error: element.querySelector('[data-role="option-mapping-modal-error"]'),
            loading: element.querySelector('[data-role="option-mapping-modal-loading"]'),
            workspace: null,
            url: '',
            requestId: 0,
            destroyed: false,
            loadingRequest: false
        };

        function close() {
            if (state.workspace && state.workspace.veaUnsavedNavigation) {
                state.workspace.veaUnsavedNavigation.request({
                    navigate: function () {
                        state.widget.modal('closeModal');
                    }
                });
                return;
            }

            state.widget.modal('closeModal');
        }

        modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            clickableOverlay: false,
            modalClass: 'vea-option-mapping-modal',
            title: $t('Mapowanie opcji'),
            buttons: [],
            modalCloseBtnHandler: close,
            outerClickHandler: close,
            keyEventHandlers: {
                escapeKey: close
            },
            closed: function () {
                state.requestId += 1;
                state.loadingRequest = false;
                destroyWorkspace(state);
                state.content.replaceChildren();
                state.error.hidden = true;
            }
        }, state.widget);

        function reloadWorkspace(event) {
            loadWorkspace(state, state.url, event.detail && event.detail.message, true);
        }

        state.element.addEventListener('vea:option-mapping-reload', reloadWorkspace);

        scope.cleanup(function () {
            state.destroyed = true;
            state.requestId += 1;
            destroyWorkspace(state);
            state.element.removeEventListener('vea:option-mapping-reload', reloadWorkspace);
            state.widget.modal('destroy');
        });

        root.veaOptionMappingModal = state;

        return state;
    }

    function loadWorkspace(state, url, successMessage, alreadyOpen) {
        var requestId;

        if (!url || state.destroyed || state.loadingRequest) {
            return;
        }

        state.loadingRequest = true;
        state.url = url;
        state.requestId += 1;
        requestId = state.requestId;
        destroyWorkspace(state);
        state.content.replaceChildren();
        state.content.hidden = true;
        state.error.hidden = true;
        state.loading.hidden = false;
        if (!alreadyOpen) {
            state.widget.modal('openModal');
        }

        load(url).then(function (response) {
            var workspace;

            if (state.destroyed || requestId !== state.requestId) {
                return;
            }

            state.content.innerHTML = response.html || '';
            workspace = state.content.querySelector('.veui-workspace');
            if (!workspace) {
                throw new Error($t('Nie udało się zbudować mapowania opcji.'));
            }

            state.workspace = workspace;
            optionMapping(response.config || {}, workspace);
            if (successMessage) {
                workspace.veaContext.message.show('success', successMessage);
            }
            state.content.hidden = false;
            state.loading.hidden = true;
            state.root.dispatchEvent(new CustomEvent('vea:option-mapping-ready', {
                detail: {workspace: workspace}
            }));
        }).catch(function (error) {
            if (state.destroyed || requestId !== state.requestId) {
                return;
            }

            state.loading.hidden = true;
            state.error.textContent = error && error.message
                ? error.message
                : $t('Nie udało się załadować mapowania opcji.');
            state.error.hidden = false;
        }).then(function () {
            if (requestId === state.requestId) {
                state.loadingRequest = false;
            }
        });
    }

    function open(scope, root, url) {
        var state = root.veaOptionMappingModal;

        if (!url || (state && (state.destroyed || state.loadingRequest))) {
            return;
        }
        if (state) {
            loadWorkspace(state, url);
            return;
        }
        if (root.veaOptionMappingModalLoading) {
            return;
        }

        root.veaOptionMappingModalLoading = true;
        require([
            'jquery',
            'Magento_Ui/js/modal/modal'
        ], function ($, modal) {
            root.veaOptionMappingModalLoading = false;
            if (!root.veaOptionMappingModal) {
                state = create(scope, root, $, modal);
            } else {
                state = root.veaOptionMappingModal;
            }
            loadWorkspace(state, url);
        });
    }

    return {
        open: open
    };
});
