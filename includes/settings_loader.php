<?php
// includes/settings_loader.php
require_once __DIR__ . '/../config/database.php';

$SYSTEM_SETTINGS = [];

// Set Defaults first (in case database has issues)
$SYSTEM_SETTINGS['currency_symbol'] = '$';
$SYSTEM_SETTINGS['currency_position'] = 'before';
$SYSTEM_SETTINGS['tax_rate'] = 0;
$SYSTEM_SETTINGS['invoice_prefix'] = 'INV-';
$SYSTEM_SETTINGS['company_name'] = 'EAMP POS System';

// Try to fetch from database (with timeout protection)
if (!isset($conn)) {
    $db = new Database();
    $conn = $db->getConnection();
}

// Only proceed if connection is valid
if ($conn && !$conn->connect_error) {
    // Fetch all preferences
    $prefs_sql = "SELECT * FROM system_preferences LIMIT 100";
    $prefs_result = @$conn->query($prefs_sql);

    if ($prefs_result) {
        while ($row = $prefs_result->fetch_assoc()) {
            $SYSTEM_SETTINGS[$row['pref_key']] = $row['pref_value'];
        }
    }

    // Fetch company settings
    $company_sql = "SELECT * FROM settings LIMIT 1";
    $company_result = @$conn->query($company_sql);
    if ($company_result && $company_result->num_rows > 0) {
        $company_data = $company_result->fetch_assoc();
        $SYSTEM_SETTINGS['company_name'] = $company_data['company_name'] ?? $SYSTEM_SETTINGS['company_name'];
        $SYSTEM_SETTINGS['company_address'] = $company_data['company_address'] ?? '';
        $SYSTEM_SETTINGS['company_details'] = $company_data['company_details'] ?? '';
        $SYSTEM_SETTINGS['company_logo'] = $company_data['logo'] ?? '';
    }
}

// Define Constants if needed
if (!defined('CURRENCY_SYMBOL'))
    define('CURRENCY_SYMBOL', $SYSTEM_SETTINGS['currency_symbol']);
if (!defined('CURRENCY_POSITION'))
    define('CURRENCY_POSITION', $SYSTEM_SETTINGS['currency_position']);

// Helper function to format money
if (!function_exists('format_currency')) {
    function format_currency($amount)
    {
        global $SYSTEM_SETTINGS;
        $formatted = number_format((float) $amount, 2);
        if ($SYSTEM_SETTINGS['currency_position'] == 'before') {
            return $SYSTEM_SETTINGS['currency_symbol'] . $formatted;
        } else {
            return $formatted . $SYSTEM_SETTINGS['currency_symbol'];
        }
    }
}
?>