/**
 * Item templates screen: list, create (with the block editor or Elementor) and delete.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Badge, CardGrid, EmptyState, ItemCard, PageHeader, Panel, SettingRow } from '@libreblocks/ui';

const PATH = '/libreblocks/v1/forge/templates';

function NewTemplate( { sources, elementor, onCancel } ) {
	const [ title, setTitle ] = useState( '' );
	const [ source, setSource ] = useState( sources[ 0 ]?.value || 'post' );
	const [ builder, setBuilder ] = useState( 'blocks' );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	const create = () => {
		setSaving( true );
		apiFetch( { path: PATH, method: 'POST', data: { title, source, builder } } )
			.then( ( template ) => {
				window.location.href = template.edit_url;
			} )
			.catch( ( err ) => {
				setError( err.message );
				setSaving( false );
			} );
	};

	return (
		<Panel title={ __( 'New item template', 'libreforge' ) } description={ __( 'A template is the design of one item (a card). Listings repeat it for every item.', 'libreforge' ) }>
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			<SettingRow label={ __( 'Template name', 'libreforge' ) } help={ __( 'Ex.: Property card', 'libreforge' ) }>
				<TextControl label={ __( 'Template name', 'libreforge' ) } hideLabelFromVision value={ title } placeholder={ __( 'Property card', 'libreforge' ) } onChange={ setTitle } __nextHasNoMarginBottom />
			</SettingRow>
			<SettingRow label={ __( 'It shows', 'libreforge' ) } help={ __( 'Used to show a real example while you design.', 'libreforge' ) }>
				<SelectControl label={ __( 'It shows', 'libreforge' ) } hideLabelFromVision value={ source } options={ sources } onChange={ setSource } __nextHasNoMarginBottom />
			</SettingRow>
			<SettingRow label={ __( 'Design with', 'libreforge' ) }>
				<div className="lf-builder-choice">
					<button type="button" className={ builder === 'blocks' ? 'is-selected' : '' } onClick={ () => setBuilder( 'blocks' ) }>
						<span className="dashicons dashicons-block-default" aria-hidden="true" />
						<strong>{ __( 'Block editor', 'libreforge' ) }</strong>
						<small>{ __( 'The WordPress editor (Gutenberg).', 'libreforge' ) }</small>
					</button>
					<button type="button" className={ builder === 'elementor' ? 'is-selected' : '' } onClick={ () => elementor && setBuilder( 'elementor' ) } aria-disabled={ ! elementor }>
						<span className="dashicons dashicons-layout" aria-hidden="true" />
						<strong>Elementor</strong>
						<small>{ elementor ? __( 'Drag-and-drop page builder.', 'libreforge' ) : __( 'Install Elementor to use it.', 'libreforge' ) }</small>
					</button>
				</div>
			</SettingRow>
			<div className="lf-form-actions">
				<Button variant="primary" onClick={ create } isBusy={ saving } disabled={ saving }>
					{ __( 'Create and open the editor', 'libreforge' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'libreforge' ) }
				</Button>
			</div>
		</Panel>
	);
}

export default function TemplatesScreen() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ creating, setCreating ] = useState( false );

	const load = () =>
		apiFetch( { path: PATH } )
			.then( setData )
			.catch( ( err ) => setError( err.message ) );

	useEffect( () => {
		load();
	}, [] );

	const remove = ( template ) => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Move this template to the trash? Listings using it will show nothing until you pick another one.', 'libreforge' ) ) ) {
			apiFetch( { path: `${ PATH }/${ template.id }`, method: 'DELETE' } )
				.then( load )
				.catch( ( err ) => setError( err.message ) );
		}
	};

	const sourceLabel = ( value ) => data?.sources.find( ( source ) => source.value === value )?.label || value;

	return (
		<>
			<PageHeader
				icon="layout"
				title={ __( 'Item templates', 'libreforge' ) }
				subtitle={ __( 'The design of each item in your listings.', 'libreforge' ) }
				actions={
					! creating && (
						<Button variant="primary" icon="plus-alt2" onClick={ () => setCreating( true ) }>
							{ __( 'New template', 'libreforge' ) }
						</Button>
					)
				}
			/>

			{ error && <Notice status="error" onRemove={ () => setError( null ) }>{ error }</Notice> }
			{ ! data && ! error && <Spinner /> }

			{ data && creating && <NewTemplate sources={ data.sources } elementor={ data.elementor } onCancel={ () => setCreating( false ) } /> }

			{ data && ! creating && data.templates.length === 0 && (
				<EmptyState
					icon="layout"
					title={ __( 'Create your first item template', 'libreforge' ) }
					text={ __( 'Design how one item looks (photo, title, price…). Then the Loop Grid block repeats it for every item of a query.', 'libreforge' ) }
					action={
						<Button variant="primary" icon="plus-alt2" onClick={ () => setCreating( true ) }>
							{ __( 'New template', 'libreforge' ) }
						</Button>
					}
				/>
			) }

			{ data && data.templates.length > 0 && (
				<CardGrid columns={ 3 }>
					{ data.templates.map( ( template ) => (
						<ItemCard
							key={ template.id }
							icon={ template.builder === 'elementor' ? 'layout' : 'block-default' }
							title={ template.title }
							code={ `#${ template.id }` }
							status={ <Badge tone="accent">{ template.builder === 'elementor' ? 'Elementor' : __( 'Block editor', 'libreforge' ) }</Badge> }
							description={ `${ sourceLabel( template.source ) } · ${ template.modified }` }
							onClick={ () => {
								window.location.href = template.edit_url;
							} }
							actions={
								<>
									<Button variant="secondary" size="small" href={ template.edit_url }>
										{ __( 'Edit design', 'libreforge' ) }
									</Button>
									<Button icon="trash" size="small" isDestructive label={ __( 'Delete', 'libreforge' ) } onClick={ () => remove( template ) } />
								</>
							}
						/>
					) ) }
				</CardGrid>
			) }
		</>
	);
}
