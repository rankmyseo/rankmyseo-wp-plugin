import { mountElementorPanel } from './mount';

const settings = window.rankmyseoElementor;

function start(): void {
	if ( ! settings ) {
		return;
	}
	try {
		mountElementorPanel( document, settings );
	} catch {
		// Leave the Elementor editor usable when its markup changes.
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
