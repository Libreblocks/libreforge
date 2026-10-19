/**
 * Query Builder editor: sections and the live preview shown in the sidebar.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, SelectControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';

import { SettingRow } from '@libreblocks/ui';

import { slugify } from './api';
import { CheckboxList } from './components';

const HIDDEN_TYPES = [ 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ];

const ORDERBY = {
	posts: [
		{ value: 'date', label: __( 'Publication date', 'libreforge' ) },
		{ value: 'modified', label: __( 'Last update', 'libreforge' ) },
		{ value: 'title', label: __( 'Title', 'libreforge' ) },
		{ value: 'menu_order', label: __( 'Manual order', 'libreforge' ) },
		{ value: 'rand', label: __( 'Random', 'libreforge' ) },
		{ value: 'comment_count', label: __( 'Number of comments', 'libreforge' ) },
		{ value: 'meta_value', label: __( 'A custom field (text)', 'libreforge' ) },
		{ value: 'meta_value_num', label: __( 'A custom field (number)', 'libreforge' ) },
		{ value: 'post__in', label: __( 'Order of the IDs typed below', 'libreforge' ) },
	],
	terms: [
		{ value: 'name', label: __( 'Name', 'libreforge' ) },
		{ value: 'count', label: __( 'Number of items', 'libreforge' ) },
		{ value: 'term_order', label: __( 'Manual order', 'libreforge' ) },
		{ value: 'meta_value', label: __( 'A custom field (text)', 'libreforge' ) },
		{ value: 'meta_value_num', label: __( 'A custom field (number)', 'libreforge' ) },
	],
	users: [
		{ value: 'display_name', label: __( 'Name', 'libreforge' ) },
		{ value: 'registered', label: __( 'Registration date', 'libreforge' ) },
		{ value: 'post_count', label: __( 'Number of posts', 'libreforge' ) },
		{ value: 'meta_value', label: __( 'A custom field (text)', 'libreforge' ) },
		{ value: 'meta_value_num', label: __( 'A custom field (number)', 'libreforge' ) },
	],
};

const COMPARE = [
	{ value: '=', label: __( 'is equal to', 'libreforge' ) },
	{ value: '!=', label: __( 'is different from', 'libreforge' ) },
	{ value: '>', label: __( 'is greater than', 'libreforge' ) },
	{ value: '>=', label: __( 'is greater or equal to', 'libreforge' ) },
	{ value: '<', label: __( 'is less than', 'libreforge' ) },
	{ value: '<=', label: __( 'is less or equal to', 'libreforge' ) },
	{ value: 'LIKE', label: __( 'contains', 'libreforge' ) },
	{ value: 'NOT LIKE', label: __( 'does not contain', 'libreforge' ) },
	{ value: 'IN', label: __( 'is one of (comma separated)', 'libreforge' ) },
	{ value: 'NOT IN', label: __( 'is none of (comma separated)', 'libreforge' ) },
	{ value: 'BETWEEN', label: __( 'is between (two values, comma)', 'libreforge' ) },
	{ value: 'EXISTS', label: __( 'is filled', 'libreforge' ) },
	{ value: 'NOT EXISTS', label: __( 'is empty', 'libreforge' ) },
];

const VALUE_TYPES = [
	{ value: 'CHAR', label: __( 'Text', 'libreforge' ) },
	{ value: 'NUMERIC', label: __( 'Whole number', 'libreforge' ) },
	{ value: 'DECIMAL', label: __( 'Decimal number', 'libreforge' ) },
	{ value: 'DATE', label: __( 'Date', 'libreforge' ) },
];

const TOKEN_HELP = __( 'Tip: values can use tokens, like {{user.id}} (logged-in user) or {{url.param|city}} (from the address).', 'libreforge' );

function Text( { label, help, value, onChange, placeholder, error, type = 'text' } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<TextControl label={ label } hideLabelFromVision type={ type } value={ value ?? '' } placeholder={ placeholder } onChange={ onChange } __nextHasNoMarginBottom />
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

function Choose( { label, help, value, options, onChange, error } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<SelectControl label={ label } hideLabelFromVision value={ value } options={ options } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

/** Rows of "taxonomy / is one of / terms". */
function TaxRules( { rules = [], relation, taxonomies, onChange, onRelation } ) {
	const update = ( index, patch ) => onChange( rules.map( ( rule, i ) => ( i === index ? { ...rule, ...patch } : rule ) ) );

	return (
		<div className="lf-rules">
			{ rules.length > 1 && (
				<SelectControl
					label={ __( 'Items must match', 'libreforge' ) }
					value={ relation }
					options={ [
						{ value: 'AND', label: __( 'all rules', 'libreforge' ) },
						{ value: 'OR', label: __( 'any rule', 'libreforge' ) },
					] }
					onChange={ onRelation }
					__nextHasNoMarginBottom
				/>
			) }
			{ rules.map( ( rule, index ) => (
				<div className="lf-rules__row" key={ index }>
					<SelectControl label={ __( 'Taxonomy', 'libreforge' ) } hideLabelFromVision value={ rule.taxonomy } options={ taxonomies } onChange={ ( taxonomy ) => update( index, { taxonomy } ) } __nextHasNoMarginBottom />
					<SelectControl
						label={ __( 'Condition', 'libreforge' ) }
						hideLabelFromVision
						value={ rule.operator }
						options={ [
							{ value: 'IN', label: __( 'is one of', 'libreforge' ) },
							{ value: 'NOT IN', label: __( 'is none of', 'libreforge' ) },
							{ value: 'AND', label: __( 'has all of', 'libreforge' ) },
							{ value: 'EXISTS', label: __( 'has any term', 'libreforge' ) },
							{ value: 'NOT EXISTS', label: __( 'has no term', 'libreforge' ) },
						] }
						onChange={ ( operator ) => update( index, { operator } ) }
						__nextHasNoMarginBottom
					/>
					{ ! [ 'EXISTS', 'NOT EXISTS' ].includes( rule.operator ) && (
						<>
							<SelectControl
								label={ __( 'Terms by', 'libreforge' ) }
								hideLabelFromVision
								value={ rule.field }
								options={ [
									{ value: 'slug', label: __( 'slug', 'libreforge' ) },
									{ value: 'term_id', label: __( 'ID', 'libreforge' ) },
									{ value: 'name', label: __( 'name', 'libreforge' ) },
								] }
								onChange={ ( field ) => update( index, { field } ) }
								__nextHasNoMarginBottom
							/>
							<TextControl label={ __( 'Terms', 'libreforge' ) } hideLabelFromVision value={ rule.terms } placeholder="sao-paulo, rio" onChange={ ( terms ) => update( index, { terms } ) } __nextHasNoMarginBottom />
						</>
					) }
					<Button icon="trash" isDestructive label={ __( 'Remove rule', 'libreforge' ) } onClick={ () => onChange( rules.filter( ( _, i ) => i !== index ) ) } />
				</div>
			) ) }
			<Button variant="secondary" icon="plus-alt2" onClick={ () => onChange( [ ...rules, { taxonomy: taxonomies[ 0 ]?.value || 'category', field: 'slug', terms: '', operator: 'IN' } ] ) }>
				{ __( 'Add taxonomy rule', 'libreforge' ) }
			</Button>
		</div>
	);
}

