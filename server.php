<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
// Built-in server mengabaikan public/uploads/.htaccess: jangan pernah eksekusi skrip di folder upload
if (preg_match('#^/uploads/#i', $uri) && preg_match('#\.(php\d?|phtml|phar|phps|pht|pgif|inc)(\.|$)#i', $uri)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
