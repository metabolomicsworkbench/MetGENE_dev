<?php
/**
 * contact.php - Contact information page
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
<title>MetGENE: Contact</title>

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
    line-height: 1.6; 
}
</style>
</head>

<body>

<div id="constrain">
    <div class="constrain">

        <h1>Contact</h1>

        <p style="margin:25px;">
            <strong>MetGENE – Gene-centric Information Retrieval Tool</strong>
            <br>c/o Dr. Shankar Subramaniam, Ph.D.
            <br>9500 Gilman Drive
            <br>La Jolla, CA 92093
            <br>Phone: 858-822-0986
            <br>E-mail: <a href="mailto:shankar@ucsd.edu">shankar@ucsd.edu</a>

            <br><br>
            <i>Please address questions about MetGENE to
                <a href="mailto:susrinivasan@ucsd.edu">susrinivasan@ucsd.edu</a>,
                <a href="mailto:mano@sdsc.edu">mano@sdsc.edu</a>
            </i>
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