/**
 * table-export-init.js
 * Universal initialization for table export across all MetGENE pages
 */
$(document).ready(function () {
    var configJson = $('#export-buttons').attr('data-export-config');

    if (configJson) {
        try {
            var config = JSON.parse(configJson);
            MetGENE.initTableExport(config);
        } catch (e) {
            console.error('Export init failed:', e);
        }
    } else {
        console.warn('No export configuration found on this page');
    }
});