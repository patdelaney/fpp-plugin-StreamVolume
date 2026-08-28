<?php
/*
 * Stream Volume Faders - main control page.
 *
 * One fader per fppd audio stream:
 *  - Slot 1 ("Master / Primary") always controls the system's overall
 *    output volume via the core api/system/volume endpoint.
 *  - Slots 2-5 map to fppd's StreamSlotManager ("Set Slot Volume" /
 *    "Media Slot Status" commands), which only exist on GStreamer-backed
 *    builds. The inline script below probes for them at load and hides
 *    that row group if the build doesn't support them.
 *
 * The control script is inlined here (rather than a separate js/*.js file)
 * deliberately: FPP's plugin.php serves plugin JS/CSS through its generic
 * ?file= route, which never gets the ?ref=<filemtime> cache-buster FPP's
 * own bundled assets use - so Apache's "cache everything for a year,
 * immutable" default (etc/apache2.site) sticks a plugin script's very
 * first version in every visitor's browser forever, immune to reloads.
 * This page (?page=content.php) isn't subject to that, so the same pattern
 * FPP's own live-control admin pages use (pipewire-audio.php,
 * pipewire-routing-matrix.php) - inline <script>, not a cached asset -
 * avoids it here too.
 */
?>
<p>
	Adjust the volume of each active fppd media stream. The <strong>Master / Primary</strong>
	fader controls the system's overall output volume. Stream Slots 2-5 correspond to
	additional simultaneous streams started via a <code>Play Media</code> command or a
	playlist entry configured with a non-default stream slot.
</p>

<div class="table-responsive">
	<table class="table align-middle">
		<thead>
			<tr>
				<th>Stream</th>
				<th>Status</th>
				<th>Volume</th>
			</tr>
		</thead>
		<tbody>
			<tr id="streamVolumeRow_1">
				<td class="fw-semibold py-3">Master / Primary</td>
				<td class="py-3"><span id="streamVolumeStatus_1" class="badge text-bg-success">Active</span></td>
				<td class="py-3">
					<div class="d-flex align-items-center gap-2">
						<button type="button" class="btn btn-sm btn-outline-secondary"
							id="streamVolumeMuteBtn_1" onclick="StreamVolumeMuteToggle(1)" title="Mute">
							<i class="fas fa-volume-up" id="streamVolumeMuteIcon_1"></i>
						</button>
						<input type="range" class="form-range" min="0" max="100" value="70"
							id="streamVolumeSlider_1"
							oninput="StreamVolumeSliderInput(1)"
							onchange="StreamVolumeSliderChange(1)">
						<span id="streamVolumeLabel_1" class="text-nowrap">70%</span>
					</div>
				</td>
			</tr>
		</tbody>
		<tbody id="streamVolumeExtraSlots">
			<?php for ($slot = 2; $slot <= 5; $slot++): ?>
			<tr id="streamVolumeRow_<?= $slot ?>">
				<td class="fw-semibold py-3">Stream Slot <?= $slot ?></td>
				<td class="py-3"><span id="streamVolumeStatus_<?= $slot ?>" class="badge text-bg-secondary">Idle</span></td>
				<td class="py-3">
					<div class="d-flex align-items-center gap-2">
						<button type="button" class="btn btn-sm btn-outline-secondary"
							id="streamVolumeMuteBtn_<?= $slot ?>" onclick="StreamVolumeMuteToggle(<?= $slot ?>)" title="Mute">
							<i class="fas fa-volume-up" id="streamVolumeMuteIcon_<?= $slot ?>"></i>
						</button>
						<input type="range" class="form-range" min="0" max="100" value="70"
							id="streamVolumeSlider_<?= $slot ?>"
							oninput="StreamVolumeSliderInput(<?= $slot ?>)"
							onchange="StreamVolumeSliderChange(<?= $slot ?>)">
						<span id="streamVolumeLabel_<?= $slot ?>" class="text-nowrap">70%</span>
					</div>
				</td>
			</tr>
			<?php endfor; ?>
		</tbody>
	</table>
