type EditFn = ( ( id: number | string ) => void ) & {
	__rankmyseo?: boolean;
};

interface InlineEditApi {
	edit: EditFn;
}

function fillQuickEdit( id: number | string ): void {
	const row = document.getElementById( `post-${ id }` );
	const editRow = document.getElementById( `edit-${ id }` );
	const data = row?.querySelector( '.rankmyseo-inline-data' );
	if ( ! ( data instanceof HTMLElement ) || ! editRow ) {
		return;
	}

	const title = editRow.querySelector( 'input[name="rankmyseo_qe_title"]' );
	const description = editRow.querySelector(
		'textarea[name="rankmyseo_qe_description"]'
	);
	const focus = editRow.querySelector( 'input[name="rankmyseo_qe_focus"]' );
	const noindex = editRow.querySelector(
		'input[name="rankmyseo_qe_noindex"]'
	);

	if ( title instanceof HTMLInputElement ) {
		title.value = data.dataset.title || '';
	}
	if ( description instanceof HTMLTextAreaElement ) {
		description.value = data.dataset.description || '';
	}
	if ( focus instanceof HTMLInputElement ) {
		focus.value = data.dataset.focus || '';
	}
	if ( noindex instanceof HTMLInputElement ) {
		noindex.checked = data.dataset.noindex === '1';
	}
}

const inlineEdit = ( window as Window & { inlineEditPost?: InlineEditApi } )
	.inlineEditPost;

if ( inlineEdit && ! inlineEdit.edit.__rankmyseo ) {
	const original = inlineEdit.edit.bind( inlineEdit );
	const wrapped: EditFn = ( id ) => {
		original( id );
		fillQuickEdit( id );
	};
	wrapped.__rankmyseo = true;
	inlineEdit.edit = wrapped;
}
