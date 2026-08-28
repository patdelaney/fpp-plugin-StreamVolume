# fpp-plugin-StreamVolume

Volume fader control panel for [Falcon Player (FPP)](https://github.com/FalconChristmas/fpp),
built from the [fpp-plugin-Template](https://github.com/FalconChristmas/fpp-plugin-Template).

Adds a **Stream Volume** page (Status/Control menu) with one live fader per fppd audio stream:

- **Master / Primary** — always shown; controls the system's overall output volume (the same
  value as the volume slider on the main Status page), via FPP's core `api/system/volume`
  endpoint.
- **Stream Slots 2-5** — fppd's independent `StreamSlotManager` stream slots, used when a
  `Play Media` command or playlist entry targets a stream slot other than 1. These only exist
  on GStreamer-backed builds of fppd (`Set Slot Volume` / `Media Slot Status` commands); if
  they aren't available, the page falls back to showing just the Master fader.

Each fader shows its stream's current volume and updates fppd live as you drag it. A stream
slot's volume can only be applied while something is actively playing on it — fppd doesn't
persist per-slot volume — so the plugin remembers the last value you set for each slot (in the
browser) and re-applies it automatically the moment that slot starts playing again.

## Installation

Install from **Content Setup → Plugins** in the FPP web UI, then restart fppd.

## Usage

**Status/Control → Stream Volume** in the FPP web UI.

## License

GPLv2 — see [LICENSE](LICENSE).
