import { mountDiviPanel } from './mount';

const settings = window.rankmyseoDivi;

function start(): void {
	if ( ! settings ) {
		return;
	}
	try {
		mountDiviPanel( document, settings );
	} catch {
		// Divi 4 and Divi 5 do not share one DOM. The builder keeps working.
	}
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
