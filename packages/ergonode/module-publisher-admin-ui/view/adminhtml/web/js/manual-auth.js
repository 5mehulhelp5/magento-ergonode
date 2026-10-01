define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'Ergonode_PublisherAdminUi/js/json-post'
], function ($, modal, $t, jsonPost) {
    'use strict';

    return function createManualAuth(config) {
        var dialog = document.createElement('div');
        var urls = config.urls || {};
        var widget;
        var pendingResolve = null;
        var destroyed = false;
        var focusTimer;

        dialog.className = 'vec-manual-auth';
        dialog.style.display = 'none';
        dialog.innerHTML = '<form data-role="manual-auth-form">'
            + '<div class="vec-manual-auth-brand" aria-hidden="true">'
            + '<img data-role="manual-auth-logo" alt=""></div>'
            + '<p class="vec-manual-auth-intro">'
            + $t('Log in to Ergonode to continue this operation.')
            + '</p>'
            + '<label class="vec-manual-auth-field"><span>' + $t('Ergonode login')
            + '</span><input name="username" autocomplete="username" required></label>'
            + '<label class="vec-manual-auth-field"><span>' + $t('Password')
            + '</span><input name="password" type="password" autocomplete="current-password" required></label>'
            + '<label class="vec-manual-auth-remember"><input name="remember_me" type="checkbox"> '
            + '<span>' + $t('Remember me') + '</span></label>'
            + '<p class="vec-manual-auth-intro">'
            + $t('When selected, Magento remembers this account for the active environment. Otherwise, login lasts only for your current admin session. Your password is never saved.')
            + '</p>'
            + '<div class="vec-manual-auth-requirements" role="note">'
            + '<strong>' + $t('Why is login required?') + '</strong><span>'
            + $t('This operation needs access to the Ergonode REST API, including product requirements or category trees.')
            + '</span><small>'
            + $t('Your Ergonode account must have permission to access the data required by this operation.')
            + '</small></div>'
            + '<div class="vec-manual-auth-error" data-role="manual-auth-error" role="alert" hidden></div>'
            + '<div class="vec-manual-auth-actions"><button type="button" '
            + 'class="vec-manual-auth-button" data-role="manual-auth-cancel">'
            + $t('Cancel') + '</button><button type="submit" '
            + 'class="vec-manual-auth-button vec-manual-auth-button-primary">'
            + $t('Log in') + '</button></div>'
            + '</form>';
        if (config.logo_url) {
            dialog.querySelector('[data-role="manual-auth-logo"]').src = config.logo_url;
        }
        document.body.appendChild(dialog);
        widget = $(dialog);
        modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            clickableOverlay: false,
            title: $t('Log in to Ergonode'),
            buttons: [],
            modalClass: 'vec-manual-auth-shell',
            closed: function () {
                dialog.querySelector('[name="password"]').value = '';
                if (pendingResolve) {
                    pendingResolve(false);
                    pendingResolve = null;
                }
            }
        }, widget);
        moveBrandToTitle();
        dialog.querySelector('[data-role="manual-auth-cancel"]').addEventListener('click', function () {
            widget.modal('closeModal');
        });
        dialog.querySelector('form').addEventListener('submit', function (event) {
            var form = event.currentTarget;
            var error = dialog.querySelector('[data-role="manual-auth-error"]');
            var submit = form.querySelector('[type="submit"]');

            event.preventDefault();
            submit.disabled = true;
            error.hidden = true;
            jsonPost.post(urls.login, config, {
                username: form.elements.username.value,
                password: form.elements.password.value,
                remember_me: form.elements.remember_me.checked ? 1 : 0
            }).then(function (response) {
                if (destroyed) { return; }
                if (!response || !response.success) {
                    throw new Error(response && response.message
                        ? response.message
                        : $t('Login failed.'));
                }
                if (pendingResolve) {
                    pendingResolve(true);
                    pendingResolve = null;
                }
                form.elements.password.value = '';
                form.elements.username.value = '';
                widget.modal('closeModal');
            }).catch(function (exception) {
                showError(exception.message || $t('Login failed.'));
            }).then(function () {
                submit.disabled = false;
                form.elements.password.value = '';
            });
        });

        return {
            destroy: destroy,
            ensure: function () {
                if (destroyed) { return Promise.resolve(false); }
                return jsonPost.post(urls.status, config).then(function (response) {
                    if (destroyed) { return false; }
                    if (!response || !response.success) {
                        throw new Error(response && response.message || $t('Unable to check the Ergonode connection.'));
                    }
                    if (response && response.authenticated) {
                        return true;
                    }

                    return open();
                }).catch(function (error) {
                    showError(error.message || $t('Unable to check the Ergonode connection.'));
                    return open();
                });
            }
        };

        function open() {
            if (destroyed) { return Promise.resolve(false); }
            return new Promise(function (resolve) {
                pendingResolve = resolve;
                widget.modal('openModal');
                focusTimer = window.setTimeout(function () {
                    dialog.querySelector('[name="username"]').focus();
                }, 0);
            });
        }

        function showError(message) {
            if (destroyed) { return; }
            var error = dialog.querySelector('[data-role="manual-auth-error"]');

            error.textContent = message;
            error.hidden = false;
        }

        function destroy() {
            if (destroyed) { return; }
            destroyed = true;
            window.clearTimeout(focusTimer);
            dialog.querySelector('[name="password"]').value = '';
            if (pendingResolve) {
                pendingResolve(false);
                pendingResolve = null;
            }
            var shell = dialog.closest('.modal-popup');

            if (widget.modal('option', 'isOpen') === true) {
                widget.modal('option', 'transitionEvent', null);
                widget.modal('closeModal');
            }
            widget.modal('destroy');
            $(shell).remove();
        }

        function moveBrandToTitle() {
            var brand = dialog.querySelector('.vec-manual-auth-brand');
            var modalShell = dialog.closest('.modal-inner-wrap');
            var title = modalShell ? modalShell.querySelector('.modal-title') : null;

            if (brand && title) {
                title.insertBefore(brand, title.firstChild);
            }
        }
    };
});
