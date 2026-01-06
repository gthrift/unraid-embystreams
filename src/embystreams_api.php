<?php
// Suppress warnings for cleaner API output
error_reporting(0);

$cfg_file = "/boot/config/plugins/embystreams/embystreams.cfg";

if (!file_exists($cfg_file)) {
    echo "<div style='padding:15px; text-align:center'>Config missing.</div>";
    exit;
}

$cfg = parse_ini_file($cfg_file);
$host = $cfg['HOST'];
$port = $cfg['PORT'];
$key = $cfg['API_KEY'];

if (empty($host) || empty($key)) {
    echo "<div style='padding:15px; text-align:center'>Please configure settings.</div>";
    exit;
}

// Timeout set short so dashboard doesn't hang
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$host:$port/emby/Sessions?api_key=$key");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    echo "<div style='padding:15px; color:#d44; text-align:center'>Connection Failed</div>";
    exit;
}

$sessions = json_decode($response, true);
$active = [];

if ($sessions) {
    foreach ($sessions as $s) {
        if (isset($s['NowPlayingItem'])) {
            $active[] = $s;
        }
    }
}

if (empty($active)) {
    echo "<div style='padding:20px; text-align:center; opacity:0.6'>No active streams</div>";
} else {
    echo "<table>";
    echo "<thead><tr><th>User</th><th>Playing</th><th>Device</th><th>State</th></tr></thead>";
    echo "<tbody>";
    
    foreach ($active as $s) {
        $user = htmlspecialchars($s['UserName']);
        $title = htmlspecialchars($s['NowPlayingItem']['Name']);
        if (isset($s['NowPlayingItem']['SeriesName'])) {
            $title = htmlspecialchars($s['NowPlayingItem']['SeriesName']) . " - " . $title;
        }
        $device = htmlspecialchars($s['DeviceName']);
        $paused = (isset($s['PlayState']['IsPaused']) && $s['PlayState']['IsPaused']);
        
        $icon = $paused ? "<i class='fa fa-pause es-icon-pause'></i>" : "<i class='fa fa-play es-icon-play'></i>";
        
        echo "<tr>";
        echo "<td>$user</td>";
        echo "<td>$title</td>";
        echo "<td>$device</td>";
        echo "<td>$icon</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}
?>
