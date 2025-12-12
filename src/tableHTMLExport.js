/*The MIT License (MIT)

Copyright (c) 2018 https://github.com/FuriosoJack/TableHTMLExport

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.*/

(function ($) {

    $.fn.extend({
        tableHTMLExport: function (options) {

            var defaults = {
                separator: ',',
                newline: '\r\n',
                ignoreColumns: '',
                ignoreRows: '',
                type: 'csv',
                htmlContent: false,
                consoleLog: false,
                trimContent: true,
                quoteFields: true,
                filename: 'tableHTMLExport.csv',
                utf8BOM: true,
                orientation: 'p' //only when exported to *pdf* "portrait" or "landscape" (or shortcuts "p" or "l")
            };
            var options = $.extend(defaults, options);

            // DEBUG: Log that plugin is loaded
            console.log('🔵 tableHTMLExport ENHANCED VERSION LOADED');
            console.log('🔵 Export type:', options.type);
            console.log('🔵 Filename:', options.filename);

            function quote(text) {
                return '"' + text.replace(/"/g, '""') + '"';
            }


            /**
             * SECURITY FIX: Enhanced parseString to extract from img alt attributes
             * Priority: img alt > link href > link text > cell text
             */
            function parseString(data) {
                var content_data;

                if (defaults.htmlContent) {
                    content_data = data.html().trim();
                } else {
                    // SECURITY FIX: Check for images first (for METSTAT_LINK columns)
                    var $img = data.find('img').first();
                    if ($img.length > 0) {
                        // Image found - use alt attribute
                        content_data = $img.attr('alt') || '';
                        console.log('🟢 parseString: Found IMG, alt="' + content_data.substring(0, 50) + '..."');

                        if (content_data === '') {
                            // Fallback: try to get href from parent link
                            var $link = $img.closest('a');
                            if ($link.length > 0) {
                                content_data = $link.attr('href') || '';
                                console.log('🟡 parseString: Using parent link href="' + content_data.substring(0, 50) + '..."');
                            }
                        }
                    } else {
                        // No image - check for links
                        var $link = data.find('a').first();
                        if ($link.length > 0) {
                            var linkText = $link.text().trim();
                            var linkHref = $link.attr('href');

                            // For links, prefer text content but include href if different
                            if (linkHref && linkHref !== linkText && !linkHref.startsWith('javascript:')) {
                                content_data = linkText;
                                console.log('🟢 parseString: Found LINK, text="' + linkText + '"');
                            } else {
                                content_data = linkText;
                            }
                        } else {
                            // Plain text content
                            content_data = data.text().trim();
                        }
                    }
                }

                return content_data;
            }

            function download(filename, text) {
                console.log('📥 Downloading file:', filename);
                console.log('📥 Content length:', text.length, 'characters');

                var element = document.createElement('a');
                element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(text));
                element.setAttribute('download', filename);

                element.style.display = 'none';
                document.body.appendChild(element);

                element.click();

                document.body.removeChild(element);
            }

            /**
             * SECURITY FIX: Enhanced toJson to return array of objects
             * Format: [{col1: val1, col2: val2}, {col1: val3, col2: val4}, ...]
             * @param el
             * @returns {Array}
             */
            function toJson(el) {
                console.log('🔵 toJson: Starting JSON conversion');

                var headers = [];

                // Extract headers from first row (thead or first tr)
                var $headerRow = $(el).find('thead tr').first();
                if ($headerRow.length === 0) {
                    $headerRow = $(el).find('tr').first();
                    console.log('🟡 toJson: Using first TR as header (no THEAD found)');
                } else {
                    console.log('🟢 toJson: Using THEAD for headers');
                }

                $headerRow.find('th, td').not(options.ignoreColumns).each(function () {
                    if ($(this).css('display') != 'none') {
                        var headerText = parseString($(this));
                        headers.push(headerText);
                        console.log('🟢 toJson: Header found:', headerText);
                    }
                });

                console.log('🔵 toJson: Total headers:', headers.length, '→', headers);

                var jsonArray = [];
                var $tbody = $(el).find('tbody');
                var $rows;

                if ($tbody.length > 0) {
                    $rows = $tbody.find('tr').not(options.ignoreRows);
                    console.log('🟢 toJson: Found TBODY with', $rows.length, 'rows');
                } else {
                    // No tbody, use all rows except first (header)
                    $rows = $(el).find('tr').not(options.ignoreRows).slice(1);
                    console.log('🟡 toJson: No TBODY, using', $rows.length, 'TR rows (skipping first)');
                }

                $rows.each(function (rowIndex) {
                    var rowObject = {};
                    var colIndex = 0;

                    $(this).find('td').not(options.ignoreColumns).each(function () {
                        if ($(this).css('display') != 'none') {
                            var header = headers[colIndex] || 'Column' + (colIndex + 1);
                            var cellValue = parseString($(this));
                            rowObject[header] = cellValue;

                            // DEBUG: Log METSTAT_LINK values specifically
                            if (header === 'METSTAT_LINK') {
                                console.log('🟢 toJson Row', rowIndex, '- METSTAT_LINK:', cellValue.substring(0, 80) + '...');
                            }

                            colIndex++;
                        }
                    });

                    // Only add non-empty rows
                    if (Object.keys(rowObject).length > 0) {
                        jsonArray.push(rowObject);
                    }
                });

                console.log('🔵 toJson: Total rows converted:', jsonArray.length);
                return jsonArray;
            }


            /**
             * SECURITY FIX: Enhanced toCsv to properly extract image alt attributes
             * Convierte la tabla enviada a csv o texto
             * @param table
             * @returns {string}
             */
            function toCsv(table) {
                console.log('🔵 toCsv: Starting CSV conversion');

                var output = "";

                if (options.utf8BOM === true) {
                    output += '\ufeff';
                }

                var rows = table.find('tr').not(options.ignoreRows);
                console.log('🔵 toCsv: Processing', rows.length, 'rows');

                var numCols = rows.first().find("td,th").not(options.ignoreColumns).length;

                rows.each(function (rowIndex) {
                    var colCount = 0;
                    $(this).find("td,th").not(options.ignoreColumns)
                        .each(function (i, col) {
                            var column = $(col);

                            if (column.css('display') === 'none') {
                                return; // skip hidden columns
                            }

                            // SECURITY FIX: Use enhanced parseString
                            var content = parseString(column);

                            // Trim if option is set
                            if (options.trimContent) {
                                content = content.trim();
                            }

                            output += options.quoteFields ? quote(content) : content;

                            if (colCount < numCols - 1) {
                                output += options.separator;
                            }
                            colCount++;
                        });
                    output += options.newline;
                });

                console.log('🔵 toCsv: CSV output length:', output.length, 'characters');
                return output;
            }


            var el = this;
            var dataMe;

            if (options.type == 'csv' || options.type == 'txt') {
                console.log('🔵 Export mode: CSV/TXT');

                var table = this.filter('table'); // TODO use $.each

                if (table.length <= 0) {
                    throw new Error('tableHTMLExport must be called on a <table> element')
                }

                if (table.length > 1) {
                    throw new Error('converting multiple table elements at once is not supported yet')
                }

                dataMe = toCsv(table);

                if (defaults.consoleLog) {
                    console.log(dataMe);
                }

                download(options.filename, dataMe);

            } else if (options.type == 'json') {
                console.log('🔵 Export mode: JSON');

                var jsonExportArray = toJson(el);

                if (defaults.consoleLog) {
                    console.log(JSON.stringify(jsonExportArray, null, 2));
                }

                // SECURITY FIX: Pretty print JSON with 2-space indentation
                dataMe = JSON.stringify(jsonExportArray, null, 2);

                console.log('✅ JSON export complete:', jsonExportArray.length, 'records');

                download(options.filename, dataMe);

            } else if (options.type == 'pdf') {
                console.log('🔵 Export mode: PDF');

                var jsonExportArray = toJson(el);

                // For PDF, convert array of objects to {header, data} format
                var headers = [];
                var dataRows = [];

                if (jsonExportArray.length > 0) {
                    headers = Object.keys(jsonExportArray[0]);

                    jsonExportArray.forEach(function (row) {
                        var rowArray = [];
                        headers.forEach(function (header) {
                            rowArray.push(row[header] || '');
                        });
                        dataRows.push(rowArray);
                    });
                }

                var contentJsPdf = {
                    head: [headers],
                    body: dataRows
                };

                if (defaults.consoleLog) {
                    console.log(contentJsPdf);
                }

                var doc = new jsPDF(defaults.orientation, 'pt');
                doc.autoTable(contentJsPdf);
                doc.save(options.filename);

            }

            console.log('✅ tableHTMLExport: Export complete!');
            return this;
        }
    });
})(jQuery);