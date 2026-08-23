opendxp.registerNS('opendxp.plugin.pricat');

/**
 * Bundle settings pushed by AdminSettingsSubscriber into opendxp.settings.pricat.
 */
opendxp.plugin.pricat.settings = function () {
    return Ext.apply({permission: 'pricat_bundle', pageSize: 25}, (opendxp.settings && opendxp.settings.pricat) || {});
};

opendxp.plugin.pricat.plugin = Class.create({

    initialize: function () {
        document.addEventListener(opendxp.events.preMenuBuild, this.addToMenu.bind(this));
    },

    hasPermission: function () {
        const user = opendxp.currentuser;
        if (!user) {
            return false;
        }
        return user.admin === true || (user.permissions || []).indexOf(opendxp.plugin.pricat.settings().permission) >= 0;
    },

    addToMenu: function (e) {
        if (!this.hasPermission()) {
            return;
        }

        e.detail.menu.pricat = {
            label: t('plugin_pimcore_pricat_menu'),
            iconCls: 'opendxp_nav_icon_commerce_pricing_rules',
            priority: 42,
            items: [],
            shadow: false,
            handler: function () {
                opendxp.plugin.pricat.pricatPanel.open();
            },
            noSubmenus: true,
            cls: 'opendxp_navigation_flyout'
        };
    }
});

new opendxp.plugin.pricat.plugin();
