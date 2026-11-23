import apiFetch from '@wordpress/api-fetch';
import { createRoot, render } from '@wordpress/element';
import '../shared/tokens.css';
import './admin.css';
import { App } from './App';

const config = window.rankmyseoAdmin;
if ( config?.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
}

const mountNode = document.getElementById( 'rankmyseo-admin-root' );

if ( mountNode ) {
	if ( typeof createRoot === 'function' ) {
		createRoot( mountNode ).render( <App /> );
	} else {
		render( <App />, mountNode );
	}
}
