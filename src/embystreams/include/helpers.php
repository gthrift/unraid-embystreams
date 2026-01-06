<?PHP
/* Copyright 2025
 *
 * EmbyStreams - Helper Functions
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 */

$plugin = "embystreams";

/**
 * Load plugin configuration
 * @return array Configuration array
 */
function embystreams_getConfig() {
    global $plugin;
    $cfgfile = "/boot/config/plugins/{$plugin}/{$plugin}.cfg";
    
    $defaults = [
        'EMBY_URL' => '',
        'EMBY_API_KEY' => '',
        'REFRESH_INTERVAL' => '10',
        'SHOW_THUMBNAILS' => 'yes',
        'SHOW_TRANSCODE_INFO' => 'yes',
        'MAX_STREAMS' => '10',
        'SHOW_IDLE' => 'no',
    ];
    
    if (file_exists($cfgfile)) {
        $cfg = parse_ini_file($cfgfile);
        return array_merge($defaults, $cfg ?: []);
    }
    
    return $defaults;
}

/**
 * Save plugin configuration
 * @param array $config Configuration array to save
 * @return bool Success status
 */
function embystreams_saveConfig($config) {
    global $plugin;
    $cfgfile = "/boot/config/plugins/{$plugin}/{$plugin}.cfg";
    $cfgdir = dirname($cfgfile);
    
    if (!is_dir($cfgdir)) {
        mkdir($cfgdir, 0755, true);
    }
    
    $content = "# EmbyStreams Configuration\n";
    $content .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($config as $key => $value) {
        // Sanitize key
        $key = preg_replace('/[^A-Z0-9_]/i', '', $key);
        // Escape quotes in value
        $value = str_replace('"', '\\"', $value);
        $content .= "{$key}=\"{$value}\"\n";
    }
    
    return file_put_contents($cfgfile, $content) !== false;
}

/**
 * Check if plugin is properly configured
 * @return bool
 */
function embystreams_isConfigured() {
    $cfg = embystreams_getConfig();
    return !empty($cfg['EMBY_URL']) && !empty($cfg['EMBY_API_KEY']);
}

/**
 * Get EmbyAPI instance
 * @return EmbyAPI|null
 */
function embystreams_getAPI() {
    require_once(__DIR__ . '/EmbyAPI.php');
    
    $cfg = embystreams_getConfig();
    
    if (empty($cfg['EMBY_URL']) || empty($cfg['EMBY_API_KEY'])) {
        return null;
    }
    
    return new EmbyAPI($cfg['EMBY_URL'], $cfg['EMBY_API_KEY']);
}

/**
 * Format ticks to human-readable time
 * @param int $ticks Ticks (1 tick = 100 nanoseconds)
 * @return string Formatted time (HH:MM:SS or MM:SS)
 */
function embystreams_formatTicks($ticks) {
    $seconds = floor($ticks / 10000000);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}

/**
 * Format bitrate to human-readable string
 * @param int $bitrate Bitrate in bps
 * @return string Formatted bitrate
 */
function embystreams_formatBitrate($bitrate) {
    if ($bitrate >= 1000000) {
        return round($bitrate / 1000000, 1) . ' Mbps';
    } elseif ($bitrate >= 1000) {
        return round($bitrate / 1000, 0) . ' Kbps';
    }
    return $bitrate . ' bps';
}

/**
 * Log message to plugin log file
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 */
function embystreams_log($message, $level = 'info') {
    global $plugin;
    $logfile = "/var/log/{$plugin}.log";
    $timestamp = date('Y-m-d H:i:s');
    $level = strtoupper($level);
    
    $entry = "[{$timestamp}] [{$level}] {$message}\n";
    
    // Rotate log if too large (>1MB)
    if (file_exists($logfile) && filesize($logfile) > 1048576) {
        @rename($logfile, $logfile . '.old');
    }
    
    @file_put_contents($logfile, $entry, FILE_APPEND | LOCK_EX);
}
?>
