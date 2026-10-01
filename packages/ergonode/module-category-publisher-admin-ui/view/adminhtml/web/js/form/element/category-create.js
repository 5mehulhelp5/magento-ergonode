define([
    'require',
    'Magento_Ui/js/form/element/abstract',
    'mage/translate',
    'Ergonode_PublisherAdminUi/js/json-post',
    'Ergonode_PublisherAdminUi/js/manual-auth',
    'Ergonode_CategoryPublisherAdminUi/js/publication-readiness'
], function (localRequire, Abstract, $t, jsonPost, createManualAuth, createPublicationReadiness) {
    'use strict';

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Ergonode_CategoryPublisherAdminUi/form/element/category-create',
            urls: {},
            categoryId: 0,
            categoryCode: '',
            categoryTreeId: 0,
            busy: false,
            writeReady: false,
            readinessMessage: '',
            configurationUrl: '',
            message: '',
            messageType: '',
            listens: {
                categoryId: 'syncContext',
                categoryCode: 'syncContext',
                categoryTreeId: 'syncContext'
            }
        },

        initialize: function () {
            this._super();
            this.lastCategoryId = null;
            this.publication = null;
            this.publicationDisposed = false;
            this.auth = createManualAuth({
                urls: this.urls,
                logo_url: localRequire.toUrl('Ergonode_CoreAdminUi/images/m2_configuration.svg')
            });
            var self = this;

            this.readiness = createPublicationReadiness({urls: this.urls}, function (state) {
                if (self.publicationDisposed) { return; }
                self.writeReady(state.ready === true);
                self.readinessMessage(state.message || '');
                self.configurationUrl(state.configuration_url || '');
            });
            this.readiness.ensure();
            this.syncContext();

            return this;
        },

        initObservable: function () {
            this._super().observe([
                'categoryId',
                'categoryCode',
                'categoryTreeId',
                'busy',
                'writeReady',
                'readinessMessage',
                'configurationUrl',
                'message',
                'messageType'
            ]);

            return this;
        },

        canCreate: function () {
            return Number(this.categoryId() || 0) > 0
                && Number(this.categoryTreeId() || 0) > 0
                && String(this.categoryCode() || '') === '';
        },

        syncContext: function () {
            var categoryId = Number(this.categoryId() || 0);

            if (this.publication && !this.isCurrentPublication(this.publication)) {
                this.cancelPublication();
            }
            if (this.lastCategoryId !== null && this.lastCategoryId !== categoryId) {
                this.message('');
                this.messageType('');
            }
            this.lastCategoryId = categoryId;
            this.visible(this.canCreate() || String(this.message() || '') !== '');

            return this;
        },

        create: function () {
            var self = this;

            if (this.publicationDisposed || !this.canCreate() || !this.writeReady() || this.busy()) {
                return;
            }
            var operation = {
                categoryId: Number(this.categoryId()),
                treeId: Number(this.categoryTreeId()),
                attempts: 0,
                startedAt: Date.now(),
                delayedSeconds: 0,
                cancelWait: null
            };

            this.publication = operation;
            this.busy(true);
            this.message('');
            this.messageType('');
            return this.readiness.ensure().then(function (ready) {
                self.assertCurrentPublication(operation);
                return ready ? self.auth.ensure() : false;
            }).then(function (authenticated) {
                self.assertCurrentPublication(operation);
                if (!authenticated) {
                    return;
                }
                return self.sendWithRetry(operation).then(function (response) {
                    self.assertCurrentPublication(operation);
                    self.source.set('data.ergonode_category_code', String(response.code));
                    self.messageType('success');
                    self.message(response.message || $t('The category has been created in Ergonode and mapped with Magento.'));
                    self.visible(true);
                });
            }).catch(function (error) {
                if (self.isCurrentPublication(operation)) {
                    self.messageType('error');
                    self.message(error && error.message
                        ? error.message
                        : $t('Unable to create and map the category in Ergonode.'));
                    self.visible(true);
                }
            }).then(function () {
                if (self.publication === operation) {
                    self.publication = null;
                    self.busy(false);
                }
            });
        },

        checkConfiguration: function () {
            return this.readiness.ensure();
        },

        sendWithRetry: function (operation) {
            var self = this;

            this.assertCurrentPublication(operation);
            operation.attempts++;
            return jsonPost.post(
                this.urls.create,
                {},
                {category_id: operation.categoryId},
                $t('Unable to connect to Magento.')
            ).then(function (response) {
                self.assertCurrentPublication(operation);
                var retryAfter = Number(response && response.retry_after_seconds || 0);

                if (response && !response.success && response.failure_type === 'retryable' && retryAfter > 0) {
                    if (!Number.isFinite(retryAfter) || operation.attempts >= 5
                        || Math.max((Date.now() - operation.startedAt) / 1000, operation.delayedSeconds) + retryAfter > 120
                    ) {
                        throw new Error((response.message ? response.message + ' ' : '') + $t(
                            'Automatic retries stopped. Publication progress was retained. Click Publish to resume.'
                        ));
                    }
                    operation.delayedSeconds += retryAfter;
                    return new Promise(function (resolve, reject) {
                        var timer = window.setTimeout(function () {
                            operation.cancelWait = null;
                            resolve();
                        }, Math.max(1, retryAfter) * 1000);

                        operation.cancelWait = function () {
                            window.clearTimeout(timer);
                            reject(new Error('Category publication was cancelled.'));
                        };
                    }).then(function () {
                        return self.sendWithRetry(operation);
                    });
                }
                if (!response || !response.success || response.code === undefined
                    || response.code === null || String(response.code) === ''
                ) {
                    throw new Error(response && response.message
                        ? response.message
                        : $t('Unable to create and map the category in Ergonode.'));
                }

                return response;
            });
        },

        isCurrentPublication: function (operation) {
            return !!operation && !this.publicationDisposed && this.publication === operation
                && Number(this.categoryId()) === operation.categoryId
                && Number(this.categoryTreeId()) === operation.treeId;
        },

        assertCurrentPublication: function (operation) {
            if (!this.isCurrentPublication(operation)) {
                throw new Error('Category publication context changed.');
            }
        },

        cancelPublication: function () {
            var operation = this.publication;

            this.publication = null;
            if (operation) {
                if (operation.cancelWait) {
                    operation.cancelWait();
                    operation.cancelWait = null;
                }
                this.busy(false);
            }
        },

        destroy: function () {
            this.publicationDisposed = true;
            this.cancelPublication();
            this.auth.destroy();
            return this._super();
        }

    });

});
