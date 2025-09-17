# BeaconGUI

A PocketMine-MP plugin that implements a GUI for Beacon blocks.

## Features

- Fully functional Beacon GUI interface
- Beacon pyramid level calculation (1-4)
- Beacon effects application to nearby players
- Colored beacon beams based on selected effect
- Support for primary and secondary effects
- Payment system using valid beacon payment items

## Requirements

- PocketMine-MP API 5.20.0
- PHP 8.0+

## Installation

1. Download the latest release from [Poggit](https://poggit.pmmp.io/ci/Renz/Beacon)
2. Place the `.phar` file in your server's `plugins` folder
3. Restart your server

## Usage

1. Build a beacon pyramid with 1-4 levels using valid beacon base blocks (Iron, Gold, Diamond, Emerald, or Netherite blocks)
2. Place a beacon block on top of the pyramid
3. Right-click (tap) on the beacon block to open the GUI
4. Place a valid payment item (Iron Ingot, Gold Ingot, Diamond, Emerald, or Netherite Ingot) in the payment slot
5. Select the desired primary and/or secondary effect
6. Click the "Confirm" button to apply the effects

## Valid Beacon Base Blocks

- Iron Block
- Gold Block
- Diamond Block
- Emerald Block
- Netherite Block

## Valid Payment Items

- Iron Ingot
- Gold Ingot
- Diamond
- Emerald
- Netherite Ingot

## Beacon Effects

### Primary Effects (Available at all levels)

- Speed
- Haste
- Resistance
- Jump Boost
- Strength

### Secondary Effects (Available at level 4 only)

- Regeneration

## Beacon Levels

- Level 1: 3x3 base (9 blocks) - Primary effect level 1
- Level 2: 5x5 base (25 blocks) - Primary effect level 1
- Level 3: 7x7 base (49 blocks) - Primary effect level 1
- Level 4: 9x9 base (81 blocks) - Primary effect level 2 + Secondary effect

## Commands

- `/beacon reload` - Reload the plugin configuration
- `/beacon status` - Show the status of all beacons in the server
- `/beacon info` - Display plugin information

## Permissions

- `beacon.use` - Allow players to use beacon GUIs (default: true)
- `beacon.admin` - Allow players to manage beacon configurations (default: op)

## License

This plugin is licensed under the GPL-3.0 License. See the LICENSE file for details.
