<?PHP
/* Copyright 2025
 *
 * EmbyStreams - AJAX Endpoint
 * Handles asynchronous requests from dashboard widget and settings page
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 */

header('Content-Type: application/json');

require_once(__DIR__ . '/helpers.php');
require_once(__DIR__ . '/EmbyAPI.php');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'error' => 'Unknown action'];

switch ($action) {
    
    case 'getSessions':
        // Get active playing sessions
        $cfg = embystreams_getConfig();
        
        if (empty($cfg['EMBY_URL']) || empty($cfg['EMBY_API_KEY'])) {
            $response = [
                'success' => false,
                'error' => 'Plugin not configured',
                'configured' => false
            ];
            break;
        }
        
        $api = new EmbyAPI($cfg['EMBY_URL'], $cfg['EMBY_API_KEY']);
        
        // Get all sessions based on config
        $showIdle = ($cfg['SHOW_IDLE'] ?? 'no') === 'yes';
        
        if ($showIdle) {
            $sessions = $api->getSessions();
            $formatted = [];
            if (is_array($sessions)) {
                foreach ($sessions as $session) {
                    if (isset($session['NowPlayingItem'])) {
                        $formatted[] = $api->formatSession($session);
                    }
                }
            }
        } else {
            $formatted = $api->getFormattedPlayingSessions();
        }
        
        // Apply max streams limit
        $maxStreams = intval($cfg['MAX_STREAMS'] ?? 0);
        if ($maxStreams > 0 && count($formatted) > $maxStreams) {
            $formatted = array_slice($formatted, 0, $maxStreams);
        }
        
        $response = [
            'success' => true,
            'sessions' => $formatted,
            'count' => count($formatted),
            'timestamp' => time(),
            'configured' => true
        ];
        break;
        
    case 'testConnection':
        // Test connection to Emby server
        $url = $_POST['url'] ?? '';
        $apiKey = $_POST['apiKey'] ?? '';
        
        if (empty($url) || empty($apiKey)) {
            $response = [
                'success' => false,
                'error' => 'URL and API Key are required'
            ];
            break;
        }
        
        $api = new EmbyAPI($url, $apiKey);
        $result = $api->testConnection();
        
        $response = [
            'success' => $result['success'],
            'message' => $result['message'],
            'server' => $result['server'] ?? null,
            'version' => $result['version'] ?? null
        ];
        break;
        
    case 'getStatus':
        // Get plugin status for dashboard
        $cfg = embystreams_getConfig();
        
        if (empty($cfg['EMBY_URL']) || empty($cfg['EMBY_API_KEY'])) {
            $response = [
                'success' => true,
                'configured' => false,
                'online' => false,
                'streamCount' => 0
            ];
            break;
        }
        
        $api = new EmbyAPI($cfg['EMBY_URL'], $cfg['EMBY_API_KEY']);
        $testResult = $api->testConnection();
        
        $streamCount = 0;
        if ($testResult['success']) {
            $sessions = $api->getPlayingSessions();
            $streamCount = count($sessions);
        }
        
        $response = [
            'success' => true,
            'configured' => true,
            'online' => $testResult['success'],
            'serverName' => $testResult['server'] ?? '',
            'serverVersion' => $testResult['version'] ?? '',
            'streamCount' => $streamCount,
            'timestamp' => time()
        ];
        break;
        
    case 'saveConfig':
        // Save configuration (from settings page)
        $config = [
            'EMBY_URL' => trim($_POST['EMBY_URL'] ?? ''),
            'EMBY_API_KEY' => trim($_POST['EMBY_API_KEY'] ?? ''),
            'REFRESH_INTERVAL' => max(5, intval($_POST['REFRESH_INTERVAL'] ?? 10)),
            'SHOW_THUMBNAILS' => ($_POST['SHOW_THUMBNAILS'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'SHOW_TRANSCODE_INFO' => ($_POST['SHOW_TRANSCODE_INFO'] ?? 'no') === 'yes' ? 'yes' : 'no',
            'MAX_STREAMS' => max(0, intval($_POST['MAX_STREAMS'] ?? 10)),
            'SHOW_IDLE' => ($_POST['SHOW_IDLE'] ?? 'no') === 'yes' ? 'yes' : 'no',
        ];
        
        if (embystreams_saveConfig($config)) {
            $response = [
                'success' => true,
                'message' => 'Configuration saved'
            ];
        } else {
            $response = [
                'success' => false,
                'error' => 'Failed to save configuration'
            ];
        }
        break;
        
    default:
        $response = [
            'success' => false,
            'error' => 'Unknown action: ' . htmlspecialchars($action)
        ];
}

echo json_encode($response);
?>
