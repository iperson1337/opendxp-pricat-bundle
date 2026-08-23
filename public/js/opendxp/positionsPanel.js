opendxp.registerNS('opendxp.plugin.pricat.positionsPanel');

/**
 * Tab with the positions of one PRICAT: header (number, supplier, validity, counters), a status
 * filter (pending by default) and a server-side paged grid. One instance per pricat id, registered
 * in the globalmanager so re-opening activates the existing tab.
 */
opendxp.plugin.pricat.positionsPanel = Class.create({

    initialize: function (pricatId, pricatNumber) {
        this.pricatId = pricatId;
        this.pricatNumber = pricatNumber;
        this.panelId = 'opendxp_pricat_positions_' + pricatId;
        this.getTabPanel();
    },

    activate: function () {
        Ext.getCmp('opendxp_panel_tabs').setActiveItem(this.panelId);
    },

    reload: function () {
        this.store.reload();
        this.loadHeader();
        // counters in the pricat list are derived from the same data
        const key = opendxp.plugin.pricat.pricatPanel.KEY;
        if (opendxp.globalmanager.exists(key)) {
            opendxp.globalmanager.get(key).reload();
        }
    },

    loadHeader: function () {
        Ext.Ajax.request({
            url: Routing.generate('iperson1337_opendxp_pricat_show', {id: this.pricatId}),
            method: 'GET',
            success: function (response) {
                const result = Ext.decode(response.responseText);
                if (result.success) {
                    this.header.update(this.renderHeader(result.data));
                }
            }.bind(this)
        });
    },

    renderHeader: function (p) {
        const enc = Ext.util.Format.htmlEncode;
        const dash = function (value) {
            return value === null || value === undefined || value === '' ? '—' : enc(value);
        };
        const field = function (label, value) {
            return '<b>' + t(label) + ':</b> ' + dash(value) + '&nbsp;&nbsp; ';
        };
        const counter = function (label, value, cls) {
            return '<span class="pricat-counter ' + (cls || '') + '">' + t(label) + ': <b>' + value + '</b></span>';
        };

        return '<div>'
            + field('plugin_pimcore_pricat_col_number', p.number)
            + field('plugin_pimcore_pricat_col_action', p.action)
            + field('plugin_pimcore_pricat_col_date', p.date)
            + field('plugin_pimcore_pricat_header_validity', (p.dateFrom || '—') + ' … ' + (p.dateTo || '—'))
            + field('plugin_pimcore_pricat_header_vat', p.vat)
            + '</div><div>'
            + field('plugin_pimcore_pricat_col_supplier', p.supplier)
            + field('plugin_pimcore_pricat_col_buyer', p.buyer)
            + field('plugin_pimcore_pricat_col_docflow', p.docFlowId)
            + '</div><div>'
            + counter('plugin_pimcore_pricat_col_positions', p.positionCount)
            + counter('plugin_pimcore_pricat_col_pending', p.pendingCount)
            + counter('plugin_pimcore_pricat_col_accepted', p.acceptedCount)
            + counter('plugin_pimcore_pricat_col_declined', p.declinedCount)
            + (p.totalRejectionComment ? '&nbsp;&nbsp; ' + field('plugin_pimcore_pricat_header_total_rejection', p.totalRejectionComment) : '')
            + '</div>';
    },

    getTabPanel: function () {
        if (this.panel) {
            return this.panel;
        }

        const settings = opendxp.plugin.pricat.settings();

        this.store = Ext.create('Ext.data.Store', {
            model: 'opendxp.plugin.pricat.model.Position',
            pageSize: settings.pageSize,
            remoteSort: false,
            autoLoad: true,
            proxy: {
                type: 'ajax',
                url: Routing.generate('iperson1337_opendxp_pricat_positions', {id: this.pricatId}),
                extraParams: {filter: 'pending'},
                reader: {
                    type: 'json',
                    rootProperty: 'data',
                    totalProperty: 'total'
                }
            }
        });

        this.header = Ext.create('Ext.Component', {
            cls: 'pricat-positions-header',
            html: '…'
        });

        this.filter = Ext.create('Ext.form.field.ComboBox', {
            fieldLabel: t('plugin_pimcore_pricat_filter'),
            labelWidth: 60,
            width: 260,
            editable: false,
            queryMode: 'local',
            displayField: 'label',
            valueField: 'value',
            value: 'pending',
            store: Ext.create('Ext.data.Store', {
                fields: ['value', 'label'],
                data: [
                    {value: 'pending', label: t('plugin_pimcore_pricat_filter_pending')},
                    {value: 'all', label: t('plugin_pimcore_pricat_filter_all')},
                    {value: 'accepted', label: t('plugin_pimcore_pricat_filter_accepted')},
                    {value: 'declined', label: t('plugin_pimcore_pricat_filter_declined')}
                ]
            }),
            listeners: {
                change: function (combo, value) {
                    this.store.getProxy().setExtraParam('filter', value);
                    this.store.loadPage(1);
                }.bind(this)
            }
        });

        this.grid = Ext.create('Ext.grid.Panel', {
            store: this.store,
            border: false,
            viewConfig: {
                stripeRows: true,
                getRowClass: function (record) {
                    if (!record.get('processed')) {
                        return '';
                    }
                    return record.get('confirmation') ? 'pricat-row-accepted' : 'pricat-row-declined';
                }
            },
            columns: [
                {text: t('plugin_pimcore_pricat_col_id'), dataIndex: 'id', width: 80},
                {text: t('plugin_pimcore_pricat_col_position_number'), dataIndex: 'positionNumber', width: 60},
                {text: t('plugin_pimcore_pricat_col_action'), dataIndex: 'action', width: 110},
                {text: t('plugin_pimcore_pricat_col_product'), dataIndex: 'product', width: 150},
                {text: t('plugin_pimcore_pricat_col_product_name'), dataIndex: 'productName', flex: 3},
                {text: t('plugin_pimcore_pricat_col_region'), dataIndex: 'region', width: 80},
                {
                    text: t('plugin_pimcore_pricat_col_processed'),
                    dataIndex: 'processed',
                    width: 100,
                    renderer: function (value) {
                        return value ? t('plugin_pimcore_pricat_yes') : t('plugin_pimcore_pricat_no');
                    }
                },
                {text: t('plugin_pimcore_pricat_col_confirmation'), dataIndex: 'confirmationLabel', width: 120},
                {text: t('plugin_pimcore_pricat_col_rejection_comment'), dataIndex: 'rejectionComment', flex: 2},
                {
                    xtype: 'actioncolumn',
                    text: t('plugin_pimcore_pricat_col_actions'),
                    width: 80,
                    sortable: false,
                    menuDisabled: true,
                    items: [
                        {
                            iconCls: 'opendxp_icon_edit',
                            tooltip: t('plugin_pimcore_pricat_btn_compare'),
                            isDisabled: function (view, rowIndex, colIndex, item, record) {
                                return record.get('processed');
                            },
                            handler: function (grid, rowIndex) {
                                this.openCompare(grid.getStore().getAt(rowIndex));
                            }.bind(this)
                        }
                    ]
                }
            ],
            bbar: Ext.create('Ext.toolbar.Paging', {
                store: this.store,
                displayInfo: true
            }),
            listeners: {
                itemdblclick: function (view, record) {
                    this.openCompare(record);
                }.bind(this)
            }
        });

        this.panel = new Ext.Panel({
            id: this.panelId,
            title: t('plugin_pimcore_pricat_tab_pricat') + (this.pricatNumber || this.pricatId),
            iconCls: 'opendxp_icon_table',
            border: false,
            layout: 'border',
            closable: true,
            items: [
                Ext.apply(this.header, {region: 'north'}),
                Ext.apply(this.grid, {region: 'center'})
            ],
            tbar: [
                this.filter,
                '->',
                {
                    text: t('plugin_pimcore_pricat_btn_refresh'),
                    iconCls: 'opendxp_icon_refresh',
                    handler: this.reload.bind(this)
                }
            ]
        });

        const tabPanel = Ext.getCmp('opendxp_panel_tabs');
        tabPanel.add(this.panel);
        tabPanel.setActiveItem(this.panel);

        this.panel.on('destroy', function () {
            opendxp.globalmanager.remove(opendxp.plugin.pricat.positionsPanel.key(this.pricatId));
        }.bind(this));

        opendxp.layout.refresh();
        this.loadHeader();

        return this.panel;
    },

    openCompare: function (record) {
        if (record.get('processed')) {
            Ext.Msg.alert(t('plugin_pimcore_pricat_message'), t('plugin_pimcore_pricat_error_position_processed').replace('%id%', record.get('id')));
            return;
        }
        new opendxp.plugin.pricat.compareWindow(record.get('id'), this.reload.bind(this)).show();
    }
});

opendxp.plugin.pricat.positionsPanel.key = function (pricatId) {
    return 'plugin_pricat_positions_' + pricatId;
};

opendxp.plugin.pricat.positionsPanel.open = function (pricatId, pricatNumber) {
    const key = opendxp.plugin.pricat.positionsPanel.key(pricatId);
    if (opendxp.globalmanager.exists(key)) {
        opendxp.globalmanager.get(key).activate();
        return;
    }
    opendxp.globalmanager.add(key, new opendxp.plugin.pricat.positionsPanel(pricatId, pricatNumber));
};
