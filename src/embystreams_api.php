<?php
// Suppress warnings
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

if (empty($host) || empty($key)) {
    echo "<div style='padding:15px; text-align:center; color:#eebb00;'>
            <i class='fa fa-exclamation-triangle'></i> _(Please configure settings)_
          </div>";
    exit;
}

// 2. Prepare API Call
$url = "http://$host:$port/emby/Sessions?api_key=$key";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 3. Error Handling
if ($http_code !== 200 || !$response) {
    echo "<div style='padding:15px; text-align:center; color:#d44;'>_(Connection Failed)_</div>";
    exit;
}

$sessions = json_decode($response, true);
$active_streams = [];

// 4. Filter for Active Playing Sessions
if ($sessions) {
    foreach ($sessions as $session) {
        if (isset($session['NowPlayingItem'])) {
            $active_streams[] = $session;
        }
    }
}

// Helper: Convert ticks (10,000,000 ticks = 1 second) to H:MM:SS or M:SS
function formatTicks($ticks) {
    if (!$ticks || $ticks <= 0) return '0:00';
    $totalSeconds = intval($ticks / 10000000);
    $hours = intval($totalSeconds / 3600);
    $minutes = intval(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
    }
    return sprintf("%d:%02d", $minutes, $seconds);
}

// 5. Generate Output
if (empty($active_streams)) {
    echo "<div style='padding:15px; text-align:center; opacity:0.6; font-style:italic;'>No active streams</div>";
} else {
    foreach ($active_streams as $s) {
        $user = htmlspecialchars($s['UserName']);
        $episode_title = htmlspecialchars($s['NowPlayingItem']['Name']);

        // Build display title - for TV shows use: Show Name - SxxExx - Episode Name
        if (isset($s['NowPlayingItem']['SeriesName'])) {
            $series = htmlspecialchars($s['NowPlayingItem']['SeriesName']);
            $season_num = isset($s['NowPlayingItem']['ParentIndexNumber']) ? intval($s['NowPlayingItem']['ParentIndexNumber']) : null;
            $episode_num = isset($s['NowPlayingItem']['IndexNumber']) ? intval($s['NowPlayingItem']['IndexNumber']) : null;
            if ($season_num !== null && $episode_num !== null) {
                $title = $series . " - S" . str_pad($season_num, 2, '0', STR_PAD_LEFT) . "E" . str_pad($episode_num, 2, '0', STR_PAD_LEFT) . " - " . $episode_title;
            } else {
                $title = $series . " - " . $episode_title;
            }
        } else {
            $title = $episode_title;
        }

        $device = htmlspecialchars($s['DeviceName']);
        $is_paused = (isset($s['PlayState']['IsPaused']) && $s['PlayState']['IsPaused']);
        $status_text = $is_paused ? "Paused" : "Playing";
        $status_color = $is_paused ? "#f0ad4e" : "#8cc43c";
        $status_icon  = $is_paused ? "fa-pause" : "fa-play";
        $play_method = $s['PlayState']['PlayMethod'] ?? 'DirectPlay';
        $is_transcoding = ($play_method === 'Transcode');

        // Build progress tooltip: [current timestamp] / [media length]
        $position_ticks = $s['PlayState']['PositionTicks'] ?? 0;
        $runtime_ticks = $s['NowPlayingItem']['RunTimeTicks'] ?? 0;
        $progress_tooltip = formatTicks($position_ticks) . " / " . formatTicks($runtime_ticks);

        // Build transcoding details tooltip
        $transcode_tooltip = '';
        if ($is_transcoding && isset($s['TranscodingInfo'])) {
            $ti = $s['TranscodingInfo'];
            $parts = [];
            if (!empty($ti['VideoCodec'])) {
                $vc = strtoupper($ti['VideoCodec']);
                $hw = '';
                if (!empty($ti['IsVideoDirect'])) {
                    $hw = ' (Direct)';
                } elseif (!empty($ti['HardwareAccelerationType'])) {
                    $hw = ' (' . htmlspecialchars($ti['HardwareAccelerationType']) . ')';
                }
                $parts[] = "Video: $vc$hw";
            }
            if (!empty($ti['AudioCodec'])) {
                $ac = strtoupper($ti['AudioCodec']);
                $audio_direct = !empty($ti['IsAudioDirect']) ? ' (Direct)' : '';
                $parts[] = "Audio: $ac$audio_direct";
            }
            if (!empty($ti['Bitrate'])) {
                $bitrate_mbps = round($ti['Bitrate'] / 1000000, 1);
                $parts[] = "Bitrate: {$bitrate_mbps} Mbps";
            }
            if (!empty($ti['CompletionPercentage'])) {
                $parts[] = "Buffered: " . round($ti['CompletionPercentage'], 0) . "%";
            }
            if (!empty($ti['TranscodeReasons'])) {
                $reasons = implode(', ', array_map('htmlspecialchars', $ti['TranscodeReasons']));
                $parts[] = "Reason: $reasons";
            }
            $transcode_tooltip = implode("&#10;", $parts);
        } elseif ($is_transcoding) {
            $transcode_tooltip = 'Transcoding';
        }

        // -- HTML Output --
        echo "<div class='es-row'>";

        // Name
        echo "<span class='es-name' title='$title'>
                $title
              </span>";

        // Device
        echo "<span class='es-device' title='$device'>
                $device
              </span>";

        // User (no icon)
        echo "<span class='es-user' title='$user'>
                $user
              </span>";

        // State
        echo "<span class='es-state' style='color:$status_color; font-weight:bold; cursor:help;' title='$progress_tooltip'>";
        if ($is_transcoding) {
            echo "<i class='fa fa-random es-transcode' style='color:#e5a00d; font-size:11px;' title='$transcode_tooltip'></i> ";
        }
        echo "<i class='fa $status_icon' style='font-size:11px; margin-right:4px;'></i>$status_text";
        echo "</span>";

        echo "</div>";
    }
}
?>
