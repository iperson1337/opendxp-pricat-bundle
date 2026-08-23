/**
 * Ext data models mirroring the rows returned by PricatController / PositionController.
 */
Ext.define('opendxp.plugin.pricat.model.Pricat', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'id', type: 'int'},
        {name: 'number', type: 'string'},
        {name: 'docFlowId', type: 'string'},
        {name: 'date', type: 'date', dateFormat: 'Y-m-d'},
        {name: 'dateFrom', type: 'date', dateFormat: 'Y-m-d'},
        {name: 'dateTo', type: 'date', dateFormat: 'Y-m-d'},
        {name: 'timeFrom', type: 'string'},
        {name: 'supplier', type: 'string'},
        {name: 'buyer', type: 'string'},
        {name: 'vat', type: 'auto'},
        {name: 'actionCode', type: 'auto'},
        {name: 'action', type: 'string'},
        {name: 'sender', type: 'string'},
        {name: 'recipient', type: 'string'},
        {name: 'totalRejectionComment', type: 'string'},
        {name: 'positionCount', type: 'int'},
        {name: 'acceptedCount', type: 'int'},
        {name: 'declinedCount', type: 'int'},
        {name: 'processedCount', type: 'int'},
        {name: 'pendingCount', type: 'int'}
    ]
});

Ext.define('opendxp.plugin.pricat.model.Position', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'id', type: 'int'},
        {name: 'positionNumber', type: 'auto'},
        {name: 'actionCode', type: 'auto'},
        {name: 'action', type: 'string'},
        {name: 'product', type: 'string'},
        {name: 'productName', type: 'string'},
        {name: 'region', type: 'string'},
        {name: 'processed', type: 'boolean'},
        {name: 'confirmation', type: 'auto'},
        {name: 'confirmationLabel', type: 'string'},
        {name: 'rejectionComment', type: 'string'}
    ]
});

Ext.define('opendxp.plugin.pricat.model.CompareRow', {
    extend: 'Ext.data.Model',
    fields: [
        {name: 'positionField', type: 'string'},
        {name: 'productField', type: 'string'},
        {name: 'label', type: 'string'},
        {name: 'positionValue', type: 'auto'},
        {name: 'productValue', type: 'auto'},
        {name: 'isEqual', type: 'boolean'},
        {name: 'isProductEmpty', type: 'boolean'},
        {name: 'isPositionEmpty', type: 'boolean'},
        {name: 'writable', type: 'boolean'},
        {name: 'apply', type: 'boolean', defaultValue: false}
    ]
});
