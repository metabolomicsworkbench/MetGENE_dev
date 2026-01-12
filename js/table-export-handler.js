/**
 * table-export-handler.js
 * Universal table export for MetGENE
 */
(function ($) {
    'use strict';


    window.MetGENE = window.MetGENE || {};

    window.MetGENE.initTableExport = function (config) {
        var settings = $.extend({
            entityType: 'Data',
            geneArray: [],
            tableIdPrefix: 'Gene',
            tableIdSuffix: 'Table'
        }, config);

        console.log('MetGENE Export initialized:', settings);

        if (typeof $.fn.tableHTMLExport === 'undefined') {
            console.error('tableHTMLExport plugin not loaded!');
            return false;
        }

        function findTable(geneId, index) {
            var selectors = [
                '#' + settings.tableIdPrefix + geneId + settings.tableIdSuffix,
                '#' + settings.tableIdPrefix.toLowerCase() + geneId + settings.tableIdSuffix.toLowerCase(),
                '#Table' + (index + 1)
            ];

            for (var i = 0; i < selectors.length; i++) {
                var $table = $(selectors[i]);
                if ($table.length > 0) {
                    return $table;
                }
            }
            return null;
        }

        function exportAll(type) {
            var count = 0;
            $('table').each(function (i) {
                $(this).tableHTMLExport({
                    type: type,
                    filename: settings.entityType + '_' + (i + 1) + '.' + type
                });
                count++;
            });

            if (count === 0) {
                alert('No tables found');
            }
        }

        function handleExport(type) {

            if (settings.geneArray.length === 0) {
                exportAll(type);
                return;
            }

            var exported = 0;
            for (var i = 0; i < settings.geneArray.length; i++) {
                var geneId = settings.geneArray[i];
                if (!geneId || geneId === 'NA') continue;

                var $table = findTable(geneId, i);
                if ($table) {
                    $table.tableHTMLExport({
                        type: type,
                        filename: settings.tableIdPrefix + geneId + settings.entityType + '.' + type
                    });
                    exported++;
                }
            }

            if (exported === 0) {
                exportAll(type);
            }
        }

        $('#json').on('click', function () { handleExport('json'); });
        $('#csv').on('click', function () { handleExport('csv'); });

        return true;
    };

})(jQuery);