<?php
/**
 * Emby Streams API Endpoint (Secured Version)
 * 
 * Security features:
 * - Session-based authentication with CSRF protection
 * - Rate limiting (server-side)
 * - Input validation and sanitization
 * - SSRF protection
 * - Secure API key handling (headers instead of URL)
 * - Error message sanitization
 */

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress warnings for production (log errors instead)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set content type
header('Content-Type: text/html; charset=UTF-8');

// =============================================================================
// 1. AUTHENTICATION & CSRF PROTECTION
// =============================================================================

// Check CSRF token
$csrf_valid = false;
if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && isset($_SESSION['embystreams_csrf'])) {
    $csrf_valid = hash_equals($_SESSION['embystreams_csrf'], $_SERVER['HTTP_X_CSRF_TOKEN']);
}

if (!$csrf_valid) {
    http_response_code(403);
    error_log("Emby Streams: CSRF token validation failed");
    echo "<div style='padding:15px; text-align:center; color:#d44;'>_(Authentication Failed)_</div>";
    exit;
}

// =============================================================================
// 2. RATE LIMITING
// =============================================================================

$rate_limit_key = 'embystreams_last_call';
$min_interval = 1; // Minimum 1 second between requests

if (isset($_SESSION[$rate_limit_key])) {
    $time_since_last = time() - $_SESSION[$rate_limit_key];
    if ($time_since_last < $min_interval) {
        http_response_code(429);
        error_log("Emby Streams: Rate limit exceeded");
        echo "<div style='padding:15px; text-align:center; color:#f0ad4e;'>_(Too Many Requests)_</div>";
        exit;
    }
}
$_SESSION[$rate_limit_key] = time();

// =============================================================================
// 3. LOAD AND VALIDATE CONFIGURATION
// =============================================================================

$cfg_file = "/boot/config/plugins/embystreams/embystreams.cfg";

if (!file_exists($cfg_file)) {
    error_log("Emby Streams: Configuration file not found");
    echo "<div style='padding:15px; text-align:center; opacity:0.6;'>_(Configuration missing)_</div>";
    exit;
}

// Check file permissions for security audit
$perms = fileperms($cfg_file);
if (($perms & 0077) != 0) {
    error_log("Emby Streams: WARNING - Config file has insecure permissions");
}

$cfg = parse_ini_file($cfg_file);

if (!$cfg) {
    error_log("Emby Streams: Failed to parse configuration");
    echo "<div style='padding:15px; text-align:center; color:#d44;'>_(Configuration Error)_</div>";
    exit;
}

// Extract and validate configuration
$host = $cfg['HOST'] ?? '';
$port = $cfg['PORT'] ?? '8096';
$key = $cfg['API_KEY'] ?? '';
$use_https = ($cfg['USE_HTTPS'] ?? '0') === '1';

// =============================================================================
// 4. INPUT VALIDATION
// =============================================================================

// Validate required fields
if (empty($host) || empty($key)) {
    echo "<div style='padding:15px; text-align:center; color:#eebb00;'>
            <i class='fa fa-exclamation-triangle'></i> _(Please configure settings)_
          </div>";
    exit;
}

// Validate host (prevent SSRF)
$is_valid_ip = filter_var($host, FILTER_VALIDATE_IP);
$is_valid_domain = preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $host);

if (!$is_valid_ip && !$is_valid_domain) {
    error_log("Emby Streams: Invalid host format: " . substr($host, 0, 50));
    echo "<div style='padding:15px; text-align:center; color:#d44;'>_(Invalid Configuration)_</div>";
    exit;
}

// Additional SSRF protection: block common internal/loopback addresses if needed
// Uncomment the following to restrict to local network only:
/*
if ($is_valid_ip) {
    $ip_long = ip2long($host);
    // Block loopback (127.0.0.0/8), unless it's your Emby server
    if (($ip_long & 0xFF000000) == 0x7F000000) {
        error_log("Emby Streams: Loopback address blocked");
        echo "<div style='padding:15px; text-align:center; color:#d44;'>_(Invalid Configuration)_</div>";
        exit;
    }
}
*/

// Validate port
$port = filter_var($port, FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => 1,
        'max_range' => 65535
    ]
]);

if ($port === false) {
    error_log("Emby Streams: Invalid port");
    echo "<div style='padding:15px; text-align:center; color:#d44;'>_(Invalid Configuration)_</div>";
    exit;
}

// Validate API key format (basic check)
if (strlen($key) < 32 || !ctype_alnum($key)) {
    error_log("Emby Streams: Invalid API key format");
    echo "<div style='padding:15px; text-align:center; color:#eebb00;'>
            <i class='fa fa-exclamation-triangle'></i> _(Invalid API Key Format)_
          </div>";
    exit;
}

// =============================================================================
// 5. PREPARE SECURE API CALL
// =============================================================================

