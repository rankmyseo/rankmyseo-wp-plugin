export interface LiveDocument {
	html: string;
	title: string;
	slug: string;
	postId: number;
}

export interface ElementorLike {
	config?: {
		document?: { id?: number };
		initial_document?: { id?: number };
		post_id?: number;
	};
	settings?: {
		page?: {
			model?: {
				get?: ( key: string ) => unknown;
			};
		};
	};
	$preview?: Array< { contentDocument?: Document | null } | null >;
}

export const DIVI_CONTENT_SELECTORS = [
	'#et-fb-app iframe',
	'iframe#et-fb-preview',
	'.et-fb-post-content',
	'#et-boc',
	'.et_pb_post_content',
	'#divi-visual-builder',
	'[data-app-name="visual-builder"]',
];

export const DIVI_CHROME_SELECTORS = [
	'#et-fb-app .et-fb-page-settings-bar',
	'#et-fb-app header',
	'.et-vb-app-bar',
	'#divi-visual-builder .et-vb-app-bar',
	'[data-app-name="visual-builder"] header',
];

export function emptyDocument(): LiveDocument {
	return { html: '', title: '', slug: '', postId: 0 };
}

function textValue( node: Element | null ): string {
	if (
		node instanceof HTMLInputElement ||
		node instanceof HTMLTextAreaElement
	) {
		return node.value;
	}
	return '';
}

function htmlFromRoot( root: ParentNode | null | undefined ): string {
	if ( ! root || typeof root.querySelector !== 'function' ) {
		return '';
	}
	try {
		const elementor = root.querySelector( '.elementor' );
		const node = elementor ?? root;
		// Preview iframes have their own window, so instanceof Element fails.
		if (
			node &&
			'innerHTML' in node &&
			'string' === typeof ( node as { innerHTML?: unknown } ).innerHTML
		) {
			return ( node as { innerHTML: string } ).innerHTML;
		}
	} catch {
		return '';
	}
	return '';
}

function previewDocument(
	doc: Document,
	elementor?: ElementorLike | null
): Document | null {
	const iframe = doc.getElementById( 'elementor-preview-iframe' );
	if ( iframe instanceof HTMLIFrameElement && iframe.contentDocument ) {
		return iframe.contentDocument;
	}
	const preview = elementor?.$preview?.[ 0 ];
	return preview?.contentDocument ?? null;
}

export function readElementorDocument(
	doc: Document,
	elementor?: ElementorLike | null
): LiveDocument {
	const live = emptyDocument();
	try {
		live.html = htmlFromRoot(
			previewDocument( doc, elementor )?.body ?? null
		);
		const model = elementor?.settings?.page?.model;
		const title = model?.get?.( 'post_title' );
		const slug = model?.get?.( 'post_name' );
		live.title = typeof title === 'string' ? title : '';
		live.slug = typeof slug === 'string' ? slug : '';
		const config = elementor?.config;
		const id =
			config?.document?.id ??
			config?.initial_document?.id ??
			config?.post_id ??
			0;
		live.postId = Number.isFinite( Number( id ) ) ? Number( id ) : 0;
	} catch {
		return emptyDocument();
	}
	return live;
}

export function readDiviDocument( doc: Document ): LiveDocument {
	const live = emptyDocument();
	try {
		for ( const selector of DIVI_CONTENT_SELECTORS ) {
			const node = doc.querySelector( selector );
			if ( node instanceof HTMLIFrameElement ) {
				const frame =
					node.contentDocument?.querySelector(
						'.et-l, .et_pb_section, body'
					) ?? node.contentDocument?.body;
				const html = htmlFromRoot( frame ?? null );
				if ( html.trim() ) {
					live.html = html;
					break;
				}
			} else if ( node instanceof HTMLElement && node.innerHTML.trim() ) {
				live.html = node.innerHTML;
				break;
			}
		}
		live.title = textValue(
			doc.querySelector(
				'#et-fb-title, #title, input[name="post_title"]'
			)
		);
		live.slug = textValue(
			doc.querySelector( '#post_name, input[name="post_name"]' )
		);
	} catch {
		return emptyDocument();
	}
	return live;
}
