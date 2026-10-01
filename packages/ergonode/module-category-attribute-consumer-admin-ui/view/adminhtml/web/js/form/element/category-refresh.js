define([
    'jquery',
    'uiRegistry',
    'Magento_Ui/js/form/element/abstract',
    'mage/translate'
], function ($, registry, Abstract, $t) {
    'use strict';

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Ergonode_CategoryAttributeConsumerAdminUi/form/element/category-refresh',
            urls: {},
            categoryId: 0,
            categoryCode: '',
            busy: false,
            message: '',
            messageType: '',
            listens: {
                categoryId: 'syncContext',
                categoryCode: 'syncContext'
            }
        },

        initialize: function () {
            this._super();
            this.lastCategoryId = null;
            this.syncContext();

            return this;
        },

        initObservable: function () {
            this._super().observe([
                'categoryId',
                'categoryCode',
                'busy',
                'message',
                'messageType'
            ]);

            return this;
        },

        canRefresh: function () {
            return Number(this.categoryId() || 0) > 0
                && String(this.categoryCode() || '') !== '';
        },

        syncContext: function () {
            var categoryId = Number(this.categoryId() || 0);

            if (this.lastCategoryId !== null && this.lastCategoryId !== categoryId) {
                this.message('');
                this.messageType('');
            }
            this.lastCategoryId = categoryId;
            this.visible(this.canRefresh() || String(this.message() || '') !== '');

            return this;
        },

        refresh: function () {
            var self = this,
                categoryId = this.categoryId(),
                categoryCode = this.categoryCode();

            if (!this.canRefresh() || this.busy()) {
                return;
            }
            if (this.hasFormChanges()) {
                this.showUnsavedChanges();
                return;
            }
            this.busy(true);
            this.message('');
            this.messageType('');
            request(this.urls.refresh, {category_id: Number(this.categoryId() || 0)})
                .then(function (response) {
                    if (self.categoryId() !== categoryId || self.categoryCode() !== categoryCode) {
                        return;
                    }
                    if (!response || !response.success) {
                        throw new Error(response && response.message
                            ? response.message
                            : $t('Unable to refresh the category from Ergonode.'));
                    }
                    self.messageType('success');
                    self.message(response.message || $t('Category refreshed from Ergonode.'));
                    self.visible(true);
                    if (response.reload) {
                        if (self.hasFormChanges()) {
                            self.showUnsavedChanges();
                        } else {
                            self.reloadPage();
                        }
                    }
                }).catch(function (error) {
                    if (self.categoryId() !== categoryId || self.categoryCode() !== categoryCode) {
                        return;
                    }
                    self.messageType('error');
                    self.message(error && error.message
                        ? error.message
                        : $t('Unable to refresh the category from Ergonode.'));
                    self.visible(true);
                }).then(function () {
                    self.busy(false);
                });
        },

        hasFormChanges: function () {
            var self = this;

            return registry.filter(function (field) {
                return field !== self && field.provider === self.provider
                    && typeof field.hasChanged === 'function' && field.hasChanged();
            }).length > 0;
        },

        showUnsavedChanges: function () {
            this.messageType('error');
            this.message($t('Save or discard your changes before refreshing the category.'));
            this.visible(true);
        },

        reloadPage: function () {
            window.location.reload();
        }
    });

    function request(url, data) {
        return new Promise(function (resolve, reject) {
            data.form_key = window.FORM_KEY || '';
            $.ajax({url: url, type: 'POST', dataType: 'json', timeout: 60000, data: data})
                .done(resolve)
                .fail(function (xhr) {
                    reject(new Error(
                        xhr && xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : $t('Unable to connect to Magento.')
                    ));
                });
        });
    }
});