</div>

<div id="streamVolumeNoExtraSlots" class="alert alert-secondary d-none">
	Stream Slots 2-5 are not available on this build of fppd (requires a GStreamer-enabled
	build). Only the Master / Primary fader above is usable.
</div>

<script type="text/javascript">
(function () {
    'use strict';

    var POLL_INTERVAL_MS = 2000;
    var SLIDER_DEBOUNCE_MS = 150;
    var MAX_SLOT = 5;
    var STORAGE_PREFIX = 'fppStreamVolume_slot_';
    var PREMUTE_STORAGE_PREFIX = 'fppStreamVolume_premute_';

    var extraSlotsAvailable = true; // optimistic until the first probe says otherwise
    var slotActive = {};
    var sliderDragging = {};
    var debounceTimers = {};

    function loadStoredVolume(slot) {
        try {
            var v = window.localStorage.getItem(STORAGE_PREFIX + slot);
            if (v !== null) {
                var n = parseInt(v, 10);
                if (!isNaN(n)) {
                    return Math.min(100, Math.max(0, n));
                }
            }
        } catch (e) {
            // localStorage unavailable (private browsing, etc) - fall through to default
        }
        return 70;
    }

    function storeVolume(slot, value) {
        try {
            window.localStorage.setItem(STORAGE_PREFIX + slot, value);
        } catch (e) {
            // ignore - nothing to persist to, slider still works for this session
        }
    }

    // Remembers the last non-zero volume seen for a slot, so the Mute button
    // has something sensible to restore to on Unmute.
    function loadPreMuteVolume(slot) {
        try {
            var v = window.localStorage.getItem(PREMUTE_STORAGE_PREFIX + slot);
            if (v !== null) {
                var n = parseInt(v, 10);
                if (!isNaN(n) && n > 0) {
                    return Math.min(100, n);
                }
            }
        } catch (e) {
            // localStorage unavailable - fall through to default
        }
        return 70;
    }

    function storePreMuteVolume(slot, value) {
        if (value <= 0) {
            return;
        }
        try {
            window.localStorage.setItem(PREMUTE_STORAGE_PREFIX + slot, value);
        } catch (e) {
            // ignore
        }
    }

    function updateMuteButton(slot, value) {
        var btn = document.getElementById('streamVolumeMuteBtn_' + slot);
        var icon = document.getElementById('streamVolumeMuteIcon_' + slot);
        if (!btn || !icon) {
            return;
        }
        var muted = value <= 0;
        btn.className = 'btn btn-sm ' + (muted ? 'btn-danger' : 'btn-outline-secondary');
        btn.title = muted ? 'Unmute' : 'Mute';
        icon.className = 'fas ' + (muted ? 'fa-volume-mute' : 'fa-volume-up');
    }

    function setSliderValue(slot, value) {
        var input = document.getElementById('streamVolumeSlider_' + slot);
        var label = document.getElementById('streamVolumeLabel_' + slot);
        if (input) {
            input.value = value;
        }
        if (label) {
            label.textContent = value + '%';
        }
        updateMuteButton(slot, value);
    }

    function setSlotStatus(slot, active, filename) {
        slotActive[slot] = active;
        var badge = document.getElementById('streamVolumeStatus_' + slot);
        if (!badge) {
            return;
        }
        if (active) {
            badge.textContent = filename ? filename : 'Active';
            badge.className = 'badge text-bg-success';
        } else {
            badge.textContent = 'Idle';
            badge.className = 'badge text-bg-secondary';
        }
    }

    function applySlotVolume(slot, value) {
        if (slot === 1) {
            fetch('api/system/volume', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ volume: value })
            }).catch(function () {
                // transient network error - next poll/drag will resync
            });
            return;
        }

        // GET form of api/command/{command}/{args...}; a non-2xx response just
        // means the slot is currently idle (Set Slot Volume no-ops on it) -
        // the stored value gets pushed automatically once it goes active.
        fetch('api/command/Set%20Slot%20Volume/' + slot + '/' + value).catch(function () {
            // transient network error - ignore
        });
    }

    function onSliderInput(slot) {
        var input = document.getElementById('streamVolumeSlider_' + slot);
        var value = parseInt(input.value, 10);
        setSliderValue(slot, value);
        storeVolume(slot, value);
        storePreMuteVolume(slot, value);
        sliderDragging[slot] = true;

        if (debounceTimers[slot]) {
            clearTimeout(debounceTimers[slot]);
        }
        debounceTimers[slot] = setTimeout(function () {
            applySlotVolume(slot, value);
        }, SLIDER_DEBOUNCE_MS);
    }

    function onSliderChange(slot) {
        var input = document.getElementById('streamVolumeSlider_' + slot);
        var value = parseInt(input.value, 10);
        if (debounceTimers[slot]) {
            clearTimeout(debounceTimers[slot]);
        }
        applySlotVolume(slot, value);
        // Give the just-sent request a moment before letting the poller
        // overwrite the slider again.
        setTimeout(function () {
            sliderDragging[slot] = false;
        }, SLIDER_DEBOUNCE_MS + 50);
    }

    function onMuteToggle(slot) {
        var input = document.getElementById('streamVolumeSlider_' + slot);
        var current = parseInt(input.value, 10);
        var newValue;
        if (current > 0) {
            storePreMuteVolume(slot, current);
            newValue = 0;
        } else {
            newValue = loadPreMuteVolume(slot);
        }
        setSliderValue(slot, newValue);
        storeVolume(slot, newValue);
        applySlotVolume(slot, newValue);
    }

    function refreshMasterVolume() {
        if (sliderDragging[1]) {
            return;
        }
        fetch('api/system/volume')
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (typeof data.volume === 'number' && !sliderDragging[1]) {
                    setSliderValue(1, data.volume);
                }
            })
            .catch(function () {
                // fppd not reachable right now - leave the last known value showing
            });
    }

    function showExtraSlots(available) {
        if (available === extraSlotsAvailable) {
            return;
        }
        extraSlotsAvailable = available;
        var group = document.getElementById('streamVolumeExtraSlots');
        var notice = document.getElementById('streamVolumeNoExtraSlots');
        if (group) {
            group.classList.toggle('d-none', !available);
        }
        if (notice) {
            notice.classList.toggle('d-none', available);
        }
    }

    function refreshSlotStatuses() {
        fetch('api/command/Media%20Slot%20Status')
            .then(function (resp) {
                if (!resp.ok) {
                    throw new Error('Media Slot Status not available');
                }
                return resp.text();
            })
            .then(function (text) {
                showExtraSlots(true);
                var slots = JSON.parse(text);
                slots.forEach(function (s) {
                    var slot = s.slot;
                    if (slot === 1 || slot > MAX_SLOT) {
                        return;
                    }
                    var wasActive = !!slotActive[slot];
                    var isActive = s.status === 'playing';
                    setSlotStatus(slot, isActive, s.mediaFilename);
                    if (isActive && !wasActive && !sliderDragging[slot]) {
                        applySlotVolume(slot, loadStoredVolume(slot));
                    }
                });
            })
            .catch(function () {
                showExtraSlots(false);
            });
    }

    function poll() {
        refreshMasterVolume();
        refreshSlotStatuses();
    }

    function init() {
        for (var slot = 2; slot <= MAX_SLOT; slot++) {
            setSliderValue(slot, loadStoredVolume(slot));
        }
        poll();
        setInterval(poll, POLL_INTERVAL_MS);
    }

    init();

    window.StreamVolumeSliderInput = onSliderInput;
    window.StreamVolumeSliderChange = onSliderChange;
    window.StreamVolumeMuteToggle = onMuteToggle;
})();
</script>
