/*
 * Stream Volume Faders
 *
 * Slot 1 (Master / Primary) is always available and is backed by FPP's core
 * api/system/volume endpoint. Slots 2-5 are fppd's StreamSlotManager stream
 * slots, only present on GStreamer-backed builds and only settable while a
 * stream is actively playing on that slot (Set Slot Volume no-ops on an idle
 * slot). Since fppd doesn't persist a per-slot volume, the last value chosen
 * for slots 2-5 is kept in localStorage and re-applied automatically the
 * moment a slot's status flips from idle to playing.
 */
(function () {
    'use strict';

    var POLL_INTERVAL_MS = 2000;
    var SLIDER_DEBOUNCE_MS = 150;
    var MAX_SLOT = 5;
    var STORAGE_PREFIX = 'fppStreamVolume_slot_';

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

    function setSliderValue(slot, value) {
        var input = document.getElementById('streamVolumeSlider_' + slot);
        var label = document.getElementById('streamVolumeLabel_' + slot);
        if (input) {
            input.value = value;
        }
        if (label) {
            label.textContent = value + '%';
        }
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

    document.addEventListener('DOMContentLoaded', init);

    window.StreamVolumeSliderInput = onSliderInput;
    window.StreamVolumeSliderChange = onSliderChange;
})();
