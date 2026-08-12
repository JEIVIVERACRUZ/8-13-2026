<?php
// ============================================================
// includes/site_settings.php
// Load barangay settings from DB once per request.
// Include this AFTER db.php on every page that needs settings.
// ============================================================

function getSiteSettings(bool $forceRefresh = false): array {
    static $cache = null;
    if ($cache === null || $forceRefresh) {
        $rows  = dbFetchAll("SELECT setting_key, setting_value FROM barangay_settings");
        $cache = array_column($rows, 'setting_value', 'setting_key');
    }
    return $cache;
}

/**
 * Output an inline <style> block that sets the --primary CSS variable.
 * Uses the default color since theme editing has been removed.
 * Still called inside <head> on every page.
 */
function renderThemeStyle(): void {
    echo "<style>
:root {
  --primary: #0066cc;
  --primary-light: #e6f0ff;
}
</style>\n";
}

/**
 * Return the logo path to use in <img src="...">.
 */
function getSiteLogo(): string {
    $s = getSiteSettings();
    return htmlspecialchars($s['logo_path'] ?? 'assets/images/logo.jpg');
}

/**
 * Return the barangay name.
 */
function getSiteName(): string {
    $s = getSiteSettings();
    return htmlspecialchars($s['barangay_name'] ?? 'Barangay San Isidro');
}