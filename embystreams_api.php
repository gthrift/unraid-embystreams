<?php
// Load Config
$cfg_file = "/boot/config/plugins/embystreams/embystreams.cfg";
if (!file_exists($cfg_file)) {
    echo "Configuration missing.";
    exit;
}
$cfg = parse_ini_file($cfg_file);

$host = $cfg['HOST'];
$port = $cfg['PORT'];
$key = $cfg['API_KEY'];

if (empty($host) || empty($key)) {
    echo "Please configure Emby Streams in Settings > User Utilities.";
    exit;
}

// Call Emby API
$url = "http://$host:$port/emby/Sessions?api_key=$key";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
$response = curl_exec($ch);
curl_close($ch);

$sessions = json_decode($response, true);

if (!$sessions) {
    echo "No connection or invalid API key.";
    exit;
}

// Filter for active playing sessions
$active_streams = [];
foreach ($sessions as $session) {
    if (isset($session['NowPlayingItem'])) {
        $active_streams[] = $session;
    }
}

// Generate HTML Output for Dashboard
if (count($active_streams) === 0) {
    echo "<div style='text-align:center; padding:10px;'>No active streams</div>";
} else {
    echo "<table>";
    echo "<thead><tr><th>User</th><th>Playing</th><th>Device</th><th>Status</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($active_streams as $s) {
        $user = $s['UserName'];
        $title = $s['NowPlayingItem']['Name'];
        // Provide fallback if Series Name exists (TV Shows)
        if (isset($s['NowPlayingItem']['SeriesName'])) {
            $title = $s['NowPlayingItem']['SeriesName'] . " - " . $title;
        }
        
        $device = $s['DeviceName'];
        $paused = isset($s['PlayState']['IsPaused']) && $s['PlayState']['IsPaused'] ? "Paused" : "Playing";
        
        echo "<tr>";
        echo "<td>$user</td>";
        echo "<td>$title</td>";
        echo "<td>$device</td>";
        echo "<td>$paused</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}
?>