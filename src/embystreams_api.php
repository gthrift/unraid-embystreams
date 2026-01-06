<?php
// Suppress warnings to prevent breaking dashboard JSON/HTML
error_reporting(0);

// 1. Load Configuration
$cfg_file = "/boot/config/plugins/embystreams/embystreams.cfg";

if (!file_exists($cfg_file)) {
    echo "<div style='padding:15px; text-align:center; opacity:0.6;'>_(Configuration missing)_</div>";
    exit;
}

$cfg = parse_ini_file($cfg_file);
$host = $cfg['HOST'];
$port = $cfg['PORT'];
$key = $cfg['API_KEY'];

// Check if basic settings are present
if (empty($host) || empty($key)) {
    echo "<div style='padding:15px; text-align:center; color:#eebb00;'>
            <i class='fa fa-exclamation-triangle'></i> _(Please configure settings)_
          </div>";
    exit;
}

// 2. Prepare API Call
// Emby Sessions API endpoint
$url = "http://$host:$port/emby/Sessions?api_key=$key";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Short timeouts to ensure the dashboard doesn't hang if Emby is down
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 3. Error Handling
if ($http_code !== 200 || !$response) {
    echo "<div style='padding:15px; text-align:center; color:#d44;'>
            _(Connection Failed)_
          </div>";
    exit;
}

$sessions = json_decode($response, true);
$active_streams = [];

// 4. Filter for Active Playing Sessions
if ($sessions) {
    foreach ($sessions as $session) {
        // We only care if 'NowPlayingItem' exists
        if (isset($session['NowPlayingItem'])) {
            $active_streams[] = $session;
        }
    }
}

// 5. Generate Output
if (empty($active_streams)) {
    echo "<div style='padding:15px; text-align:center; opacity:0.6; font-style:italic;'>
            _(No active streams)_
          </div>";
} else {
    foreach ($active_streams as $s) {
        // -- Data Extraction --
        
        // User
        $user = htmlspecialchars($s['UserName']);
        
        // Title (Show 'Series - Episode' or just 'Movie Name')
        $title = htmlspecialchars($s['NowPlayingItem']['Name']);
        if (isset($s['NowPlayingItem']['SeriesName'])) {
            $title = htmlspecialchars($s['NowPlayingItem']['SeriesName']) . " - " . $title;
        }
        
        // Device
        $device = htmlspecialchars($s['DeviceName']);
        
        // Status (Paused/Playing)
        $is_paused = (isset($s['PlayState']['IsPaused']) && $s['PlayState']['IsPaused']);
        $status_text = $is_paused ? "Paused" : "Playing";
        
        // Styling based on status
        $status_color = $is_paused ? "#f0ad4e" : "#8cc43c"; // Orange for pause, Green for play
        $status_icon  = $is_paused ? "fa-pause" : "fa-play";

        // Transcode Logic
        $play_method = $s['PlayState']['PlayMethod'] ?? 'DirectPlay';
        $is_transcoding = ($play_method === 'Transcode');
        
        // Tooltip detail (e.g. "Playing (Transcode)")
        $tooltip = "$status_text ($play_method)";

        // -- HTML Output --
        echo "<div class='es-row'>";
        
        // Name (36% width normally, 65% on mobile via CSS)
        echo "<span class='w36' style='white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding-right:10px;' title='$title'>
                $title
              </span>";
        
        // Device (18% width) -> Has 'es-hide-mobile' class to vanish on small screens
        echo "<span class='w18 es-hide-mobile' align='center' style='white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='$device'>
                $device
              </span>";
              
        // User (18% width) -> Has 'es-hide-mobile' class to vanish on small screens
        echo "<span class='w18 es-hide-mobile' style='white-space:nowrap; overflow:hidden; text-overflow:ellipsis;' title='$user'>
                <i class='fa fa-user' style='opacity:0.3; margin-right:4px;'></i>$user
              </span>";
              
        // Status (18% width normally, 35% on mobile via CSS)
        echo "<span class='w18' align='right' style='color:$status_color; font-weight:bold; cursor:help;' title='$tooltip'>";
        
        // If Transcoding, add the exchange icon
        if ($is_transcoding) {
            echo "<i class='fa fa-exchange es-transcode' title='_(Transcoding)_'></i> ";
        }
        
        echo "<i class='fa $status_icon' style='font-size:10px; margin-right:4px;'></i>$status_text";
        echo "</span>";
              
        echo "</div>";
    }
}
?>
