<?PHP
/* Copyright 2025
 *
 * EmbyStreams - Emby API Helper Class
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 */

class EmbyAPI {
    private $baseUrl;
    private $apiKey;
    private $timeout;
    
    /**
     * Constructor
     * @param string $baseUrl Emby server URL (e.g., http://localhost:8096)
     * @param string $apiKey API key from Emby server
     * @param int $timeout Request timeout in seconds
     */
    public function __construct($baseUrl, $apiKey, $timeout = 10) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }
    
    /**
     * Make an API request to Emby
     * @param string $endpoint API endpoint (e.g., /Sessions)
     * @param array $params Query parameters
     * @return array|null Response data or null on error
     */
    public function request($endpoint, $params = []) {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            return null;
        }
        
        // Build URL with API key
        $params['api_key'] = $this->apiKey;
        $url = $this->baseUrl . '/emby' . $endpoint . '?' . http_build_query($params);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get all active sessions
     * @return array|null Array of sessions or null on error
     */
    public function getSessions() {
        return $this->request('/Sessions');
    }
    
    /**
     * Get sessions that are currently playing
     * @return array Array of sessions with NowPlayingItem
     */
    public function getPlayingSessions() {
        $sessions = $this->getSessions();
        
        if (!is_array($sessions)) {
            return [];
        }
        
        return array_filter($sessions, function($session) {
            return isset($session['NowPlayingItem']) && !empty($session['NowPlayingItem']);
        });
    }
    
    /**
     * Get server info
     * @return array|null Server info or null on error
     */
    public function getServerInfo() {
        return $this->request('/System/Info/Public');
    }
    
    /**
     * Test connection to Emby server
     * @return array ['success' => bool, 'message' => string, 'server' => string|null]
     */
    public function testConnection() {
        $info = $this->getServerInfo();
        
        if ($info === null) {
            return [
                'success' => false,
                'message' => 'Cannot connect to Emby server',
                'server' => null
            ];
        }
        
        if (isset($info['error'])) {
            return [
                'success' => false,
                'message' => $info['error'],
                'server' => null
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Connected successfully',
            'server' => $info['ServerName'] ?? 'Unknown',
            'version' => $info['Version'] ?? 'Unknown'
        ];
    }
    
    /**
     * Get image URL for an item
     * @param string $itemId Item ID
     * @param string $imageType Image type (Primary, Backdrop, etc.)
     * @param int $maxWidth Maximum width
     * @return string Image URL
     */
    public function getImageUrl($itemId, $imageType = 'Primary', $maxWidth = 200) {
        if (empty($itemId)) {
            return '';
        }
        
        return $this->baseUrl . '/emby/Items/' . $itemId . '/Images/' . $imageType 
             . '?maxWidth=' . $maxWidth . '&api_key=' . $this->apiKey;
    }
    
    /**
     * Get user image URL
     * @param string $userId User ID
     * @param int $maxWidth Maximum width
     * @return string Image URL
     */
    public function getUserImageUrl($userId, $maxWidth = 50) {
        if (empty($userId)) {
            return '';
        }
        
        return $this->baseUrl . '/emby/Users/' . $userId . '/Images/Primary'
             . '?maxWidth=' . $maxWidth . '&api_key=' . $this->apiKey;
    }
    
    /**
     * Format session data for display
     * @param array $session Raw session data from API
     * @return array Formatted session data
     */
    public function formatSession($session) {
        $nowPlaying = $session['NowPlayingItem'] ?? [];
        $playState = $session['PlayState'] ?? [];
        $transcodeInfo = $session['TranscodingInfo'] ?? null;
        
        // Determine media type
        $type = $nowPlaying['Type'] ?? 'Unknown';
        $mediaType = $nowPlaying['MediaType'] ?? '';
        
        // Build title based on type
        $title = $nowPlaying['Name'] ?? 'Unknown';
        if ($type === 'Episode') {
            $seriesName = $nowPlaying['SeriesName'] ?? '';
            $seasonNum = $nowPlaying['ParentIndexNumber'] ?? 0;
            $episodeNum = $nowPlaying['IndexNumber'] ?? 0;
            if ($seriesName) {
                $title = "{$seriesName} - S{$seasonNum}E{$episodeNum} - {$title}";
            }
        } elseif ($type === 'MusicVideo' || $type === 'Audio') {
            $artist = '';
            if (!empty($nowPlaying['Artists'])) {
                $artist = implode(', ', $nowPlaying['Artists']);
            } elseif (!empty($nowPlaying['AlbumArtist'])) {
                $artist = $nowPlaying['AlbumArtist'];
            }
            if ($artist) {
                $title = "{$artist} - {$title}";
            }
        }
        
        // Calculate progress
        $positionTicks = $playState['PositionTicks'] ?? 0;
        $runtimeTicks = $nowPlaying['RunTimeTicks'] ?? 0;
        $progress = $runtimeTicks > 0 ? round(($positionTicks / $runtimeTicks) * 100, 1) : 0;
        
        // Determine play method
        $playMethod = 'Unknown';
        if ($transcodeInfo !== null) {
            if (($transcodeInfo['IsVideoDirect'] ?? false) && ($transcodeInfo['IsAudioDirect'] ?? false)) {
                $playMethod = 'Direct Play';
            } elseif ($transcodeInfo['IsVideoDirect'] ?? false) {
                $playMethod = 'Direct Stream';
            } else {
                $playMethod = 'Transcode';
            }
        } elseif (isset($playState['PlayMethod'])) {
            $playMethod = $playState['PlayMethod'];
        }
        
        // Get quality info
        $quality = '';
        if (!empty($nowPlaying['MediaStreams'])) {
            foreach ($nowPlaying['MediaStreams'] as $stream) {
                if (($stream['Type'] ?? '') === 'Video') {
                    $width = $stream['Width'] ?? 0;
                    $height = $stream['Height'] ?? 0;
                    if ($height >= 2160) {
                        $quality = '4K';
                    } elseif ($height >= 1080) {
                        $quality = '1080p';
                    } elseif ($height >= 720) {
                        $quality = '720p';
                    } elseif ($height > 0) {
                        $quality = "{$height}p";
                    }
                    
                    // Add codec info
                    $codec = strtoupper($stream['Codec'] ?? '');
                    if ($codec) {
                        $quality .= " {$codec}";
                    }
                    break;
                }
            }
        }
        
        // Transcode details
        $transcodeDetails = null;
        if ($transcodeInfo !== null && $playMethod === 'Transcode') {
            $transcodeDetails = [
                'videoCodec' => $transcodeInfo['VideoCodec'] ?? '',
                'audioCodec' => $transcodeInfo['AudioCodec'] ?? '',
                'bitrate' => $transcodeInfo['Bitrate'] ?? 0,
                'width' => $transcodeInfo['Width'] ?? 0,
                'height' => $transcodeInfo['Height'] ?? 0,
                'completion' => round($transcodeInfo['CompletionPercentage'] ?? 0, 1),
                'hwAccel' => $transcodeInfo['VideoEncoderHwAccel'] ?? '',
                'reasons' => $transcodeInfo['TranscodeReasons'] ?? [],
            ];
        }
        
        return [
            'id' => $session['Id'] ?? '',
            'userId' => $session['UserId'] ?? '',
            'userName' => $session['UserName'] ?? 'Unknown User',
            'client' => $session['Client'] ?? 'Unknown Client',
            'deviceName' => $session['DeviceName'] ?? 'Unknown Device',
            'deviceId' => $session['DeviceId'] ?? '',
            'remoteEndPoint' => $session['RemoteEndPoint'] ?? '',
            
            'itemId' => $nowPlaying['Id'] ?? '',
            'title' => $title,
            'type' => $type,
            'mediaType' => $mediaType,
            'year' => $nowPlaying['ProductionYear'] ?? '',
            
            'isPaused' => $playState['IsPaused'] ?? false,
            'isMuted' => $playState['IsMuted'] ?? false,
            'positionTicks' => $positionTicks,
            'runtimeTicks' => $runtimeTicks,
            'progress' => $progress,
            
            'playMethod' => $playMethod,
            'quality' => $quality,
            'transcodeInfo' => $transcodeDetails,
            
            'imageUrl' => $this->getImageUrl($nowPlaying['Id'] ?? '', 'Primary', 150),
            'userImageUrl' => $this->getUserImageUrl($session['UserId'] ?? ''),
        ];
    }
    
    /**
     * Get formatted list of all playing sessions
     * @return array Formatted sessions
     */
    public function getFormattedPlayingSessions() {
        $sessions = $this->getPlayingSessions();
        $formatted = [];
        
        foreach ($sessions as $session) {
            $formatted[] = $this->formatSession($session);
        }
        
        return $formatted;
    }
}
?>
