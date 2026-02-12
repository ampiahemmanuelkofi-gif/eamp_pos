<?php
// includes/licensing_helper.php

// Secure key for internal encryption (Developer should change this per project)
define('LICENSE_SECRET', 'ANTIGRAVITY_SECURE_POS_2026_!@#');

/**
 * Generates a unique hardware-locked System ID.
 */
function get_system_id()
{
    $id = "";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows specific: Baseboard + Disk Serial
        $baseboard = shell_exec('wmic baseboard get serialnumber');
        $disk = shell_exec('wmic diskdrive get serialnumber');
        $id = md5(trim($baseboard) . trim($disk));
    } else {
        // Linux/Unix fallback
        $id = md5(php_uname('n') . shell_exec('cat /etc/machine-id'));
    }

    if (empty($id)) {
        // Absolute fallback (Less secure but prevents crashes)
        $id = md5($_SERVER['SERVER_ADDR'] . $_SERVER['SERVER_NAME']);
    }

    return strtoupper($id);
}

/**
 * Validates a license key string.
 */
function validate_license($license_key)
{
    if (empty($license_key))
        return ['valid' => false, 'error' => 'Key is empty'];

    $data = @json_decode(decrypt_license_data($license_key), true);

    if (!$data || !isset($data['system_id']) || !isset($data['expiry'])) {
        return ['valid' => false, 'error' => 'Invalid key format'];
    }

    // 1. Check System ID
    if ($data['system_id'] !== get_system_id()) {
        return ['valid' => false, 'error' => 'Key belongs to another computer'];
    }

    // 2. Check Expiry
    if ($data['expiry'] !== 'LIFETIME') {
        $expiry_ts = strtotime($data['expiry']);
        if ($expiry_ts < time()) {
            return ['valid' => false, 'error' => 'License expired on ' . $data['expiry']];
        }
    }

    return [
        'valid' => true,
        'type' => $data['type'] ?? 'Full',
        'expiry' => $data['expiry'],
        'client' => $data['client'] ?? 'Unknown',
        'features' => $data['features'] ?? []
    ];
}

/**
 * Checks if the system is currently licensed.
 * Bypasses check for developer dashboard if needed.
 */
function is_system_authorized()
{
    global $SYSTEM_SETTINGS;

    // Skip license check for licensing page itself to avoid infinite loops
    $current_page = $_SERVER['PHP_SELF'];
    if (strpos($current_page, 'activate.php') !== false)
        return true;

    $license_key = $SYSTEM_SETTINGS['license_key'] ?? '';
    $val = validate_license($license_key);

    return $val['valid'];
}

/**
 * Utility: Encrypt license data (Used by key generator)
 */
function encrypt_license_data($data)
{
    $json = json_encode($data);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($json, 'aes-256-cbc', LICENSE_SECRET, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * Utility: Decrypt license data (Used by validator)
 */
function decrypt_license_data($license_key)
{
    $parts = explode('::', base64_decode($license_key));
    if (count($parts) != 2)
        return false;
    list($encrypted_data, $iv) = $parts;
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', LICENSE_SECRET, 0, $iv);
}
?>