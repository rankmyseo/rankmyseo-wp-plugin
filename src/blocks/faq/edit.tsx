import { Button, SelectControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockEditProps, FaqAttributes, FaqPair } from '../types';

function move( pairs: FaqPair[], index: number, direction: -1 | 1 ): FaqPair[] {
	const next = index + direction;
	if ( next < 0 || next >= pairs.length ) {
		return pairs;
	}
	const copy = [ ...pairs ];
	const current = copy[ index ];
	copy[ index ] = copy[ next ];
	copy[ next ] = current;
	return copy;
}

export function FaqEdit( {
	attributes,
	setAttributes,
}: BlockEditProps< FaqAttributes > ): JSX.Element {
	const questions = attributes.questions ?? [];

	const update = ( index: number, patch: Partial< FaqPair > ): void => {
		setAttributes( {
			questions: questions.map( ( pair, pairIndex ) =>
				pairIndex === index ? { ...pair, ...patch } : pair
			),
		} );
	};

	return (
		<div className="rankmyseo-block rankmyseo-block-faq">
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Answer layout', 'rankmyseo' ) }
				value={
					attributes.layout === 'headings' ? 'headings' : 'details'
				}
				options={ [
					{
						label: __( 'Expandable questions', 'rankmyseo' ),
						value: 'details',
					},
					{
						label: __( 'Headings and answers', 'rankmyseo' ),
						value: 'headings',
					},
				] }
				onChange={ ( value ) =>
					setAttributes( {
						layout: value === 'headings' ? 'headings' : 'details',
					} )
				}
			/>
			{ questions.map( ( pair, index ) => (
				<div className="rankmyseo-faq-editor" key={ index }>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Question', 'rankmyseo' ) }
						value={ pair.question }
						onChange={ ( value ) =>
							update( index, { question: value } )
						}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Answer', 'rankmyseo' ) }
						value={ pair.answer }
						onChange={ ( value ) =>
							update( index, { answer: value } )
						}
					/>
					<div className="rankmyseo-faq-editor__actions">
						<Button
							variant="secondary"
							disabled={ index === 0 }
							onClick={ () =>
								setAttributes( {
									questions: move( questions, index, -1 ),
								} )
							}
						>
							{ __( 'Move up', 'rankmyseo' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ index === questions.length - 1 }
							onClick={ () =>
								setAttributes( {
									questions: move( questions, index, 1 ),
								} )
							}
						>
							{ __( 'Move down', 'rankmyseo' ) }
						</Button>
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () =>
								setAttributes( {
									questions: questions.filter(
										( _pair, pairIndex ) =>
											pairIndex !== index
									),
								} )
							}
						>
							{ __( 'Remove', 'rankmyseo' ) }
						</Button>
					</div>
				</div>
			) ) }
			<Button
				variant="primary"
				onClick={ () =>
					setAttributes( {
						questions: [
							...questions,
							{ question: '', answer: '' },
						],
					} )
				}
			>
				{ __( 'Add question', 'rankmyseo' ) }
			</Button>
		</div>
	);
}