/** Rows of "field / comparison / value". */
function MetaRules( { rules = [], relation, onChange, onRelation } ) {
	const update = ( index, patch ) => onChange( rules.map( ( rule, i ) => ( i === index ? { ...rule, ...patch } : rule ) ) );

	return (
		<div className="lf-rules">
			{ rules.length > 1 && (
				<SelectControl
					label={ __( 'Items must match', 'libreforge' ) }
					value={ relation }
					options={ [
						{ value: 'AND', label: __( 'all rules', 'libreforge' ) },
						{ value: 'OR', label: __( 'any rule', 'libreforge' ) },
					] }
					onChange={ onRelation }
					__nextHasNoMarginBottom
				/>
			) }
			{ rules.map( ( rule, index ) => (
				<div className="lf-rules__row" key={ index }>
					<TextControl label={ __( 'Field name', 'libreforge' ) } hideLabelFromVision value={ rule.key } placeholder={ __( 'field name, e.g. price', 'libreforge' ) } onChange={ ( key ) => update( index, { key: slugify( key ) } ) } className="lf-mono" __nextHasNoMarginBottom />
					<SelectControl label={ __( 'Condition', 'libreforge' ) } hideLabelFromVision value={ rule.compare } options={ COMPARE } onChange={ ( compare ) => update( index, { compare } ) } __nextHasNoMarginBottom />
					{ ! [ 'EXISTS', 'NOT EXISTS' ].includes( rule.compare ) && (
						<>
							<TextControl label={ __( 'Value', 'libreforge' ) } hideLabelFromVision value={ rule.value } placeholder="500000" onChange={ ( value ) => update( index, { value } ) } __nextHasNoMarginBottom />
							<SelectControl label={ __( 'Compare as', 'libreforge' ) } hideLabelFromVision value={ rule.type } options={ VALUE_TYPES } onChange={ ( type ) => update( index, { type } ) } __nextHasNoMarginBottom />
						</>
					) }
					<Button icon="trash" isDestructive label={ __( 'Remove rule', 'libreforge' ) } onClick={ () => onChange( rules.filter( ( _, i ) => i !== index ) ) } />
				</div>
			) ) }
			<Button variant="secondary" icon="plus-alt2" onClick={ () => onChange( [ ...rules, { key: '', compare: '=', value: '', type: 'CHAR' } ] ) }>
				{ __( 'Add field rule', 'libreforge' ) }
			</Button>
		</div>
	);
}

