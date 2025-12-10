/**
 * combineStudies-export.js
 * Export functionality for combined studies page
 */

$('#json').on('click', function() {
    $("#Table1").tableHTMLExport({type:'json', filename:'combinedStudies.json'});
});

$('#csv').on('click', function() {
    $("#Table1").tableHTMLExport({type:'csv', filename:'combinedStudies.csv'});
});
