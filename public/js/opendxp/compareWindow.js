opendxp.registerNS('opendxp.plugin.pricat.compareWindow');

/**
 * Loads the compare table of one position and shows it in a modal with Accept / Decline.
 * `onProcessed()` is called after the position was accepted or declined (the caller reloads its grid).
 */
opendxp.plugin.pricat.compareWindow = Class.create({

    initialize: function (positionId, onProcessed) {
        this.positionId = positionId;
        this.onProcessed = onProcessed;
    },

    show: function () {
        Ext.Ajax.request({
            url: Routing.generate('iperson1337_opendxp_pricat_position_compare', {id: this.positionId}),
            method: 'GET',
            success: function (response) {
                const result = Ext.decode(response.responseText);
                if (!result.success) {
                    Ext.Msg.alert(t('plugin_pimcore_pricat_message'), result.message);
                    return;
                }
                if (!result.data.rows || !result.data.rows.length) {
                    Ext.Msg.alert(t('plugin_pimcore_pricat_message'), t('plugin_pimcore_pricat_compare_no_data'));
                    return;
                }
                this.render(result.data);
            }.bind(this),
            failure: function () {
                Ext.Msg.alert(t('plugin_pimcore_pricat_message'), t('plugin_pimcore_pricat_error_accept_failed').replace('%error%', 'HTTP'));
            }
        });
    },

    render: function (data) {
        // preselect what accept would change: writable rows whose PRICAT value is non-empty and differs
        const rows = data.rows.map(function (row) {
            return Ext.apply({apply: row.writable && !row.isEqual && !row.isPositionEmpty}, row);
        });

        const store = Ext.create('Ext.data.Store', {
            model: 'opendxp.plugin.pricat.model.CompareRow',
            data: rows
        });
        this.store = store;

        const dash = function (value) {
            return value === null || value === undefined || value === '' ? '<span class="pricat-dash">—</span>' : Ext.util.Format.htmlEncode(value);
        };

        const grid = Ext.create('Ext.grid.Panel', {
            store: store,
            border: false,
            viewConfig: {
                stripeRows: true,
                getRowClass: function (record) {
                    return record.get('writable') ? '' : 'pricat-row-readonly';
                }
            },
            columns: [
                {
                    xtype: 'checkcolumn',
                    text: t('plugin_pimcore_pricat_compare_col_apply'),
                    dataIndex: 'apply',
                    width: 70,
                    sortable: false,
                    menuDisabled: true,
                    listeners: {
                        // compare-only rows can never be selected for writing
                        beforecheckchange: function (column, rowIndex, checked, record) {
                            return record.get('writable') === true;
                        }
                    }
                },
                {
                    text: t('plugin_pimcore_pricat_compare_col_field'),
                    dataIndex: 'label',
                    flex: 2,
                    renderer: function (value, meta, record) {
                        meta.tdAttr = 'data-qtip="' + Ext.util.Format.htmlEncode(record.get('positionField') + ' → ' + record.get('productField')) + '"';
                        return Ext.util.Format.htmlEncode(value);
                    }
                },
                {
                    text: t('plugin_pimcore_pricat_compare_col_position'),
                    dataIndex: 'positionValue',
                    flex: 3,
                    renderer: function (value, meta, record) {
                        if (!record.get('isEqual') && !record.get('isPositionEmpty')) {
                            meta.tdCls = 'pricat-cell-diff';
                        }
                        return dash(value);
                    }
                },
                {
                    text: t('plugin_pimcore_pricat_compare_col_product'),
                    dataIndex: 'productValue',
                    flex: 3,
                    renderer: function (value, meta, record) {
                        if (record.get('isProductEmpty')) {
                            meta.tdCls = 'pricat-cell-empty';
                        }
                        return dash(value);
                    }
                }
            ]
        });

        const productLink = data.product
            ? '<a href="#" class="pricat-product-link" data-id="' + data.product.id + '">' + Ext.util.Format.htmlEncode(data.product.path) + '</a>'
            : '';

        this.window = new Ext.Window({
            title: t('plugin_pimcore_pricat_compare_title') + ' #' + this.positionId,
            width: 900,
            height: 600,
            modal: true,
            closable: true,
            layout: 'fit',
            items: [grid],
            tbar: [
                {
                    xtype: 'component',
                    cls: 'pricat-compare-header',
                    html: '<b>' + t('plugin_pimcore_pricat_compare_product') + ':</b> ' + productLink,
                    listeners: {
                        afterrender: function (cmp) {
                            cmp.getEl().on('click', function (e, target) {
                                if (target.className === 'pricat-product-link') {
                                    e.preventDefault();
                                    opendxp.helpers.openObject(parseInt(target.getAttribute('data-id'), 10), 'object');
                                }
                            });
                        }
                    }
                }
            ],
            bbar: [
                {
                    xtype: 'component',
                    cls: 'pricat-compare-legend',
                    html: t('plugin_pimcore_pricat_compare_legend')
                },
                '->',
                {
                    text: t('plugin_pimcore_pricat_btn_accept'),
                    iconCls: 'opendxp_icon_accept',
                    handler: this.accept.bind(this)
                },
                {
                    text: t('plugin_pimcore_pricat_btn_decline'),
                    iconCls: 'opendxp_icon_cancel',
                    handler: this.decline.bind(this)
                }
            ]
        });

        this.window.show();
    },

    selectedFields: function () {
        const fields = [];
        this.store.each(function (record) {
            if (record.get('writable') && record.get('apply')) {
                fields.push(record.get('productField'));
            }
        });
        return fields;
    },

    accept: function () {
        const fields = this.selectedFields();
        const message = fields.length
            ? t('plugin_pimcore_pricat_compare_accept_confirm').replace('%count%', fields.length)
            : t('plugin_pimcore_pricat_compare_accept_confirm_none');

        Ext.Msg.confirm(
            t('plugin_pimcore_pricat_btn_accept'),
            message,
            function (answer) {
                if (answer !== 'yes') {
                    return;
                }
                Ext.Ajax.request({
                    url: Routing.generate('iperson1337_opendxp_pricat_position_accept', {id: this.positionId}),
                    method: 'POST',
                    jsonData: {fields: fields},
                    success: function (response) {
                        this.handleResult(Ext.decode(response.responseText));
                    }.bind(this),
                    failure: this.handleTransportFailure.bind(this)
                });
            }.bind(this)
        );
    },

    decline: function () {
        const form = Ext.create('Ext.form.Panel', {
            bodyPadding: 10,
            border: false,
            items: [
                {
                    xtype: 'textarea',
                    name: 'comment',
                    fieldLabel: t('plugin_pimcore_pricat_decline_comment'),
                    allowBlank: false,
                    anchor: '100%',
                    height: 90
                }
            ]
        });

        const declineWindow = new Ext.Window({
            title: t('plugin_pimcore_pricat_decline_title'),
            width: 520,
            modal: true,
            layout: 'fit',
            items: [form],
            buttons: [
                {
                    text: t('plugin_pimcore_pricat_btn_send'),
                    iconCls: 'opendxp_icon_apply',
                    handler: function () {
                        if (!form.getForm().isValid()) {
                            return;
                        }
                        Ext.Ajax.request({
                            url: Routing.generate('iperson1337_opendxp_pricat_position_decline', {id: this.positionId}),
                            method: 'POST',
                            params: {comment: form.getForm().findField('comment').getValue()},
                            success: function (response) {
                                declineWindow.close();
                                this.handleResult(Ext.decode(response.responseText));
                            }.bind(this),
                            failure: this.handleTransportFailure.bind(this)
                        });
                    }.bind(this)
                },
                {
                    text: t('plugin_pimcore_pricat_btn_cancel'),
                    handler: function () {
                        declineWindow.close();
                    }
                }
            ]
        });

        declineWindow.show();
    },

    handleResult: function (result) {
        if (!result.success) {
            Ext.Msg.alert(t('plugin_pimcore_pricat_message'), result.message);
            return;
        }
        if (this.window) {
            this.window.close();
        }
        opendxp.helpers.showNotification(t('plugin_pimcore_pricat_message'), result.message, 'success');
        if (Ext.isFunction(this.onProcessed)) {
            this.onProcessed();
        }
    },

    handleTransportFailure: function (response) {
        Ext.Msg.alert(t('plugin_pimcore_pricat_message'), response.statusText || 'HTTP ' + response.status);
    }
});
