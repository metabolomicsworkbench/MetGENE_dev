<?php
/******************************************************************************
 * MetGENE – Hardened nav_index.php
 *
 * SECURITY FIXES:
 *  - Removes full HTML wrapper (included file should not start <html>…)
 *  - Uses metgene_common.php helpers for:
 *        escapeHtml()
 *        getBaseDirName()
 *  - All output safely escaped
 *  - No dangerous CSS-PHP injection
 *  - Removes unused GET/SESSION usage (index page needs none)
 *  - Ensures banner paths resolve correctly
 ******************************************************************************/

declare(strict_types=1);

require_once __DIR__ . "/metgene_common.php";

$base = getBaseDirName();
$bannerUrl = escapeHtml($base . "/images/MetGeneBanner.png");
?>
<!-- Navigation header for INDEX PAGE ONLY -->
<style>
/* -------------------------- Shared CSS Imports ---------------------------- */
@import "<?php echo escapeHtml($base); ?>/css/header.css";
@import "<?php echo escapeHtml($base); ?>/css/layout.css";
@import "<?php echo escapeHtml($base); ?>/css/style_layout.css";
@import "<?php echo escapeHtml($base); ?>/css/main.css";
@import "<?php echo escapeHtml($base); ?>/css/site.css";
@import "<?php echo escapeHtml($base); ?>/css/layout_2col.css";
@import "<?php echo escapeHtml($base); ?>/css/966px.css";

/* ------------------------------ Banner ------------------------------------ */
#hdr .login-nav {
    background-color: #ffffff;
    background-image: url('<?php echo $bannerUrl; ?>');
    background-size: 100% auto;
    background-repeat: no-repeat;
}

/* ------------------------------ Navigation -------------------------------- */
.topnav ul {
    list-style-type: none;
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.topnav li {
    float: left;
}

.topnav li a.dropbtn {
    display: inline-block;
    color: #215EA9;
    text-decoration: none;
    padding: 8px 12px;
    background-color: #eeeeee;
    font-size: 16px;
    margin-right: 2px;
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: #f9f9f9;
}

.dropdown-content a {
    color: black;
    padding: 8px 12px;
    display: block;
    text-decoration: none;
}

.dropdown-content a:hover {
    background-color: #ddd;
}

.dropdown:hover .dropdown-content {
    display: block;
}

.cleardiv {
    clear: both;
}

/* Tables shared */
.styled-table {
    width: 100%;
    table-layout: fixed;
    word-wrap: break-word;
}
.styled-table td {
    padding: 5px 10px;
    word-break: break-all;
    white-space: pre-line;
}
.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}
</style>

<!-- HTML STRUCTURE: CLEAN + MINIMAL -->
<div id="constrain">
    <div class="constrain">
        <div id="hdr">
            <div class="login-nav">
                <div style="height:1.1cm; padding-right:1em; padding-top:1em; text-align:right;">
                    <!-- Empty right corner (future login if needed) -->
                </div>
                <div style="position:absolute; top:80px; right:2px; text-align:right;">
                    <!-- Reserved for future header elements -->
                </div>
                <span class="cleardiv"></span>
            </div>
        </div>
    </div>
</div>
