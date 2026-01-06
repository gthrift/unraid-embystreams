# EmbyStreams

An UnRAID plugin that displays currently active Emby streaming sessions on your dashboard.

## Features

- **Dashboard Widget**: Real-time view of all active Emby streams directly on your UnRAID dashboard
- **Stream Details**: Shows user, client, quality, play method (Direct Play/Transcode)
- **Progress Tracking**: Visual progress bar and time display for each stream
- **Thumbnails**: Optional media thumbnails for easy identification
- **Transcode Info**: See at a glance which streams are transcoding
- **Auto-Refresh**: Configurable refresh interval
- **Clean Upgrades**: Configuration preserved during plugin updates

## Installation

### Via Community Applications
Search for "EmbyStreams" in Community Applications and click Install.

### Manual Installation
```
https://raw.githubusercontent.com/YourUsername/Unraid-embystreams/main/embystreams.plg
```

## Configuration

After installation:

1. Go to **Settings → Emby Streams**
2. Enter your Emby Server URL (e.g., `http://192.168.1.100:8096`)
3. Enter your Emby API Key
   - Generate at: Emby Dashboard → Settings → Advanced → API Keys
4. Click **Test Connection** to verify
5. Adjust display settings as desired
6. Click **Apply**

### Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Emby Server URL | Full URL including port | Required |
| API Key | Emby API key | Required |
| Refresh Interval | Update frequency (seconds) | 10 |
| Show Thumbnails | Display media thumbnails | Yes |
| Show Transcode Info | Show play method badges | Yes |
| Max Streams | Limit displayed streams | 10 |
| Show Idle Sessions | Include non-playing sessions | No |

## Dashboard Widget

The dashboard widget displays:

- Media title (with series/season/episode info for TV)
- User name
- Client/device name
- Video quality (4K, 1080p, etc.)
- Play method (Direct Play, Direct Stream, Transcode)
- Progress bar with elapsed/total time
- Play/pause status

## Emby API

This plugin uses the following Emby API endpoints:

- `GET /Sessions` - Get active sessions
- `GET /System/Info/Public` - Test connection and get server info
- `GET /Items/{id}/Images/Primary` - Media thumbnails

## Requirements

- UnRAID 6.12.0 or later
- Emby Server with API access enabled
- Network connectivity between UnRAID and Emby server

## Changelog

### 2025.01.06
- Initial release
- Dashboard widget with real-time stream display
- Settings page with connection test
- Thumbnail support
- Transcode indicator badges
- Progress tracking
- Auto-refresh functionality

## Troubleshooting

### "Cannot connect to Emby server"
- Verify the URL is correct and includes the port
- Check that Emby is running and accessible
- Ensure the API key is valid
- Check firewall settings

### Widget shows "No active streams" but Emby is playing
- Verify the API key has sufficient permissions
- Check the refresh interval isn't too long
- Try refreshing the dashboard

### Thumbnails not loading
- Ensure "Show Thumbnails" is enabled
- Check that the Emby server is accessible from UnRAID
- Some media may not have thumbnails available

## Support

Please report issues on GitHub: [Issues](https://github.com/YourUsername/Unraid-embystreams/issues)

## Credits

Inspired by the [plexstreams](https://github.com/dorgan/Unraid-plexstreams) plugin by dorgan.

## License

GNU General Public License v2.0
