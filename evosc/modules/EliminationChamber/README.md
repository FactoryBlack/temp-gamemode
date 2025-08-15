# EliminationChamber

A server-authoritative elimination gamemode for EvoSC where players compete with limited lives until only one (or N) survivors remain.

## Features

### Core Gameplay
- **Lives System**: Players start with configurable lives (default: 3)
- **Safe Status**: Finish a map to become Safe and auto-spectate for that round
- **Elimination**: Unsafe players lose a life at map end; zero lives = elimination
- **Skip Voting**: Only Unsafe players can vote to skip maps (majority threshold)
- **Carry-over**: Safe status persists to next map unless player voted to skip

### TMX Integration
- **Random Maps**: Automatically fetches random maps from TrackMania Exchange
- **Smart Filtering**: Configurable filters for style, length, difficulty, upload date
- **Queue Management**: Maintains a queue of maps to ensure continuous gameplay
- **Auto-download**: Downloads and adds GBX files to matchsettings automatically

### User Interface
- **Persistent HUD**: Shows lives, status, skip votes, and optional round timer
- **Popups**: Notifications for becoming Safe or getting eliminated
- **Admin Panel**: Compact overlay for session management
- **Responsive Design**: Scales properly across different screen ratios

## Installation

1. **Copy Files**: Place the module files in your EvoSC modules directory:
   \`\`\`
   evosc/modules/EliminationChamber/
   \`\`\`

2. **Copy GameMode**: Place the script in your Trackmania modes directory:
   \`\`\`
   scripts/Trackmania/Modes/Trackmania/EliminationChamber.Script.txt
   \`\`\`

3. **Create Directories**: Ensure these directories exist:
   \`\`\`
   UserData/Maps/EliminationChamber/
   UserData/Maps/MatchSettings/
   \`\`\`

4. **Configure**: Edit `Config/config.json` to match your server setup

5. **Permissions**: Grant `elimination_chamber` access right to admins

6. **Restart**: Restart EvoSC to load the module

## Configuration

### Basic Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `livesStart` | 3 | Starting lives for each player |
| `skipThresholdPercent` | 51 | Percentage of Unsafe players needed to skip |
| `roundTimeoutSec` | 300 | Round timeout in seconds (0 = disabled) |
| `winnersCount` | 1 | Stop when N players remain |

### TMX Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `tmxApiKey` | "" | TMX API key (optional, for higher rate limits) |
| `tmxFilters.style` | "Race" | Map style filter |
| `tmxFilters.minLength` | 30 | Minimum author time (seconds) |
| `tmxFilters.maxLength` | 90 | Maximum author time (seconds) |
| `tmxFilters.uploadedAfter` | "2020-01-01" | Only maps uploaded after this date |

### Paths

| Setting | Default | Description |
|---------|---------|-------------|
| `mapsPath` | "UserData/Maps/EliminationChamber/" | Directory for downloaded maps |
| `matchsettingsPath` | "UserData/Maps/MatchSettings/elimination_chamber.txt" | Matchsettings file |

## Commands

### Player Commands
- `/skip` - Vote to skip current map (Unsafe players only)

### Admin Commands
- `/ec start [lives]` - Start session with optional lives count
- `/ec stop` - Stop current session
- `/ec lives <number>` - Set lives for next session (1-10)
- `/ec next` - Force next map
- `/ec status` - Show session status

## Gameplay Rules

### Session Flow
1. Admin starts session with `/ec start [lives]`
2. System fetches random maps from TMX matching filters
3. Players spawn with configured lives
4. Round begins with all players as Unsafe

### During a Round
- **Finish**: Player becomes Safe and is forced to spectate
- **Skip Voting**: Unsafe players can `/skip`; majority triggers instant next map
- **Timeout**: Round ends after configured time (optional)
- **All Safe**: Round ends early if everyone finishes

### End of Round
- **Unsafe players**: Lose one life
- **Zero lives**: Eliminated (permanent spectator)
- **Skip voters**: Lose carry-over Safe status for next map
- **Safe players**: Keep Safe status for next map

### Session End
- Stops when only N players have lives > 0 (configurable)
- Admin can stop manually with `/ec stop`

## HUD Elements

### Lives Pill
- Shows current lives count
- Updates in real-time

### Status Badge
- **SAFE** (green): Player finished and is spectating
- **UNSAFE** (amber/red): Player can still race and lose lives
- **ELIMINATED** (red): Player has no lives left

### Skip Meter
- Shows skip vote progress: "X/Y Unsafe"
- Progress bar fills as votes accumulate
- Only visible when votes are active

### Round Timer (Optional)
- Countdown timer if round timeout is enabled
- Format: "M:SS"

## Technical Details

### Architecture
- **ManiaScript**: Server-authoritative gamemode logic
- **PHP Module**: TMX integration and session management
- **XML Views**: HUD components and popups
- **JSON Config**: Centralized configuration

### Data Flow
1. EvoSC fetches maps from TMX API
2. Downloads GBX files and updates matchsettings
3. Sets mode script settings and starts gamemode
4. Mode script manages player states and emits events
5. EvoSC HUD subscribes to script values and updates display

### Script Communication
The gamemode exposes these global variables for EvoSC:
- `G_PlayerStates[]` - Player status array
- `G_PlayerLives[]` - Lives count array
- `G_SafePlayers[]` - List of Safe player logins
- `G_UnsafeCount` - Count of Unsafe players
- `G_SkipVotes` - Current skip vote count
- `G_SkipThreshold` - Votes needed to skip
- `G_RoundActive` - Whether round is active
- `G_RoundTimeLeft` - Seconds remaining (if timeout enabled)

## Troubleshooting

### Common Issues

**Maps not downloading**
- Check TMX API connectivity
- Verify `mapsPath` directory exists and is writable
- Check TMX filters aren't too restrictive

**HUD not showing**
- Ensure `hudEnabled` is true in config
- Check player has proper permissions
- Verify ManiaLink XML syntax

**Skip votes not working**
- Only Unsafe players can vote
- Check `skipThresholdPercent` setting
- Ensure enough Unsafe players are present

**Session won't start**
- Check EvoSC logs for errors
- Verify gamemode script is in correct location
- Ensure required directories exist

### Debug Mode
Enable detailed logging by setting `logging.level` to `"debug"` in config.

## Acceptance Tests

The gamemode has been tested for these scenarios:

✅ **Finishing sets Safe and forces spectate immediately**
✅ **Safe players cannot leave spectator until next map**
✅ **Unsafe-only /skip counts; majority triggers instant next map**
✅ **Skippers do not carry Safe into the next map**
✅ **End-of-map removes one life from all remaining Unsafe**
✅ **Elimination forces spectator for the session**
✅ **TMX fetch adds a playable random map within filters**
✅ **HUD shows correct lives, status, and skip percent at all times**

## Version History

### v1.0.0
- Initial release
- Core elimination gameplay
- TMX integration
- HUD system
- Admin commands

## Support

For issues, feature requests, or contributions, please contact the EvoSC community or check the project repository.

## License

This module is released under the same license as EvoSC.
