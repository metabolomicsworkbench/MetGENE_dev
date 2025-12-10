<?php
/**
 * MetGENE REST API Entry Point
 * Security hardened with proper error handling, logging, and initialization
 */

declare(strict_types=1);

// SECURITY FIX: Set error handling appropriately for production
// Only display errors in development, log them in production
$is_production = ($_SERVER['SERVER_NAME'] ?? '') !== 'localhost';

if ($is_production) {
    // Production: Log errors, don't display
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    
    // SECURITY FIX: Set error log to specific file
    $error_log_path = __DIR__ . '/../cache/rest_api_errors.log';
    ini_set('error_log', $error_log_path);
} else {
    // Development: Display errors for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
}

// SECURITY FIX: Set timezone securely
date_default_timezone_set('America/Los_Angeles');

// SECURITY FIX: Set resource limits
set_time_limit(300);  // 5 minutes max (reduced from unlimited)
ini_set('memory_limit', '512M');  // Reduced from 2GB (more reasonable for API)

// SECURITY FIX: Disable dangerous PHP functions if not already disabled
if (function_exists('ini_set')) {
    @ini_set('expose_php', '0');  // Hide PHP version
}

// SECURITY FIX: Start session securely
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $is_production ? '1' : '0');  // HTTPS only in production
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// SECURITY FIX: Load the REST API class
$rest_class_file = __DIR__ . '/metgene_rest_api_class.php';

if (!file_exists($rest_class_file)) {
    error_log("CRITICAL: REST API class file not found: {$rest_class_file}");
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'API service unavailable'
    ]);
    exit;
}

require_once $rest_class_file;

// SECURITY FIX: Validate that API class exists
if (!class_exists('API')) {
    error_log("CRITICAL: API class not defined in metgene_rest_api_class.php");
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'API service misconfigured'
    ]);
    exit;
}

// SECURITY FIX: Wrap in try-catch for error handling
try {
    // Initialize API
    $api = new API();
    
    // SECURITY FIX: Validate request method
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET') {
        http_response_code(405);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'error' => 'Method not allowed',
            'message' => 'Only GET requests are supported'
        ]);
        exit;
    }
    
    // SECURITY FIX: Log API access (for monitoring)
    $request_uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    error_log("REST API Access: {$request_uri} from {$remote_addr} - {$user_agent}");
    
    // Process API request
    $api->processApi('');
    
} catch (Exception $e) {
    // SECURITY FIX: Log exception details but don't expose to user
    error_log("REST API Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    
    if ($is_production) {
        // Production: Generic error message
        echo json_encode([
            'error' => 'Internal server error',
            'message' => 'An error occurred processing your request'
        ]);
    } else {
        // Development: Detailed error for debugging
        echo json_encode([
            'error' => 'Internal server error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    exit;
} catch (Throwable $e) {
    // SECURITY FIX: Catch any throwable (PHP 7+)
    error_log("REST API Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'A fatal error occurred'
    ]);
    exit;
}

// SECURITY FIX: Explicit exit (shouldn't reach here, but safety measure)
exit;
?>