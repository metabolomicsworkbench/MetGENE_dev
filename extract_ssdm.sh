#!/bin/bash
#
# Get sample source list from the php/html code like
#<option value=""></option>
#<option value="Adipose tissue">Adipose tissue</option>

grep -o 'value="[^"]*"' ssdm_sample_source_pulldown_menu_phpcode.php | sed 's/value="//; s/"$//' > ssdm_sample_source.txt

