<?php
/**
 * termsofuse.php - Terms of Use page
 * Security hardened using metgene_common.php functions
 */

// SECURITY FIX: Start session and load helpers BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/metgene_common.php");

// SECURITY FIX: Send security headers
sendSecurityHeaders();

// SECURITY FIX: Use getBaseDirName() function
$base_dir = getBaseDirName();

// Define esc() wrapper for convenience
if (!function_exists('esc')) {
    function esc(string $v): string {
        return escapeHtml($v);
    }
}

/* Restore session variables (for navigation state) */
$species   = $_SESSION['species']   ?? '';
$geneList  = $_SESSION['geneList']  ?? '';
$disease   = $_SESSION['disease']   ?? '';
$anatomy   = $_SESSION['anatomy']   ?? '';
$phenotype = $_SESSION['phenotype'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Terms of Use</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= esc($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= esc($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= esc($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= esc($base_dir) ?>/site.webmanifest">

<?php
// SECURITY FIX: Validate nav.php path with realpath
$nav_file = realpath(__DIR__ . '/nav.php');
if ($nav_file !== false && strpos($nav_file, __DIR__) === 0 && is_readable($nav_file)) {
    include $nav_file;
}
?>

<style>
#constrain {
    max-width: 950px;
    margin: auto;
}
h1 { 
    margin-top: 30px; 
}
p { 
    font-size: 16px; 
    line-height: 1.5; 
}
</style>
</head>

<body>

<div id="constrain">
    <div class="constrain">
        <h1>Terms of Use</h1>

        <p style="margin:25px;">
            MetGENE tool is provided by the Metabolomics Workbench Data Coordination Center (DCC)
            of the NIH Common Fund Data Ecosystem (CFDE) on an "as is" basis, without warranty or
            representation of any kind, express or implied. The content of the tool is protected by
            international copyright, trademark and other laws.
            <br><br>
            You may download outputs (such as tables and web pages) from this site for your personal,
            non-commercial use only, provided that you keep intact all authorship, copyright and
            other proprietary notices.
            <br><br>
            If you use this tool, you accept these terms. MW DCC reserves the right to modify these
            terms at any time.
        </p>
    </div>
</div>

<?php
// SECURITY FIX: Validate footer.php path with realpath
$footer_file = realpath(__DIR__ . '/footer.php');
if ($footer_file !== false && strpos($footer_file, __DIR__) === 0 && is_readable($footer_file)) {
    include $footer_file;
}
?>

</body>
</html>