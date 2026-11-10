opendxp.registerNS('opendxp.plugin.pricat.importWindow');

/**
 * Modal "upload a PRICAT XML" dialog. `onImported(result)` is called after a successful import.
 */
opendxp.plugin.pricat.importWindow = Class.create({

    initialize: function (onImported) {
        this.onImported = onImported;
    },

    show: function () {
        this.form = Ext.create('Ext.form.Panel', {
            bodyPadding: 10,
            border: false,
            items: [
                {
                    xtype: 'filefield',
                    name: 'file',
                    fieldLabel: t('plugin_pimcore_pricat_import_file'),
                    labelWidth: 140,
                    anchor: '100%',
                    allowBlank: false,
                    buttonText: '…'
                },
                {
                    xtype: 'hiddenfield',
                    name: 'csrfToken',
                    value: opendxp.settings['csrfToken']
                }
            ]
        });

        this.window = new Ext.Window({
            title: t('plugin_pimcore_pricat_import_title'),
            width: 480,
            modal: true,
            closable: true,
            layout: 'fit',
            items: [this.form],
            buttons: [
                {
                    text: t('plugin_pimcore_pricat_btn_upload'),
                    iconCls: 'opendxp_icon_upload',
                    handler: this.submit.bind(this)
                },
                {
                    text: t('plugin_pimcore_pricat_btn_cancel'),
                    handler: function () {
                        this.window.close();
                    }.bind(this)
                }
            ]
        });

        this.window.show();
    },

    submit: function () {
        const form = this.form.getForm();
        if (!form.isValid()) {
            return;
        }

        form.submit({
            url: Routing.generate('iperson1337_opendxp_pricat_import'),
            method: 'POST',
            waitMsg: t('plugin_pimcore_pricat_import_uploading'),
            success: function (fp, action) {
                this.window.close();
                const result = action.result || {};
                // server messages carry values taken from the uploaded file — they are text, never markup
                let message = result.message ? Ext.util.Format.htmlEncode(result.message) : t('plugin_pimcore_pricat_import_success');
                const unmapped = [].concat(
                    (result.data && result.data.unmappedHeaderTags) || [],
                    (result.data && result.data.unmappedPositionTags) || []
                );
                if (unmapped.length) {
                    message += '<br><br>' + t('plugin_pimcore_pricat_import_unmapped') + ': ' + Ext.util.Format.htmlEncode(unmapped.join(', '));
                }
                opendxp.helpers.showNotification(t('plugin_pimcore_pricat_message'), message, 'success');
                if (Ext.isFunction(this.onImported)) {
                    this.onImported(result.data || {});
                }
            }.bind(this),
            failure: function (fp, action) {
                const result = action.result || {};
                const message = result.message
                    ? Ext.util.Format.htmlEncode(result.message)
                    : t('plugin_pimcore_pricat_error_import_failed').replace('%error%', Ext.util.Format.htmlEncode(action.response ? action.response.statusText : ''));
                Ext.Msg.alert(t('plugin_pimcore_pricat_message'), message);
            }
        });
    }
});
