<?php
/*
 * Mail settings for SMTP sending. Returns an array (enabled, host, port, encryption,
 * username, password, from_email, from_name, configured).
 * Access: public include, no output. Inputs: config/local.php or DOMS_MAIL_* environment variables.
 */

// ===== Load settings source =====

// Set these as environment variables or in config/local.php. Never store real credentials here.
$localConfig = __DIR__ . '/local.php';
$local = is_file($localConfig) ? require $localConfig : [];


// ===== Build mail settings (local file overrides environment) =====

$config = [
    'enabled' => filter_var($local['mail_enabled'] ?? getenv('DOMS_MAIL_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN),
    'host' => trim((string)($local['mail_host'] ?? getenv('DOMS_MAIL_HOST') ?: 'smtp.gmail.com')),
    'port' => (int)($local['mail_port'] ?? getenv('DOMS_MAIL_PORT') ?: 587),
    'encryption' => strtolower(trim((string)($local['mail_encryption'] ?? getenv('DOMS_MAIL_ENCRYPTION') ?: 'tls'))),
    'username' => trim((string)($local['mail_username'] ?? getenv('DOMS_MAIL_USERNAME') ?: '')),
    'password' => (string)($local['mail_password'] ?? getenv('DOMS_MAIL_PASSWORD') ?: ''),
    'from_email' => trim((string)($local['mail_from_email'] ?? getenv('DOMS_MAIL_FROM_EMAIL') ?: '')),
    'from_name' => trim((string)($local['mail_from_name'] ?? getenv('DOMS_MAIL_FROM_NAME') ?: 'WebShelf')),
];


// ===== Check settings are complete =====

// True only when host, port, encryption, login and a valid From address are all set
$config['configured'] =
    $config['host'] !== '' &&
    $config['port'] > 0 &&
    in_array($config['encryption'], ['none', 'tls', 'ssl'], true) &&
    $config['username'] !== '' &&
    $config['password'] !== '' &&
    filter_var($config['from_email'], FILTER_VALIDATE_EMAIL) !== false;


// ===== Return settings to the caller (used via require) =====

return $config;
