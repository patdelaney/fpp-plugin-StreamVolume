<fieldset class="border rounded p-3 mb-3">
	<legend class="w-auto px-2 fs-6">Help</legend>
	<p>
		This plugin adds a <strong>Stream Volume</strong> page under Status/Control with one
		fader per fppd audio stream.
	</p>
	<ul>
		<li>
			<strong>Master / Primary</strong> is always shown and controls the system's overall
			output volume (the same value as the volume slider on the main Status page).
		</li>
		<li>
			<strong>Stream Slots 2-5</strong> control fppd's independent stream slots, used when
			a <code>Play Media</code> command or playlist entry targets a stream slot other than
			1. These only exist on GStreamer-backed builds of fppd; if they're not available on
			this system, only the Master fader is shown.
		</li>
		<li>
			A stream slot's volume can only be changed while something is actively playing on it.
			Moving its fader while idle still remembers the value in this browser and applies it
			automatically the next time that slot starts playing.
		</li>
	</ul>
</fieldset>

<fieldset class="border rounded p-3">
	<legend class="w-auto px-2 fs-6">Info</legend>
	<p>Developed by patdelaney.</p>
	<p>
		<a href="https://github.com/patdelaney/fpp-plugin-StreamVolume" target="_blank">GitHub Repository</a>
		&nbsp;|&nbsp;
		<a href="https://github.com/patdelaney/fpp-plugin-StreamVolume/issues" target="_blank">Report a Bug</a>
	</p>
</fieldset>
