import { Button, CheckboxControl, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Icon } from '@libreblocks/ui';

/** Field wrapper that shows the validation message returned by the REST API. */
export function Row( { error, children, help } ) {
	return (
		<div className={ `lf-row${ error ? ' has-error' : '' }` }>
			{ children }
			{ help && <p className="lf-row__help">{ help }</p> }
			{ error && <p className="lf-row__error">{ error }</p> }
		</div>
	);
}

export function Grid( { children, columns } ) {
	return <div className={ `lf-grid${ columns ? ` lf-grid--${ columns }` : '' }` }>{ children }</div>;
}

/** Multiple choice shown as selectable chips, e.g. the post types of a taxonomy. */
export function CheckboxList( { label, options, value = [], onChange, help } ) {
	return (
		<fieldset className="lf-choices">
			{ label && <legend>{ label }</legend> }
			<div className="lf-choices__list">
				{ options.map( ( option ) => (
					<CheckboxControl
						key={ option.value }
						className={ `lf-choice${ value.includes( option.value ) ? ' is-checked' : '' }` }
						label={ option.label }
						checked={ value.includes( option.value ) }
						onChange={ ( checked ) =>
							onChange( checked ? [ ...value, option.value ] : value.filter( ( item ) => item !== option.value ) )
						}
						__nextHasNoMarginBottom
					/>
				) ) }
			</div>
			{ help && <p className="lf-row__help">{ help }</p> }
		</fieldset>
	);
}

/** One switch row with a title and a short explanation. */
export function Switch( { label, help, checked, onChange } ) {
	return (
		<label className="lf-switch">
			<span className="lf-switch__text">
				<strong>{ label }</strong>
				{ help && <span>{ help }</span> }
			</span>
			<input type="checkbox" className="lf-switch__input" checked={ !! checked } onChange={ ( event ) => onChange( event.target.checked ) } />
			<span className="lf-switch__track" aria-hidden="true" />
		</label>
	);
}

export function SwitchList( { children } ) {
	return <div className="lf-switches">{ children }</div>;
}

/** Simplified preview of the admin menu entry a post type will create. */
export function MenuPreview( { icon, plural, singular, taxonomies = [] } ) {
	return (
		<div className="lf-menu-preview" aria-hidden="true">
			<div className="lf-menu-preview__item is-current">
				<Icon name={ icon || 'dashicons-admin-post' } />
				<span>{ plural || __( 'Items', 'libreforge' ) }</span>
			</div>
			<div className="lf-menu-preview__sub">
				<span className="is-current">{ plural || __( 'Items', 'libreforge' ) }</span>
				<span>{ __( 'Add new', 'libreforge' ) }{ singular ? ` · ${ singular }` : '' }</span>
				{ taxonomies.map( ( taxonomy ) => (
					<span key={ taxonomy }>{ taxonomy }</span>
				) ) }
			</div>
		</div>
	);
}

/** Editable list of value/label pairs, with a bulk "paste" mode. */
export function OptionsEditor( { value = [], onChange } ) {
	const [ bulk, setBulk ] = useState( false );
	const [ text, setText ] = useState( '' );
	const rows = Array.isArray( value ) ? value : [];

	const update = ( index, patch ) => onChange( rows.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) );

	if ( bulk ) {
		return (
			<div className="lf-options">
				<textarea
					className="lf-options__bulk"
					rows={ 10 }
					value={ text }
					onChange={ ( event ) => setText( event.target.value ) }
					placeholder={ 'sp : São Paulo\nrj : Rio de Janeiro' }
				/>
				<div className="lf-options__footer">
					<Button
						variant="primary"
						onClick={ () => {
							const added = text
								.split( '\n' )
								.map( ( line ) => line.trim() )
								.filter( Boolean )
								.map( ( line ) => {
									const [ optionValue, ...label ] = line.split( ':' );
									return { value: optionValue.trim(), label: ( label.join( ':' ) || optionValue ).trim() };
								} );
							onChange( [ ...rows, ...added ] );
							setText( '' );
							setBulk( false );
						} }
					>
						{ __( 'Add to the list', 'libreforge' ) }
					</Button>
					<Button variant="tertiary" onClick={ () => setBulk( false ) }>
						{ __( 'Cancel', 'libreforge' ) }
					</Button>
				</div>
			</div>
		);
	}

	return (
		<div className="lf-options">
			{ rows.length > 0 && (
				<div className="lf-options__head">
					<span>{ __( 'Value (saved)', 'libreforge' ) }</span>
					<span>{ __( 'Label (shown)', 'libreforge' ) }</span>
				</div>
			) }
			{ rows.map( ( row, index ) => (
				<div className="lf-options__row" key={ index }>
					<TextControl value={ row.value } onChange={ ( optionValue ) => update( index, { value: optionValue } ) } __nextHasNoMarginBottom />
					<TextControl value={ row.label } onChange={ ( label ) => update( index, { label } ) } __nextHasNoMarginBottom />
					<Button icon="trash" isDestructive label={ __( 'Remove', 'libreforge' ) } onClick={ () => onChange( rows.filter( ( _, i ) => i !== index ) ) } />
				</div>
			) ) }
			<div className="lf-options__footer">
				<Button variant="secondary" icon="plus-alt2" onClick={ () => onChange( [ ...rows, { value: '', label: '' } ] ) }>
					{ __( 'Add option', 'libreforge' ) }
				</Button>
				<Button variant="tertiary" onClick={ () => setBulk( true ) }>
					{ __( 'Paste many', 'libreforge' ) }
				</Button>
			</div>
		</div>
	);
}