// Determine protocol
$protocol = $use_https ? 'https' : 'http';

// Build URL without API key (security best practice)
$url = "$protocol://$host:$port/emby/Sessions";

// Initialize cURL
$ch = curl_init();

// Set URL
curl_setopt($ch, CURLOPT_URL, $url);

// Return response instead of outputting
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Set timeouts (prevent hanging)
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

// Send API key in header instead of URL (more secure)
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Emby-Token: ' . $key,
    'Accept: application/json'
]);

// SSL/TLS options for HTTPS
if ($use_https) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
}

// Follow redirects (but limit to prevent abuse)
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

// Set user agent
curl_setopt($ch, CURLOPT_USERAGENT, 'EmbyStreams-Unraid/2.0');

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// =============================================================================
// 6. ERROR HANDLING
// =============================================================================

if ($response === false || $http_code !== 200) {
    $error_msg = "_(Connection Failed)_";
    
    // Log detailed error server-side
    error_log("Emby Streams: Connection failed - HTTP $http_code - $curl_error");
    
    // Display generic error to user
    if ($http_code === 401 || $http_code === 403) {
        $error_msg = "_(Authentication Failed - Check API Key)_";
    } elseif ($http_code === 0) {
        $error_msg = "_(Cannot Reach Server)_";
    }
    
    echo "<div style='padding:15px; text-align:center; color:#d44;'>$error_msg</div>";
    exit;
}

// Decode JSON response
$sessions = json_decode($response, true);

if (!is_array($sessions)) {
    error_log("Emby Streams: Invalid JSON response");
    echo "<div style='padding:15px; text-align:center; color:#d44;'>_(Invalid Response)_</div>";
    exit;
}

// =============================================================================
// 7. FILTER FOR ACTIVE PLAYING SESSIONS
// =============================================================================

$active_streams = [];

foreach ($sessions as $session) {
    if (isset($session['NowPlayingItem'])) {
        $active_streams[] = $session;
    }
}

// =============================================================================
// 8. GENERATE SECURE OUTPUT
// =============================================================================

if (empty($active_streams)) {
    echo "<div style='padding:15px; text-align:center; opacity:0.6; font-style:italic;'>No active streams</div>";
    exit;
}

// Output active streams with proper sanitization
foreach ($active_streams as $s) {
    // Extract and sanitize data
    $user = isset($s['UserName']) ? htmlspecialchars($s['UserName'], ENT_QUOTES, 'UTF-8') : 'Unknown';
    
    // Build title
    $title = isset($s['NowPlayingItem']['Name']) ? 
        htmlspecialchars($s['NowPlayingItem']['Name'], ENT_QUOTES, 'UTF-8') : 
        'Unknown';
    
    // Add series name if it's a TV show
    if (isset($s['NowPlayingItem']['SeriesName'])) {
        $series = htmlspecialchars($s['NowPlayingItem']['SeriesName'], ENT_QUOTES, 'UTF-8');
        $title = $series . " - " . $title;
    }
    
    $device = isset($s['DeviceName']) ? 
        htmlspecialchars($s['DeviceName'], ENT_QUOTES, 'UTF-8') : 
        'Unknown Device';
    
    // Determine play state
    $is_paused = (isset($s['PlayState']['IsPaused']) && $s['PlayState']['IsPaused'] === true);
    $status_text = $is_paused ? "Paused" : "Playing";
    $status_color = $is_paused ? "#f0ad4e" : "#8cc43c";
    $status_icon = $is_paused ? "fa-pause" : "fa-play";
    
    // Check transcode status
    $play_method = isset($s['PlayState']['PlayMethod']) ? 
        htmlspecialchars($s['PlayState']['PlayMethod'], ENT_QUOTES, 'UTF-8') : 
        'DirectPlay';
    $is_transcoding = ($play_method === 'Transcode');
    
    $tooltip = htmlspecialchars("$status_text ($play_method)", ENT_QUOTES, 'UTF-8');
    
    // Output stream row
    echo "<div class='es-row'>";
    
    // Name
    echo "<span class='es-name' style='white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding-right:10px;' title='" . $title . "'>
            " . $title . "
          </span>";
    
    // Device
    echo "<span class='es-device' align='center' style='white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='" . $device . "'>
            " . $device . "
          </span>";
    
    // User
    echo "<span class='es-user' style='white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='" . $user . "'>
            </i>" . $user . "
          </span>";
    
    // State
    echo "<span class='es-state' align='right' style='color:" . $status_color . "; font-weight:bold; cursor:help;' title='" . $tooltip . "'>";
    if ($is_transcoding) {
        echo "<i class='fa fa-random es-transcode' title='Transcoding'></i> ";
    }
    echo "<i class='fa " . $status_icon . "' style='font-size:10px; margin-right:4px;'></i>" . $status_text;
    echo "</span>";
    
    echo "</div>";
}
?>
