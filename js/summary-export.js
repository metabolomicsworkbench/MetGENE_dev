/**
 * summary-export.js
 * Export functionality for summary page
 */

$(document).ready(function () {
    $('#json').on('click', function () {
        // Export #Table1 to JSON
        $('#Table1').tableHTMLExport({
            type: 'json',
            filename: 'Summary.json'
        });
    });

    $('#csv').on('click', function () {
        // Export #Table1 to CSV
        $('#Table1').tableHTMLExport({
            type: 'csv',
            filename: 'Summary.csv'
        });
    });
});