/**
 * Query types and their sort options, extended by other plugins:
 *
 *     wp.hooks.addFilter( 'libreforge.queryTypes', 'my-plugin', ( types ) => [ ...types, { value, label, orderby: [ { value, label } ], General } ] );
 *
 * "General" is a component ( { config, set, environment } ) shown under "What to list".
 */
const queryTypes = () =>
	applyFilters( 'libreforge.queryTypes', [
		{ value: 'posts', label: __( 'Content (posts, pages, post types)', 'libreforge' ), orderby: ORDERBY.posts },
		{ value: 'terms', label: __( 'Terms of a taxonomy (e.g. cities)', 'libreforge' ), orderby: ORDERBY.terms },
		{ value: 'users', label: __( 'Users', 'libreforge' ), orderby: ORDERBY.users },
	] );

export function querySections( { config, set, errors, environment, slug, setSlug, isNew } ) {
	const type = config.type || 'posts';
	const types = queryTypes();
	const current = types.find( ( item ) => item.value === type ) || types[ 0 ];
	const sorts = current.orderby || ORDERBY.posts;
	const General = current.General;
	const builtIn = [ 'posts', 'terms', 'users' ].includes( type );
	const postTypes = environment.post_types.filter( ( item ) => ! HIDDEN_TYPES.includes( item.value ) );
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
			keys: [ 'title', 'slug', 'order_meta_key' ],
			content: (
				<>
					<Text label={ __( 'Query name', 'libreforge' ) } help={ __( 'Ex.: Latest properties, Featured team members.', 'libreforge' ) } value={ config.title } placeholder={ __( 'Latest properties', 'libreforge' ) } error={ errors.title } onChange={ syncTitle } />
					<Choose
						label={ __( 'What to list', 'libreforge' ) }
						value={ type }
						options={ types.map( ( item ) => ( { value: item.value, label: item.label } ) ) }
						onChange={ ( value ) => set( { type: value, orderby: ( types.find( ( item ) => item.value === value )?.orderby || ORDERBY.posts )[ 0 ].value } ) }
					/>
					{ General && <General config={ config } set={ set } environment={ environment } errors={ errors } /> }
					{ type === 'posts' && (
						<SettingRow label={ __( 'Post Types', 'libreforge' ) } help={ __( 'Which content to show.', 'libreforge' ) }>
							<CheckboxList options={ postTypes } value={ config.post_types || [] } onChange={ ( post_types ) => set( { post_types } ) } />
						</SettingRow>
					) }
					{ type === 'terms' && (
						<SettingRow label={ __( 'Taxonomies', 'libreforge' ) }>
							<CheckboxList options={ environment.taxonomies } value={ config.taxonomies || [] } onChange={ ( taxonomies ) => set( { taxonomies } ) } />
						</SettingRow>
					) }
					{ type === 'users' && (
						<SettingRow label={ __( 'Roles', 'libreforge' ) } help={ __( 'Leave empty for every user.', 'libreforge' ) }>
							<CheckboxList options={ environment.roles } value={ config.roles || [] } onChange={ ( roles ) => set( { roles } ) } />
						</SettingRow>
					) }
					<Text label={ __( 'How many per page', 'libreforge' ) } help={ __( '-1 shows all of them at once.', 'libreforge' ) } type="number" value={ config.per_page ?? 10 } onChange={ ( value ) => set( { per_page: value === '' ? 10 : Number( value ) } ) } />
					<Choose label={ __( 'Sort by', 'libreforge' ) } value={ config.orderby } options={ sorts } onChange={ ( orderby ) => set( { orderby } ) } />
					{ [ 'meta_value', 'meta_value_num' ].includes( config.orderby ) && (
						<Text label={ __( 'Field used to sort', 'libreforge' ) } help={ __( 'Name of the custom field, e.g. price.', 'libreforge' ) } value={ config.order_meta_key } error={ errors.order_meta_key } onChange={ ( order_meta_key ) => set( { order_meta_key: slugify( order_meta_key ) } ) } />
					) }
					<Choose
						label={ __( 'Order', 'libreforge' ) }
						value={ config.order || 'DESC' }
						options={ [
							{ value: 'DESC', label: __( 'Descending (newest, Z→A, highest first)', 'libreforge' ) },
							{ value: 'ASC', label: __( 'Ascending (oldest, A→Z, lowest first)', 'libreforge' ) },
						] }
						onChange={ ( order ) => set( { order } ) }
					/>
				</>
			),
		},
		{
			id: 'filters',
			title: __( 'Filters', 'libreforge' ),
			icon: 'filter',
			open: true,
			count: ( config.tax_rules || [] ).length + ( config.meta_rules || [] ).length,
			keys: [],
			content: (
				<>
					<p className="lf-intro">{ __( 'Show only the items that match. Leave empty to show everything.', 'libreforge' ) }</p>
					{ type === 'posts' && (
						<SettingRow label={ __( 'By taxonomy', 'libreforge' ) } help={ __( 'Ex.: only properties in the city "sao-paulo".', 'libreforge' ) } stacked>
							<TaxRules rules={ config.tax_rules } relation={ config.tax_relation } taxonomies={ environment.taxonomies } onChange={ ( tax_rules ) => set( { tax_rules } ) } onRelation={ ( tax_relation ) => set( { tax_relation } ) } />
						</SettingRow>
					) }
					<SettingRow label={ __( 'By custom field', 'libreforge' ) } help={ __( 'Ex.: price less than 500000.', 'libreforge' ) } stacked>
						<MetaRules rules={ config.meta_rules } relation={ config.meta_relation } onChange={ ( meta_rules ) => set( { meta_rules } ) } onRelation={ ( meta_relation ) => set( { meta_relation } ) } />
					</SettingRow>
					{ builtIn && environment.relations.length > 0 && (
						<>
							<Choose
								label={ __( 'Related to the current item', 'libreforge' ) }
								help={ __( 'Ex.: on a broker page, list only their properties.', 'libreforge' ) }
								value={ config.relation || '' }
								options={ [ { value: '', label: __( '— Any item —', 'libreforge' ) }, ...environment.relations ] }
								onChange={ ( relation ) => set( { relation } ) }
							/>
							{ config.relation && (
								<Choose
									label={ __( 'Which side', 'libreforge' ) }
									value={ config.relation_side || 'children' }
									options={ [
										{ value: 'children', label: __( 'Its children (e.g. the properties of this broker)', 'libreforge' ) },
										{ value: 'parents', label: __( 'Its parents (e.g. the broker of this property)', 'libreforge' ) },
									] }
									onChange={ ( relation_side ) => set( { relation_side } ) }
								/>
							) }
						</>
					) }
					<Text label={ __( 'Search text', 'libreforge' ) } help={ __( 'Only items containing this text.', 'libreforge' ) } value={ config.search } onChange={ ( search ) => set( { search } ) } />
					<p className="lf-intro lf-intro--tip">{ TOKEN_HELP }</p>
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
					<p className="lf-intro">{ __( 'The defaults work for most sites. Change only if you need to.', 'libreforge' ) }</p>
					<Text label={ __( 'Only these IDs', 'libreforge' ) } help={ __( 'Comma separated. Empty = any.', 'libreforge' ) } value={ config.include } placeholder="12, 34" onChange={ ( include ) => set( { include } ) } />
					<Text label={ __( 'Except these IDs', 'libreforge' ) } help={ __( 'Comma separated.', 'libreforge' ) } value={ config.exclude } onChange={ ( exclude ) => set( { exclude } ) } />
					<Text label={ __( 'Skip the first', 'libreforge' ) } help={ __( 'Number of items to skip (offset).', 'libreforge' ) } type="number" value={ config.offset ?? 0 } onChange={ ( value ) => set( { offset: Number( value ) || 0 } ) } />
					{ type === 'posts' && (
						<>
							<SettingRow label={ __( 'Status', 'libreforge' ) }>
								<CheckboxList
									options={ [
										{ value: 'publish', label: __( 'Published', 'libreforge' ) },
										{ value: 'draft', label: __( 'Draft', 'libreforge' ) },
										{ value: 'future', label: __( 'Scheduled', 'libreforge' ) },
										{ value: 'private', label: __( 'Private', 'libreforge' ) },
									] }
									value={ config.statuses || [ 'publish' ] }
									onChange={ ( statuses ) => set( { statuses } ) }
								/>
							</SettingRow>
							<Text label={ __( 'Author', 'libreforge' ) } help={ __( 'User ID or token, e.g. {{user.id}} for "my items".', 'libreforge' ) } value={ config.author } onChange={ ( author ) => set( { author } ) } />
							<Toggle label={ __( 'Hide the item being viewed', 'libreforge' ) } help={ __( 'On an item page, do not list that same item ("related items").', 'libreforge' ) } checked={ config.exclude_current ?? true } onChange={ ( exclude_current ) => set( { exclude_current } ) } />
							<Toggle label={ __( 'Ignore sticky posts', 'libreforge' ) } checked={ config.ignore_sticky ?? true } onChange={ ( ignore_sticky ) => set( { ignore_sticky } ) } />
						</>
					) }
					{ type === 'terms' && (
						<>
							<Toggle label={ __( 'Hide empty terms', 'libreforge' ) } help={ __( 'Terms without any item.', 'libreforge' ) } checked={ config.hide_empty } onChange={ ( hide_empty ) => set( { hide_empty } ) } />
							<Text label={ __( 'Children of term', 'libreforge' ) } help={ __( 'Parent term ID. 0 = only top level.', 'libreforge' ) } value={ config.parent } onChange={ ( parent ) => set( { parent } ) } />
						</>
					) }
					<Text label={ __( 'Identifier (slug)', 'libreforge' ) } value={ slug } onChange={ ( value ) => setSlug( slugify( value ).slice( 0, 64 ) ) } error={ errors.slug } />
				</>
			),
		},
	];
}

