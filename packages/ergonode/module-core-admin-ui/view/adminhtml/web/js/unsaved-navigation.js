define([
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function (confirm, $t) {
    'use strict';

    function create(options) {
        var navigationAllowed = false;
        var navigationTimer = null;
        var pending = false;

        options = options || {};

        function isDirty() {
            return typeof options.isDirty === 'function' && options.isDirty();
        }

        function allowNavigation(navigate) {
            navigationAllowed = true;
            window.clearTimeout(navigationTimer);
            navigationTimer = window.setTimeout(function () {
                navigationAllowed = false;
            }, 1000);
            navigate();
        }

        function request(requestOptions) {
            var choice = 'save';
            var navigate;

            requestOptions = requestOptions || {};
            navigate = requestOptions.navigate || options.navigate;
            if (typeof navigate !== 'function' || pending) {
                return;
            }
            if (!isDirty()) {
                allowNavigation(navigate);
                return;
            }

            pending = true;
            confirm({
                modalClass: 'confirm veui-unsaved-navigation-modal',
                focus: '.action-accept',
                title: $t('Warning, data is unsaved'),
                content: $t(
                    'You have unsaved changes. Do you want to save them before leaving this page?'
                ),
                actions: {
                    always: function () {},
                    cancel: function () {
                        pending = false;
                        if (typeof requestOptions.onCancel === 'function') {
                            requestOptions.onCancel();
                        }
                    },
                    confirm: function () {
                        if (choice === 'discard') {
                            pending = false;
                            allowNavigation(navigate);
                            return;
                        }
                        if (typeof options.save !== 'function') {
                            pending = false;
                            return;
                        }

                        Promise.resolve().then(options.save).then(function () {
                            pending = false;
                            allowNavigation(navigate);
                        }).catch(function (error) {
                            pending = false;
                            if (typeof requestOptions.onSaveError === 'function') {
                                requestOptions.onSaveError(error);
                            }
                        });
                    }
                },
                buttons: [{
                    text: $t('Cancel'),
                    class: 'action-secondary action-dismiss',
                    click: function (event) {
                        this.closeModal(event);
                    }
                }, {
                    text: $t('Discard'),
                    class: 'action-secondary action-discard',
                    click: function (event) {
                        choice = 'discard';
                        this.closeModal(event, true);
                    }
                }, {
                    text: $t('Save'),
                    class: 'action-primary action-accept',
                    click: function (event) {
                        choice = 'save';
                        this.closeModal(event, true);
                    }
                }]
            });
        }

        function beforeUnload(event) {
            if (navigationAllowed) {
                navigationAllowed = false;
                window.clearTimeout(navigationTimer);
                return undefined;
            }
            if (!isDirty()) {
                return undefined;
            }

            event.preventDefault();
            event.returnValue = '';

            return '';
        }

        return {
            beforeUnload: beforeUnload,
            destroy: function () {
                window.clearTimeout(navigationTimer);
            },
            isDirty: isDirty,
            request: request
        };
    }

    function isLinkNavigation(event, anchor) {
        var href = anchor ? anchor.getAttribute('href') || '' : '';
        var target = anchor ? anchor.getAttribute('target') || '' : '';
        var current;
        var destination;

        if (!anchor || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return false;
        }
        if (typeof event.button === 'number' && event.button !== 0) {
            return false;
        }
        if (anchor.hasAttribute('download') || (target !== '' && target !== '_self')) {
            return false;
        }
        if (href === '' || href.charAt(0) === '#' || /^javascript:/i.test(href)) {
            return false;
        }

        current = new URL(window.location.href);
        destination = new URL(anchor.href, current.href);

        return !(destination.hash
            && destination.origin === current.origin
            && destination.pathname === current.pathname
            && destination.search === current.search);
    }

    function bind(scope, options) {
        var controller = create(options);

        scope.listen(window, 'beforeunload', controller.beforeUnload);
        scope.listen(document, 'click', function (event) {
            var anchor = event.target && event.target.closest
                ? event.target.closest('a[href]')
                : null;

            if (!controller.isDirty() || !isLinkNavigation(event, anchor)) {
                return;
            }
            if (typeof options.shouldHandleLink === 'function' && !options.shouldHandleLink(anchor)) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            controller.request({
                navigate: function () {
                    if (typeof options.navigate === 'function') {
                        options.navigate(anchor.href);
                        return;
                    }
                    window.location.assign(anchor.href);
                }
            });
        }, true);
        scope.cleanup(function () {
            controller.destroy();
        });

        return controller;
    }

    return {
        bind: bind,
        create: create
    };
});
