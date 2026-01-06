# Emby Streams - Unraid Plugin

Display active Emby media streams directly on your Unraid dashboard with real-time updates.

![Version](https://img.shields.io/badge/version-2026.01.05-blue)
![Unraid](https://img.shields.io/badge/unraid-6.9.0%2B-orange)
![License](https://img.shields.io/badge/license-GPL--2.0-green)

## Features

- 📊 **Real-time Dashboard Widget** - See active streams at a glance
- 👥 **User Information** - Shows who's watching what
- 📺 **Media Details** - Display title, progress, and client device
- ⏯️ **Playback Status** - Visual indicators for playing/paused content
- 🔄 **Auto-refresh** - Updates every 30 seconds
- 🎨 **Movable Tile** - Drag and position anywhere on your dashboard
- ⚡ **Connection Testing** - Verify Emby connectivity before saving

## Screenshots

![Dashboard Widget](https://via.placeholder.com/800x400?text=Dashboard+Screenshot)
*Add your actual screenshot here*

![Settings Page](https://via.placeholder.com/800x400?text=Settings+Screenshot)
*Add your actual screenshot here*

## Installation

### Method 1: Community Applications (Recommended)
1. Open Unraid WebGUI
2. Go to **Apps** tab
3. Search for "Emby Streams"
4. Click **Install**

### Method 2: Manual Installation
1. Go to **Plugins** → **Install Plugin**
2. Paste this URL:
   ```
   https://raw.githubusercontent.com/YOUR_GITHUB_USERNAME/unraid-embystreams/main/embystreams.plg
   ```
3. Click **Install**

## Configuration

1. Navigate to **Settings** → **Emby Streams**
2. Enter your **Emby Server URL**
   - Example: `http://192.168.1.100:8096`
   - No trailing slash
3. Enter your **API Key**
   - Find in Emby: **Dashboard** → **Advanced** → **API Keys**
4. Click **Test Connection** to verify
5. Click **Apply** to save

## Dashboard Setup

1. Go to your **Dashboard**
2. Click the **padlock icon** to unlock
3. Drag the **Emby Streams** tile to your preferred position
4. Resize as needed
5. Lock the dashboard when done

## Requirements

- Unraid 6.9.0 or higher
- Emby Server (any recent version)
- Network connectivity between Unraid and Emby
- Valid Emby API key

## Troubleshooting

### No streams appearing
- Verify Emby URL is correct (no trailing slash)
- Check API key is valid
- Ensure Emby server is accessible from Unraid
- Try the "Test Connection" button

### Widget not movable
- Click the padlock icon on dashboard to unlock
- Refresh the page if needed

### Connection errors
- Check firewall settings
- Verify Emby is running
- Test URL in browser: `http://YOUR_EMBY_IP:8096`

## Development

### Project Structure
```
unraid-embystreams/
├── embystreams.plg              # Main plugin installer
├── src/
│   ├── embystreams.php          # PHP backend
│   ├── EmbyStreamsDash.page     # Dashboard widget
│   ├── EmbyStreamsSettings.page # Settings page
│   ├── embystreams.png          # Plugin icon
│   └── README.md                # Plugin docs
└── README.md                    # This file
```

### Building from Source
1. Clone the repository:
   ```bash
   git clone https://github.com/YOUR_GITHUB_USERNAME/unraid-embystreams.git
   ```

2. Make your changes in the `src/` directory

3. Update version in `embystreams.plg`:
   ```xml
   <!ENTITY version "YYYY.MM.DD">
   ```

4. Add changelog entry in `<CHANGES>` section

5. Commit and push to GitHub

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Support

- 🐛 **Report bugs**: [GitHub Issues](https://github.com/YOUR_GITHUB_USERNAME/unraid-embystreams/issues)
- 💬 **Discuss**: [Unraid Forums](https://forums.unraid.net/)
- 📖 **Documentation**: [Wiki](https://github.com/YOUR_GITHUB_USERNAME/unraid-embystreams/wiki)

## Changelog

### 2026.01.05
- Initial release
- Dashboard widget with real-time updates
- Settings page with connection testing
- Support for pause/play indicators
- Client device information display

## License

This project is licensed under the GNU General Public License v2.0 - see the [LICENSE](LICENSE) file for details.

## Credits

- Inspired by the [Plex Streams plugin](https://github.com/dorgan/Unraid-plexstreams)
- Built for the Unraid community

## Donations

If you find this plugin useful, consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 📝 Contributing improvements
- ☕ [Buy me a coffee](https://www.buymeacoffee.com/YOURNAME)

---

**Note**: This plugin is not affiliated with Emby or Unraid. All trademarks belong to their respective owners.