/** Sidebar card that runs the unsaved query and shows the first results. */
export function QueryPreview( { config } ) {
	const [ result, setResult ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const key = JSON.stringify( config );

	useEffect( () => {
		setLoading( true );
		const timer = setTimeout( () => {
			apiFetch( { path: '/libreblocks/v1/forge/queries/preview', method: 'POST', data: { config } } )
				.then( ( data ) => {
					setResult( data );
					setError( null );
				} )
				.catch( ( err ) => setError( err.message ) )
				.finally( () => setLoading( false ) );
		}, 500 );

		return () => clearTimeout( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );

	return (
		<div className="lb-card lf-aside-card lf-preview">
			<h3>
				{ __( 'Live preview', 'libreforge' ) }
				{ loading && <Spinner /> }
			</h3>
			{ error && <p className="lf-row__error">{ error }</p> }
			{ result && (
				<>
					<p className="lf-preview__total">
						{ /* translators: %d: number of results */ }
						{ sprintf( _n( '%d result', '%d results', result.total, 'libreforge' ), result.total ) }
					</p>
					{ result.items.length === 0 ? (
						<p className="lf-muted">{ __( 'Nothing matches yet. Try removing a filter.', 'libreforge' ) }</p>
					) : (
						<ol className="lf-preview__list">
							{ result.items.map( ( item ) => (
								<li key={ item.id }>
									<span>{ item.label }</span>
									<small>{ item.meta }</small>
								</li>
							) ) }
						</ol>
					) }
				</>
			) }
		</div>
	);
}
