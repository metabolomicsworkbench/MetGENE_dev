  <!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<style type='text/css' media='screen, projection, print'>
@import "css/header.css";
@import "css/layout.css";
@import "css/style_layout.css";
@import "css/main.css";
@import "css/site.css";
@import "css/layout_2col.css";	@import "css/966px.css";

#hdr 
<?php
  $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));                                                        $METGENE_BASE_DIR_NAME = $curDirPath;
?>
.login-nav {
    background-color: #ffffff;
    background-image: <?php echo "url('".$METGENE_BASE_DIR_NAME."/images/MetGeneBanner.png');";?>
    background-size: 100%;
}
.topnav ul {
    list-style-type: none;
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.topnav li {
    float: left;
}

.topnav li a .dropbtn{
    display: inline;
    color: #215EA9;
	text-align: center;
	text-decoration: none;

}
.dropdown {
  float: left;
  overflow: hidden;
}

.dropdown .dropbtn{
  font-size: 16px;  
  border: none;
  color: #215EA9;
  padding: 8px 12px;  
  
  background-color: #eeeeee;
	font-size: 100%;
	margin-right:2px;
	padding: 8px 12px;
	font-weight: normal;
  
  }	

.dropdown-content {
  display: none;
  position: absolute;
  background-color: #f9f9f9;
 
}
.dropdown-content a {
  float: none;
  color: black;
  padding: 8px 12px;
  text-decoration: none;
  display: block;
  text-align: left;
}

.dropdown-content a:hover {
  background-color: #ddd;
}

/* Show the dropdown menu on hover */
.dropdown:hover .dropdown-content {
  display: block;
}

.styled-table {
    display:table;
    table-layout:fixed;
    width: 100%;
    word-wrap: break-word;

}

.styled-table td {
/*    border: 1px solid #000;*/
    padding:5px 10px;
    width: 3%;
    word-break: break-all;
    white-space: pre-line;
}
.styled-table tbody tr {
    border-bottom: 1px solid #dddddd;
}

.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}
/*
table {
    border-collapse:collapse;
    table-layout:fixed;
}

td{
    border:1px solid #ccc;
    padding:5px 10px;
    vertical-align:top;
    word-break:break-word;
    white-space: pre-line;
}*/
/*table, th, td {
  border: 1px solid black;
  word-wrap: break-word;
  width:100%;
}
*/
</style>

<style type="text/css">

</style>

<body>
<div id="constrain">
  <div class="constrain">
    <div id="hdr">
	<span class="cleardiv"><!-- --></span>
	<span class="cleardiv"><!-- --></span>
	<div class="login-nav"><div style="text-align:right; vertical-align: text-bottom; height:1.1cm; padding-right: 1.0em; padding-top: 1.0em;">
        </div>
        <div style="text-align:right; position: absolute; top:80px;right:2px;">


</div>

 <span class="cleardiv"><!-- --></span>
					</div>
				</div>
			</div>
		</div>
		</body>
		</html>


