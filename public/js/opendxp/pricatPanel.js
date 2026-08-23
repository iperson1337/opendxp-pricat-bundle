opendxp.registerNS('opendxp.plugin.pricat.pricatPanel');

/**
 * Main "PRICAT" tab: paged grid of imported price lists + Import / Refresh toolbar.
 */
opendxp.plugin.pricat.pricatPanel = Class.create({

    panelId: 'opendxp_pricat_panel',

    initialize: function () {
        this.getTabPanel();
    },

    activate: function () {
        Ext.getCmp('opendxp_panel_tabs').setActiveItem(this.panelId);
    },

    reload: function () {
        this.store.reload();
    },

    getTabPanel: function () {
        if (this.panel) {
            return this.panel;
        }

        const settings = opendxp.plugin.pricat.settings();

        this.store = Ext.create('Ext.data.Store', {
            model: 'opendxp.plugin.pricat.model.Pricat',
            pageSize: settings.pageSize,
            remoteSort: false,
            autoLoad: true,
            proxy: {
                type: 'ajax',
                url: Routing.generate('iperson1337_opendxp_pricat_list'),
                reader: {
                    type: 'json',
                    rootProperty: 'data',
                    totalProperty: 'total'
                }
            }
        });

        this.grid = Ext.create('Ext.grid.Panel', {
            store: this.store,
            border: false,
            viewConfig: {stripeRows: true},
            columns: [
                {text: t('plugin_pimcore_pricat_col_id'), dataIndex: 'id', width: 80},
                {text: t('plugin_pimcore_pricat_col_number'), dataIndex: 'number', width: 100},
                {text: t('plugin_pimcore_pricat_col_docflow'), dataIndex: 'docFlowId', flex: 3},
                {text: t('plugin_pimcore_pricat_col_date'), dataIndex: 'date', xtype: 'datecolumn', format: 'Y-m-d', width: 110},
                {text: t('plugin_pimcore_pricat_col_date_from'), dataIndex: 'dateFrom', xtype: 'datecolumn', format: 'Y-m-d', width: 110},
                {text: t('plugin_pimcore_pricat_col_date_to'), dataIndex: 'dateTo', xtype: 'datecolumn', format: 'Y-m-d', width: 110},
                {text: t('plugin_pimcore_pricat_col_supplier'), dataIndex: 'supplier', flex: 2},
                {text: t('plugin_pimcore_pricat_col_buyer'), dataIndex: 'buyer', flex: 2},
                {text: t('plugin_pimcore_pricat_col_action'), dataIndex: 'action', flex: 2},
                {text: t('plugin_pimcore_pricat_col_positions'), dataIndex: 'positionCount', width: 80, align: 'right'},
                {
                    text: t('plugin_pimcore_pricat_col_pending'),
                    dataIndex: 'pendingCount',
                    width: 90,
                    align: 'right',
                    renderer: function (value, meta) {
                        if (value > 0) {
                            meta.tdCls = 'pricat-cell-pending';
                        }
                        return value;
                    }
                },
                {text: t('plugin_pimcore_pricat_col_accepted'), dataIndex: 'acceptedCount', width: 80, align: 'right'},
                {text: t('plugin_pimcore_pricat_col_declined'), dataIndex: 'declinedCount', width: 80, align: 'right'},
                {
                    xtype: 'actioncolumn',
                    text: t('plugin_pimcore_pricat_col_actions'),
                    width: 80,
                    sortable: false,
                    menuDisabled: true,
                    items: [
                        {
                            iconCls: 'opendxp_icon_right',
                            tooltip: t('plugin_pimcore_pricat_btn_open_positions'),
                            handler: function (grid, rowIndex) {
                                this.openPositions(grid.getStore().getAt(rowIndex));
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
                    this.openPositions(record);
                }.bind(this)
            }
        });

        this.panel = new Ext.Panel({
            id: this.panelId,
            title: t('plugin_pimcore_pricat_tab_title'),
            iconCls: 'opendxp_icon_table',
            border: false,
            layout: 'fit',
            closable: true,
            items: [this.grid],
            tbar: [
                {
                    text: t('plugin_pimcore_pricat_btn_import'),
                    iconCls: 'opendxp_icon_import',
                    handler: function () {
                        new opendxp.plugin.pricat.importWindow(this.reload.bind(this)).show();
                    }.bind(this)
                },
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
            opendxp.globalmanager.remove(opendxp.plugin.pricat.pricatPanel.KEY);
        });

        opendxp.layout.refresh();

        return this.panel;
    },

    openPositions: function (record) {
        opendxp.plugin.pricat.positionsPanel.open(record.get('id'), record.get('number'));
    }
});

opendxp.plugin.pricat.pricatPanel.KEY = 'plugin_pricat_panel';

opendxp.plugin.pricat.pricatPanel.open = function () {
    const key = opendxp.plugin.pricat.pricatPanel.KEY;
    if (opendxp.globalmanager.exists(key)) {
        opendxp.globalmanager.get(key).activate();
        return;
    }
    opendxp.globalmanager.add(key, new opendxp.plugin.pricat.pricatPanel());
};
