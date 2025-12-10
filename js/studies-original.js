/**
 * studies-original.js
 * Original studies functionality moved to external file
 */

// Export buttons functionality
$('#json').on('click', function () {
    $("#Table1").tableHTMLExport({ type: 'json', filename: 'Studies.json', ignoreColumns: "SELECT" });
});

$('#csv').on('click', function () {
    $("#Table1").tableHTMLExport({ type: 'csv', filename: 'Studies.csv' });
});

// Attach event handler to Combine Studies button
$('#combineStudiesBtn').on('click', function () {
    GetSelected();
});

// Combine studies functionality
function GetSelected() {
    // Reference the Table
    var grid = document.getElementById("Table1");

    if (!grid) {
        console.error('Table1 not found');
        alert('Error: Study table not found');
        return;
    }

    // Reference the CheckBoxes in Table
    var checkBoxes = grid.getElementsByTagName("input");
    var message = "Id Studies\n";

    $("#display").html("Processing....");
    var map1 = new Map();

    // Loop through the CheckBoxes
    for (var i = 0; i < checkBoxes.length; i++) {
        if (checkBoxes[i].checked) {
            var row = checkBoxes[i].parentNode.parentNode;
            var compId = row.cells[2].innerText;
            var newId = compId.replaceAll(":", "___");
            var studiesStr = row.cells[3].innerText;
            map1.set(newId, studiesStr);
        }
    }

    // Check if any metabolites were selected
    if (map1.size === 0) {
        $("#display").html("");
        alert('Please select at least one metabolite');
        return;
    }

    var obj = Object.fromEntries(map1);
    var objStr = encodeURIComponent(JSON.stringify(obj));

    // Get session data from body data attributes
    var $body = $('body');
    var species = $body.attr('data-species') || '';
    var geneList = $body.attr('data-genelist') || '';
    var geneIDType = $body.attr('data-geneidtype') || '';
    var disease = $body.attr('data-disease') || '';
    var anatomy = $body.attr('data-anatomy') || '';
    var phenotype = $body.attr('data-phenotype') || '';
    var baseDir = $body.attr('data-basedir') || '';

    console.log('Combining studies for:', map1.size, 'metabolites');

    $.ajax({
        url: baseDir + '/combineStudies.php',
        type: 'get',
        data: { metabolites: objStr },
        success: function () {
            window.location.href = baseDir + "/combineStudies.php?metabolites=" + objStr +
                "&GeneInfoStr=" + geneList +
                "&GeneIDType=" + geneIDType +
                "&species=" + species +
                "&disease=" + disease +
                "&anatomy=" + anatomy +
                "&phenotype=" + phenotype;
        },
        error: function (xhr, status, error) {
            console.error('AJAX error:', status, error);
            $("#display").html('<span style="color:red;">Error: Failed to combine studies. Please try again.</span>');
        }
    });
}