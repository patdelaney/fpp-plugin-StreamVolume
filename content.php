<?php
/*
 * Stream Volume Faders - main control page.
 *
 * One fader per fppd audio stream:
 *  - Slot 1 ("Master / Primary") always controls the system's overall
 *    output volume via the core api/system/volume endpoint.
 *  - Slots 2-5 map to fppd's StreamSlotManager ("Set Slot Volume" /
 *    "Media Slot Status" commands), which only exist on GStreamer-backed
 *    builds. js/streamvolume.js probes for them at load and hides that
 *    row group if the build doesn't support them.
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
				<td class="fw-semibold">Master / Primary</td>
				<td><span id="streamVolumeStatus_1" class="badge text-bg-success">Active</span></td>
				<td>
					<div class="d-flex align-items-center gap-2">
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
				<td class="fw-semibold">Stream Slot <?= $slot ?></td>
				<td><span id="streamVolumeStatus_<?= $slot ?>" class="badge text-bg-secondary">Idle</span></td>
				<td>
					<div class="d-flex align-items-center gap-2">
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
