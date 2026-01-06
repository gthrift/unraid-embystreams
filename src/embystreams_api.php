<?php
// Suppress errors to prevent breaking the dashboard JSON/HTML
error_reporting(0);

$cfg_file = "/boot/config/plugins/embystreams/embystreams.cfg";

if (!file_exists($cfg_file)) {
    echo "<div style='padding:10px'>Configuration file missing.</div>";
    exit;
}

$cfg = parse_ini_file($cfg_file);
$host = $cfg['HOST'];
$port = $cfg['PORT'];
$key = $cfg['API_KEY'];

if (empty($host) || empty($key)) {
    echo "<div style='padding:15px; text-align:center;'>
            <i class='fa fa-exclamation-triangle'></i><br>
            Please configure settings<br>
            <a href='/Settings/EmbyStreamsSettings'>Go to Settings</a>
          </div>";
    exit;
}

// Prepare API Call
$url = "http://$host:$port/emby/Sessions?api_key=$key";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Fast timeout to not hang dashboard
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    echo "<div style='padding:15px; color: #d44;'>Could not connect to Emby ($host).</div>";
    exit;
}

$sessions = json_decode($response, true);

// Filter for playing items
$active_streams = [];
if ($sessions) {
    foreach ($sessions as $session) {
        if (isset($session['NowPlayingItem'])) {
            $active_streams[] = $session;
        }
    }
}

if (count($active_streams) === 0) {
    echo "<div style='padding: 20px; text-align: center; opacity: 0.5;'>No active streams</div>";
} else {
    echo "<table class='tablesorter'>";
    echo "<thead><tr><th>User</th><th>Playing</th><th>Device</th><th>State</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($active_streams as $s) {
        $user = htmlspecialchars($s['UserName']);
        $title = htmlspecialchars($s['NowPlayingItem']['Name']);
        
        // Add Series Name if available
        if (isset($s['NowPlayingItem']['SeriesName'])) {
            $title = htmlspecialchars($s['NowPlayingItem']['SeriesName']) . " - " . $title;
        }
        
        $device = htmlspecialchars($s['DeviceName']);
        $is_paused = isset($s['PlayState']['IsPaused']) && $s['PlayState']['IsPaused'];
        $status_html = $is_paused ? "<span class='es-status-paused'><i class='fa fa-pause'></i> Paused</span>" : "<span class='es-status-playing'><i class='fa fa-play'></i> Playing</span>";
        
        echo "<tr>";
        echo "<td>$user</td>";
        echo "<td><div style='max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;' title='$title'>$title</div></td>";
        echo "<td>$device</td>";
        echo "<td>$status_html</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}
?>
