/**
 * Relation editor: which two kinds of content are connected and how.
 */
import { SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { SettingRow } from '@libreblocks/ui';

import { slugify } from './api';

function Choose( { label, help, value, options, onChange, error } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<SelectControl label={ label } hideLabelFromVision value={ value } options={ options } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

function Text( { label, help, value, onChange, placeholder, error } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<TextControl label={ label } hideLabelFromVision value={ value ?? '' } placeholder={ placeholder } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

function Toggle( { label, help, checked, onChange } ) {
	return (
		<SettingRow label={ label } help={ help }>
			<ToggleControl label={ <span className="screen-reader-text">{ label }</span> } checked={ !! checked } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

/** Plain sentence that explains the chosen type with the chosen content names. */
export function relationSentence( config, objects ) {
	const name = ( value ) => objects.find( ( item ) => item.value === value )?.label || '…';
	const parent = name( config.parent_object );
	const child = name( config.child_object );

	switch ( config.type ) {
		case 'one_to_one':
			/* translators: 1: parent content, 2: child content */
			return sprintf( __( 'Each item of %1$s is linked to one item of %2$s, and vice versa.', 'libreforge' ), parent, child );
		case 'many_to_many':
			/* translators: 1: parent content, 2: child content */
			return sprintf( __( 'Items of %1$s can have many items of %2$s, and each of them can belong to many %1$s.', 'libreforge' ), parent, child );
		default:
			/* translators: 1: parent content, 2: child content */
			return sprintf( __( 'Each item of %1$s can have many items of %2$s; each item of %2$s belongs to one %1$s.', 'libreforge' ), parent, child );
	}
}

export function relationSections( { config, set, errors, environment, slug, setSlug, isNew } ) {
	const objects = [ { value: '', label: __( '— Choose —', 'libreforge' ) }, ...environment.objects ];
	const syncTitle = ( title ) => {
		if ( isNew && ( ! slug || slug === slugify( config.title || '' ).slice( 0, 64 ) ) ) {
			setSlug( slugify( title ).slice( 0, 64 ) );
		}
		set( { title } );
	};

	return [
		{
			id: 'general',
			title: __( 'General settings', 'libreforge' ),
			icon: 'admin-generic',
			open: true,
			keys: [ 'title', 'slug', 'parent_object', 'child_object' ],
			content: (
				<>
					<Text label={ __( 'Relation name', 'libreforge' ) } help={ __( 'Ex.: Broker → Properties, Course → Lessons.', 'libreforge' ) } value={ config.title } placeholder={ __( 'Broker → Properties', 'libreforge' ) } error={ errors.title } onChange={ syncTitle } />
					<Choose label={ __( 'Parent content', 'libreforge' ) } help={ __( 'The "owner" side. Ex.: Brokers.', 'libreforge' ) } value={ config.parent_object } options={ objects } error={ errors.parent_object } onChange={ ( parent_object ) => set( { parent_object } ) } />
					<Choose label={ __( 'Child content', 'libreforge' ) } help={ __( 'The side that belongs to the parent. Ex.: Properties.', 'libreforge' ) } value={ config.child_object } options={ objects } error={ errors.child_object } onChange={ ( child_object ) => set( { child_object } ) } />
					<Choose
						label={ __( 'How many on each side', 'libreforge' ) }
						value={ config.type || 'one_to_many' }
						options={ [
							{ value: 'one_to_many', label: __( 'One to many (a broker has many properties)', 'libreforge' ) },
							{ value: 'many_to_many', label: __( 'Many to many (students and courses)', 'libreforge' ) },
							{ value: 'one_to_one', label: __( 'One to one (a person and a profile)', 'libreforge' ) },
						] }
						onChange={ ( type ) => set( { type } ) }
					/>
					<div className="lf-callout">
						<strong>{ __( 'In plain words', 'libreforge' ) }</strong>
						<p>{ relationSentence( config, environment.objects ) }</p>
					</div>
				</>
			),
		},
		{
			id: 'editing',
			title: __( 'Where to connect items', 'libreforge' ),
			icon: 'admin-links',
			open: true,
			keys: [],
			content: (
				<>
					<p className="lf-intro">{ __( 'A box appears on the edit screen to pick the related items.', 'libreforge' ) }</p>
					<Toggle label={ __( 'Box on the parent', 'libreforge' ) } help={ __( 'Ex.: on each broker, pick their properties.', 'libreforge' ) } checked={ config.show_in_parent ?? true } onChange={ ( show_in_parent ) => set( { show_in_parent } ) } />
					<Text label={ __( 'Title of the box on the parent', 'libreforge' ) } value={ config.child_label } placeholder={ __( 'Properties', 'libreforge' ) } onChange={ ( child_label ) => set( { child_label } ) } />
					<Toggle label={ __( 'Box on the child', 'libreforge' ) } help={ __( 'Ex.: on each property, pick its broker.', 'libreforge' ) } checked={ config.show_in_child ?? true } onChange={ ( show_in_child ) => set( { show_in_child } ) } />
					<Text label={ __( 'Title of the box on the child', 'libreforge' ) } value={ config.parent_label } placeholder={ __( 'Broker', 'libreforge' ) } onChange={ ( parent_label ) => set( { parent_label } ) } />
				</>
			),
		},
		{
			id: 'advanced',
			title: __( 'Advanced settings', 'libreforge' ),
			icon: 'admin-tools',
			keys: [],
			content: (
				<>
					<Text label={ __( 'Identifier (slug)', 'libreforge' ) } value={ slug } onChange={ ( value ) => setSlug( slugify( value ).slice( 0, 64 ) ) } error={ errors.slug } />
					<SettingRow label={ __( 'Tokens', 'libreforge' ) } help={ __( 'IDs of the related items of the current item, for queries and texts.', 'libreforge' ) }>
						<code className="lf-token">{ `{{relation.children|${ slug || 'slug' }}}` }</code>
						<br />
						<code className="lf-token">{ `{{relation.parents|${ slug || 'slug' }}}` }</code>
					</SettingRow>
				</>
			),
		},
	];
}
