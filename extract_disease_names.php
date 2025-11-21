<?php
/******************************************************************************
 * Secure Disease Name Extractor
 *
 * HARDENING FEATURES:
 *  - Prevent SSRF when loading remote URLs
 *  - Prevent directory traversal for local files
 *  - Safe JSON decoding and error handling
 *  - Validates disease_name fields for safe characters only
 *  - Prevents PHP warnings from revealing internal paths
 *  - Uses strict typing and exceptions
 *****************************************************************************/

declare(strict_types=1);

/**
 * Escape HTML helper (optional, for debugging or inline output).
 */
function escapeHtml(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Validate and sanitize input path/URL.
 *
 * - For local files:
 *      ✓ Ensures file is inside allowed folder
 *      ✓ Prevents ../ traversal
 *
 * - For remote URLs:
 *      ✓ Allows only HTTPS
 *      ✓ Blocks IP literals (SSRF protection)
 *      ✓ Blocks internal subnets (169.254.*, 10.*, 192.168.*, 172.16.* – 172.31.*)
 *
 * Returns:
 *      Sanitized and validated path/URL
 *
 * Throws:
 *      Exception on invalid or unsafe input
 */
function sanitizeSourceInput(string $source): string
{
    $source = trim($source);

    // If it's a URL, apply strict SSRF checks
    if (preg_match('#^https?://#i', $source)) {

        // Must be HTTPS, not HTTP
        if (!preg_match('#^https://#i', $source)) {
            throw new Exception("Only HTTPS URLs allowed for disease source.");
        }

        $host = parse_url($source, PHP_URL_HOST);
        if ($host === false || $host === null) {
            throw new Exception("Invalid host in URL.");
        }

        // Prevent SSRF to internal hosts
        $ip = gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if (
                str_starts_with($ip, '10.') ||
                str_starts_with($ip, '192.168.') ||
                preg_match('#^172\.(1[6-9]|2[0-9]|3[0-1])\.#', $ip) ||
                str_starts_with($ip, '127.') ||
                str_starts_with($ip, '169.254.')
            ) {
                throw new Exception("Blocked unsafe/private IP target: $ip");
            }
        }

        return $source;
    }

    // ----- Local File Security -----

    // No directory traversal
    if (str_contains($source, '..')) {
        throw new Exception("Directory traversal detected.");
    }

    // Restrict local lookup to current directory for safety
    $baseDir = __DIR__;
    $real     = realpath($source);

    if ($real === false || !str_starts_with($real, $baseDir)) {
        throw new Exception("Access to file outside allowed directory blocked.");
    }

    if (!is_readable($real)) {
        throw new Exception("File not readable: $real");
    }

    return $real;
}

/**
 * Extract disease names from the JSON file.
 *
 * SECURITY:
 *  - Uses validated/sanitized input path
 *  - Ensures JSON is an array
 *  - Ensures `disease_name` entries contain safe characters only
 */
function getDiseaseNames(string $source): array
{
    // Validate and sanitize the provided path
    $safeSource = sanitizeSourceInput($source);

    // Safely load JSON
    $json = @file_get_contents($safeSource);

    if ($json === false) {
        throw new Exception("Cannot read source file: $safeSource");
    }

    // Decode JSON safely
    $data = json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

    if (!is_array($data)) {
        throw new Exception("Invalid JSON content in: $safeSource");
    }

    $diseaseNames = [];
    $allowedPattern = '/^[A-Za-z0-9 ,\-\(\)\/]+$/u'; // Safe characters

    foreach ($data as $outer) {
        if (!is_array($outer)) continue;

        foreach ($outer as $inner) {
            if (!is_array($inner)) continue;

            if (isset($inner['disease_name'])) {
                $name = trim($inner['disease_name']);

                // Validate name
                if ($name !== "" && preg_match($allowedPattern, $name)) {
                    $diseaseNames[] = $name;
                }
            }
        }
    }

    return $diseaseNames;
}

/* --------------------------------------------------------------------------
 * Example Usage (commented out to avoid accidental runtime use)
 *
 * include_once 'extract_disease_names.php';
 *
 * // Local:
 * $names = getDiseaseNames('disease_pulldown_menu_cascaded.json');
 *
 * // Remote:
 * $names = getDiseaseNames(
 *     'https://raw.githubusercontent.com/metabolomicsworkbench/MetGENE/main/disease_pulldown_menu_cascaded.json'
 * );
 *
 * print_r($names);
 * -------------------------------------------------------------------------- */

?>
