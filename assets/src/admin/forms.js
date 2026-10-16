/**
 * Editors of each definition kind.
 *
 * One long page of collapsible sections, each setting on its own row (name and
 * a plain-language explanation on the left, the control on the right). Only the
 * essentials are open; technical options live under "Advanced settings" with
 * defaults that already work.
 *
 * Each builder receives { config, set, errors, environment, slug, setSlug, isNew }
 * and returns [ { id, title, icon, open, count, keys, content } ]. "keys" lists the
 * error keys (or prefixes ending in ".") shown inside the section.
 */
import { SelectControl, TextareaControl, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { IconPicker, SettingRow } from '@libreblocks/ui';

import { slugify } from './api';
import { CheckboxList, OptionsEditor } from './components';
import FieldBuilder from './field-builder';

const SUPPORTS = [
	{ value: 'title', label: __( 'Title', 'libreforge' ) },
	{ value: 'editor', label: __( 'Content editor', 'libreforge' ) },
	{ value: 'thumbnail', label: __( 'Featured image', 'libreforge' ) },
	{ value: 'excerpt', label: __( 'Excerpt', 'libreforge' ) },
	{ value: 'author', label: __( 'Author', 'libreforge' ) },
	{ value: 'comments', label: __( 'Comments', 'libreforge' ) },
	{ value: 'revisions', label: __( 'Revisions', 'libreforge' ) },
	{ value: 'page-attributes', label: __( 'Order and parent', 'libreforge' ) },
	{ value: 'custom-fields', label: __( 'Custom fields panel', 'libreforge' ) },
];

const HIDDEN_TYPES = [ 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ];

/* -------------------------------------------------------------------------- row helpers */

function Text( { label, help, value, onChange, placeholder, error, type = 'text', mono = false, maxLength } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<TextControl
				label={ label }
				hideLabelFromVision
				type={ type }
				value={ value ?? '' }
				placeholder={ placeholder }
				maxLength={ maxLength }
				onChange={ onChange }
				className={ mono ? 'lf-mono' : undefined }
				__nextHasNoMarginBottom
			/>
		</SettingRow>
	);
}

function LongText( { label, help, value, onChange, placeholder } ) {
	return (
		<SettingRow label={ label } help={ help }>
			<TextareaControl label={ label } hideLabelFromVision value={ value ?? '' } placeholder={ placeholder } onChange={ onChange } rows={ 2 } __nextHasNoMarginBottom />
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

function Choose( { label, help, value, options, onChange } ) {
	return (
		<SettingRow label={ label } help={ help }>
			<SelectControl label={ label } hideLabelFromVision value={ value } options={ options } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

function Chips( { label, help, options, value, onChange, error } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<CheckboxList options={ options } value={ value || [] } onChange={ onChange } />
		</SettingRow>
	);
}

/**
 * Identifier (slug). It follows the name automatically while the item is new,
 * so beginners never have to think about it.
 */
function Identifier( { slug, setSlug, errors, maxLength, isNew, help } ) {
	return (
		<Text
			label={ __( 'Identifier (slug)', 'libreforge' ) }
			help={
				<>
					{ help || __( 'Filled in automatically from the name. Only lowercase letters, numbers and _.', 'libreforge' ) }
					{ ! isNew && <strong className="lf-warn"> { __( 'Changing it later disconnects the content saved with the old one.', 'libreforge' ) }</strong> }
				</>
			}
			value={ slug }
			mono
			maxLength={ maxLength }
			error={ errors.slug }
			onChange={ ( value ) => setSlug( slugify( value ).slice( 0, maxLength ) ) }
		/>
	);
}

/** Returns a setter for the name field that also keeps the slug in sync on new items. */
const followSlug = ( { slug, setSlug, isNew, maxLength, previous } ) => ( value ) => {
	if ( isNew && ( ! slug || slug === slugify( previous || '' ).slice( 0, maxLength ) ) ) {
		setSlug( slugify( value ).slice( 0, maxLength ) );
	}
};

function Names( { config, set, errors, slug, setSlug, isNew, maxLength, examples } ) {
	const sync = followSlug( { slug, setSlug, isNew, maxLength, previous: config.singular } );

	return (
		<>
			<Text
				label={ __( 'Name (plural)', 'libreforge' ) }
				help={ sprintf( /* translators: %s: example */ __( 'How it appears in the menu. Ex.: %s', 'libreforge' ), examples[ 1 ] ) }
				value={ config.plural }
				placeholder={ examples[ 1 ] }
				error={ errors.plural }
				onChange={ ( plural ) => set( { plural } ) }
			/>
			<Text
				label={ __( 'Name (singular)', 'libreforge' ) }
				help={ sprintf( /* translators: %s: example */ __( 'Used in buttons like "Add new". Ex.: %s', 'libreforge' ), examples[ 0 ] ) }
				value={ config.singular }
				placeholder={ examples[ 0 ] }
				error={ errors.singular }
				onChange={ ( singular ) => {
					sync( singular );
					set( { singular } );
				} }
			/>
		</>
	);
}

/** Optional overrides of the generated labels. Empty = automatic. */
function LabelRows( { config, set, keys } ) {
	const labels = config.labels || {};
	const names = {
		menu_name: [ __( 'Menu name', 'libreforge' ), __( 'Text of the item in the side menu.', 'libreforge' ) ],
		all_items: [ __( '"All items" link', 'libreforge' ), __( 'First link under the menu item.', 'libreforge' ) ],
		add_new_item: [ __( '"Add new" title', 'libreforge' ), __( 'Title of the screen that creates an item.', 'libreforge' ) ],
		edit_item: [ __( '"Edit" title', 'libreforge' ), __( 'Title of the screen that edits an item.', 'libreforge' ) ],
		search_items: [ __( 'Search button', 'libreforge' ), __( 'Text of the search button in the list.', 'libreforge' ) ],
		not_found: [ __( 'Nothing found message', 'libreforge' ), __( 'Shown when the list is empty.', 'libreforge' ) ],
		parent_item: [ __( 'Parent item', 'libreforge' ), __( 'Label of the parent selector.', 'libreforge' ) ],
		back_to_items: [ __( '"Back" link', 'libreforge' ), __( 'Shown after editing a term.', 'libreforge' ) ],
	};

	return keys.map( ( key ) => (
		<Text
			key={ key }
			label={ names[ key ][ 0 ] }
			help={ names[ key ][ 1 ] }
			value={ labels[ key ] }
			placeholder={ __( 'Automatic', 'libreforge' ) }
			onChange={ ( value ) => set( { labels: { ...labels, [ key ]: value } } ) }
		/>
	) );
}

/** The "Custom fields" section shared by every kind that has fields. */
function fieldsSection( { config, set, errors, environment, help, extra } ) {
	const fields = config.fields || [];

	return {
		id: 'fields',
		title: __( 'Custom fields', 'libreforge' ),
		icon: 'forms',
		open: true,
		count: fields.length,
		keys: [ 'fields.' ],
		content: (
			<>
				<p className="lf-intro">{ help }</p>
				{ extra }
				<FieldBuilder fields={ fields } onChange={ ( value ) => set( { fields: value } ) } environment={ environment } errors={ errors } />
			</>
		),
	};
}

/* -------------------------------------------------------------------------- post types */

export function postTypeSections( { config, set, errors, environment, slug, setSlug, isNew } ) {
	const taxonomies = environment.taxonomies.filter( ( taxonomy ) => ! [ 'nav_menu', 'wp_pattern_category', 'link_category' ].includes( taxonomy.value ) );

	return [
		{
			id: 'general',
			title: __( 'General settings', 'libreforge' ),
			icon: 'admin-generic',
			open: true,
			keys: [ 'slug', 'singular', 'plural' ],
			content: (
				<>
					<Names config={ config } set={ set } errors={ errors } slug={ slug } setSlug={ setSlug } isNew={ isNew } maxLength={ 20 } examples={ [ __( 'Property', 'libreforge' ), __( 'Properties', 'libreforge' ) ] } />
					<Identifier slug={ slug } setSlug={ setSlug } errors={ errors } maxLength={ 20 } isNew={ isNew } />
					<SettingRow label={ __( 'Menu icon', 'libreforge' ) } help={ __( 'The little picture next to the name in the menu.', 'libreforge' ) }>
						<IconPicker value={ config.menu_icon } onChange={ ( menu_icon ) => set( { menu_icon } ) } />
					</SettingRow>
					<Chips label={ __( 'What each item has', 'libreforge' ) } help={ __( 'Boxes shown when writing an item.', 'libreforge' ) } options={ SUPPORTS } value={ config.supports } onChange={ ( supports ) => set( { supports } ) } />
				</>
			),
		},
		fieldsSection( {
			config,
			set,
			errors,
			environment,
			help: __( 'Extra information for each item, like price, address or gallery. Each field needs a label (what people see) and a name (how it is saved).', 'libreforge' ),
			extra: <Text label={ __( 'Box title', 'libreforge' ) } help={ __( 'Title of the box that shows these fields on the edit screen.', 'libreforge' ) } value={ config.fields_title } placeholder={ __( 'Custom fields', 'libreforge' ) } onChange={ ( fields_title ) => set( { fields_title } ) } />,
		} ),
		{
			id: 'labels',
			title: __( 'Labels', 'libreforge' ),
			icon: 'translation',
			keys: [],
			content: (
				<>
					<p className="lf-intro">{ __( 'Created automatically from the names above. Fill only what you want to change.', 'libreforge' ) }</p>
					<LabelRows config={ config } set={ set } keys={ [ 'menu_name', 'all_items', 'add_new_item', 'edit_item', 'search_items', 'not_found' ] } />
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
					<Toggle label={ __( 'Public', 'libreforge' ) } help={ __( 'Each item has its own page on the site.', 'libreforge' ) } checked={ config.public } onChange={ ( value ) => set( { public: value } ) } />
					<Toggle label={ __( 'Archive page', 'libreforge' ) } help={ __( 'A page listing all items, like /properties/.', 'libreforge' ) } checked={ config.has_archive } onChange={ ( has_archive ) => set( { has_archive } ) } />
					<Toggle label={ __( 'Show in the admin menu', 'libreforge' ) } checked={ config.show_in_menu } onChange={ ( show_in_menu ) => set( { show_in_menu } ) } />
					<Toggle label={ __( 'Block editor', 'libreforge' ) } help={ __( 'Off = classic editor. Also controls the REST API.', 'libreforge' ) } checked={ config.show_in_rest } onChange={ ( show_in_rest ) => set( { show_in_rest } ) } />
					<Toggle label={ __( 'Hierarchical', 'libreforge' ) } help={ __( 'Items can have a parent, like pages.', 'libreforge' ) } checked={ config.hierarchical } onChange={ ( hierarchical ) => set( { hierarchical } ) } />
					<Chips label={ __( 'Taxonomies', 'libreforge' ) } help={ __( 'Ways to classify the items, like categories.', 'libreforge' ) } options={ taxonomies } value={ config.taxonomies } onChange={ ( value ) => set( { taxonomies: value } ) } />
					<Text label={ __( 'Address of the items', 'libreforge' ) } help={ sprintf( /* translators: %s: example URL */ __( 'Part of the URL before each item: %s', 'libreforge' ), `${ window.location.origin }/${ config.rewrite_slug || slug || 'slug' }/…` ) } value={ config.rewrite_slug } placeholder={ slug } onChange={ ( rewrite_slug ) => set( { rewrite_slug } ) } />
					<Text label={ __( 'Address of the archive', 'libreforge' ) } value={ config.archive_slug } placeholder={ slug } onChange={ ( archive_slug ) => set( { archive_slug } ) } />
					<Toggle label={ __( 'Exclude from search results', 'libreforge' ) } checked={ config.exclude_from_search } onChange={ ( exclude_from_search ) => set( { exclude_from_search } ) } />
					<Toggle label={ __( 'Available in navigation menus', 'libreforge' ) } checked={ config.show_in_nav_menus } onChange={ ( show_in_nav_menus ) => set( { show_in_nav_menus } ) } />
					<Toggle label={ __( 'Use the permalink base', 'libreforge' ) } help={ __( 'Adds the prefix from Settings > Permalinks (for example /blog/).', 'libreforge' ) } checked={ config.with_front } onChange={ ( with_front ) => set( { with_front } ) } />
					<Choose
						label={ __( 'Position of the fields box', 'libreforge' ) }
						value={ config.fields_context || 'normal' }
						options={ [
							{ value: 'normal', label: __( 'Below the content', 'libreforge' ) },
							{ value: 'side', label: __( 'Sidebar', 'libreforge' ) },
						] }
						onChange={ ( fields_context ) => set( { fields_context } ) }
					/>
					<LongText label={ __( 'Description', 'libreforge' ) } help={ __( 'Internal note. Some themes show it.', 'libreforge' ) } value={ config.description } onChange={ ( description ) => set( { description } ) } />
					<Text label={ __( 'Menu position', 'libreforge' ) } help={ __( 'Number. 5 = below Posts, 20 = below Pages.', 'libreforge' ) } type="number" value={ config.menu_position ?? '' } onChange={ ( value ) => set( { menu_position: value === '' ? null : Number( value ) } ) } />
					<Text label={ __( 'REST base', 'libreforge' ) } help={ __( 'For developers.', 'libreforge' ) } value={ config.rest_base } placeholder={ slug } mono onChange={ ( rest_base ) => set( { rest_base } ) } />
					<Text label={ __( 'Capability type', 'libreforge' ) } help={ __( 'For developers. Keep "post" if unsure.', 'libreforge' ) } value={ config.capability_type || 'post' } mono onChange={ ( capability_type ) => set( { capability_type } ) } />
					<Toggle label={ __( 'Publicly queryable', 'libreforge' ) } help={ __( 'Allows front-end queries by URL parameters.', 'libreforge' ) } checked={ config.publicly_queryable } onChange={ ( publicly_queryable ) => set( { publicly_queryable } ) } />
					<Toggle label={ __( 'Delete items with their author', 'libreforge' ) } checked={ config.delete_with_user } onChange={ ( delete_with_user ) => set( { delete_with_user } ) } />
				</>
			),
		},
	];
}

/* -------------------------------------------------------------------------- taxonomies */

export function taxonomySections( { config, set, errors, environment, slug, setSlug, isNew } ) {
	const postTypes = environment.post_types.filter( ( type ) => ! HIDDEN_TYPES.includes( type.value ) );

	return [
		{
			id: 'general',
			title: __( 'General settings', 'libreforge' ),
			icon: 'admin-generic',
			open: true,
			keys: [ 'slug', 'singular', 'plural' ],
			content: (
				<>
					<Names config={ config } set={ set } errors={ errors } slug={ slug } setSlug={ setSlug } isNew={ isNew } maxLength={ 32 } examples={ [ __( 'City', 'libreforge' ), __( 'Cities', 'libreforge' ) ] } />
					<Identifier slug={ slug } setSlug={ setSlug } errors={ errors } maxLength={ 32 } isNew={ isNew } />
					<Chips label={ __( 'Used by', 'libreforge' ) } help={ __( 'Which content can be classified with it.', 'libreforge' ) } options={ postTypes } value={ config.object_types } onChange={ ( object_types ) => set( { object_types } ) } />
					<Toggle label={ __( 'Hierarchical', 'libreforge' ) } help={ __( 'On: like categories, with parent terms. Off: like tags.', 'libreforge' ) } checked={ config.hierarchical } onChange={ ( hierarchical ) => set( { hierarchical } ) } />
				</>
			),
		},
		fieldsSection( { config, set, errors, environment, help: __( 'Extra information for each term, like an image or a color.', 'libreforge' ) } ),
		{
			id: 'labels',
			title: __( 'Labels', 'libreforge' ),
			icon: 'translation',
			keys: [],
			content: (
				<>
					<p className="lf-intro">{ __( 'Created automatically from the names above. Fill only what you want to change.', 'libreforge' ) }</p>
					<LabelRows config={ config } set={ set } keys={ [ 'menu_name', 'all_items', 'add_new_item', 'edit_item', 'search_items', 'not_found', 'parent_item', 'back_to_items' ] } />
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
					<Toggle label={ __( 'Public', 'libreforge' ) } help={ __( 'Each term has its own archive page.', 'libreforge' ) } checked={ config.public } onChange={ ( value ) => set( { public: value } ) } />
					<Toggle label={ __( 'Column in the post list', 'libreforge' ) } checked={ config.show_admin_column } onChange={ ( show_admin_column ) => set( { show_admin_column } ) } />
					<Toggle label={ __( 'Block editor', 'libreforge' ) } help={ __( 'Shows the taxonomy in the block editor. Also controls the REST API.', 'libreforge' ) } checked={ config.show_in_rest } onChange={ ( show_in_rest ) => set( { show_in_rest } ) } />
					<Toggle label={ __( 'Quick edit', 'libreforge' ) } checked={ config.show_in_quick_edit } onChange={ ( show_in_quick_edit ) => set( { show_in_quick_edit } ) } />
					<Toggle label={ __( 'Tag cloud widget', 'libreforge' ) } checked={ config.show_tagcloud } onChange={ ( show_tagcloud ) => set( { show_tagcloud } ) } />
					<Toggle label={ __( 'Available in navigation menus', 'libreforge' ) } checked={ config.show_in_nav_menus } onChange={ ( show_in_nav_menus ) => set( { show_in_nav_menus } ) } />
					<Text label={ __( 'Address of the terms', 'libreforge' ) } help={ sprintf( /* translators: %s: example URL */ __( 'Part of the URL before each term: %s', 'libreforge' ), `${ window.location.origin }/${ config.rewrite_slug || slug || 'slug' }/…` ) } value={ config.rewrite_slug } placeholder={ slug } onChange={ ( rewrite_slug ) => set( { rewrite_slug } ) } />
					<Toggle label={ __( 'Include parent terms in the URL', 'libreforge' ) } checked={ config.hierarchical_rewrite } onChange={ ( hierarchical_rewrite ) => set( { hierarchical_rewrite } ) } />
					<Toggle label={ __( 'Use the permalink base', 'libreforge' ) } checked={ config.with_front } onChange={ ( with_front ) => set( { with_front } ) } />
					<Text label={ __( 'REST base', 'libreforge' ) } help={ __( 'For developers.', 'libreforge' ) } value={ config.rest_base } placeholder={ slug } mono onChange={ ( rest_base ) => set( { rest_base } ) } />
				</>
			),
		},
	];
}

/* -------------------------------------------------------------------------- field groups */

export function fieldGroupSections( { config, set, errors, environment, slug, setSlug, isNew } ) {
	const object = config.object || 'post';
	const syncSlug = followSlug( { slug, setSlug, isNew, maxLength: 64, previous: config.title } );

	return [
		{
			id: 'general',
			title: __( 'General settings', 'libreforge' ),
			icon: 'admin-generic',
			open: true,
			keys: [ 'title', 'post_types', 'taxonomies' ],
			content: (
				<>
					<Text
						label={ __( 'Group title', 'libreforge' ) }
						help={ __( 'Title of the box with the fields. Ex.: Property details', 'libreforge' ) }
						value={ config.title }
						placeholder={ __( 'Property details', 'libreforge' ) }
						error={ errors.title }
						onChange={ ( title ) => {
							syncSlug( title );
							set( { title } );
						} }
					/>
					<Choose
						label={ __( 'Show on', 'libreforge' ) }
						help={ __( 'Which edit screens get these fields.', 'libreforge' ) }
						value={ object }
						options={ [
							{ value: 'post', label: __( 'Posts / post types', 'libreforge' ) },
							{ value: 'term', label: __( 'Taxonomy terms', 'libreforge' ) },
							{ value: 'user', label: __( 'User profiles', 'libreforge' ) },
						] }
						onChange={ ( value ) => set( { object: value } ) }
					/>
					{ object === 'post' && (
						<Chips label={ __( 'Post types', 'libreforge' ) } options={ environment.post_types.filter( ( type ) => ! HIDDEN_TYPES.includes( type.value ) ) } value={ config.post_types } onChange={ ( post_types ) => set( { post_types } ) } error={ errors.post_types } />
					) }
					{ object === 'term' && <Chips label={ __( 'Taxonomies', 'libreforge' ) } options={ environment.taxonomies } value={ config.taxonomies } onChange={ ( taxonomies ) => set( { taxonomies } ) } error={ errors.taxonomies } /> }
					{ object === 'user' && <Chips label={ __( 'Only for these roles', 'libreforge' ) } help={ __( 'Leave empty to show on every profile.', 'libreforge' ) } options={ environment.roles } value={ config.roles } onChange={ ( roles ) => set( { roles } ) } /> }
					{ object === 'post' && (
						<Choose
							label={ __( 'Position on the screen', 'libreforge' ) }
							value={ config.context || 'normal' }
							options={ [
								{ value: 'normal', label: __( 'Below the content', 'libreforge' ) },
								{ value: 'side', label: __( 'Sidebar', 'libreforge' ) },
							] }
							onChange={ ( context ) => set( { context } ) }
						/>
					) }
				</>
			),
		},
		fieldsSection( { config, set, errors, environment, help: __( 'Each field needs a label (what people see) and a name (how it is saved).', 'libreforge' ) } ),
		{
			id: 'advanced',
			title: __( 'Advanced settings', 'libreforge' ),
			icon: 'admin-tools',
			keys: [ 'slug' ],
			content: (
				<>
					<Identifier slug={ slug } setSlug={ setSlug } errors={ errors } maxLength={ 64 } isNew={ isNew } />
					<Choose
						label={ __( 'Priority', 'libreforge' ) }
						help={ __( 'Higher priority boxes appear first.', 'libreforge' ) }
						value={ config.priority || 'default' }
						options={ [
							{ value: 'high', label: __( 'High', 'libreforge' ) },
							{ value: 'default', label: __( 'Default', 'libreforge' ) },
							{ value: 'low', label: __( 'Low', 'libreforge' ) },
						] }
						onChange={ ( priority ) => set( { priority } ) }
					/>
				</>
			),
		},
	];
}

/* -------------------------------------------------------------------------- options pages */

export function optionsPageSections( { config, set, errors, environment, slug, setSlug, isNew } ) {
	const option = environment.option_prefix + ( slug || slugify( config.title ) );
	const syncSlug = followSlug( { slug, setSlug, isNew, maxLength: 64, previous: config.title } );

	return [
		{
			id: 'general',
			title: __( 'General settings', 'libreforge' ),
			icon: 'admin-generic',
			open: true,
			keys: [ 'title' ],
			content: (
				<>
					<Text
						label={ __( 'Page title', 'libreforge' ) }
						help={ __( 'Ex.: Contact details, Social networks, Promo banner.', 'libreforge' ) }
						value={ config.title }
						placeholder={ __( 'Contact details', 'libreforge' ) }
						error={ errors.title }
						onChange={ ( title ) => {
							syncSlug( title );
							set( { title } );
						} }
					/>
					<Choose label={ __( 'Where it appears in the menu', 'libreforge' ) } value={ config.parent ?? '' } options={ environment.parents } onChange={ ( parent ) => set( { parent } ) } />
					{ ( config.parent ?? '' ) === '' && (
						<SettingRow label={ __( 'Menu icon', 'libreforge' ) }>
							<IconPicker value={ config.icon } onChange={ ( icon ) => set( { icon } ) } />
						</SettingRow>
					) }
					<SettingRow label={ __( 'How to use the values', 'libreforge' ) } help={ __( 'Copy this token into any text or dynamic field.', 'libreforge' ) }>
						<code className="lf-token">{ `{{option|${ option }:field_name}}` }</code>
					</SettingRow>
				</>
			),
		},
		fieldsSection( { config, set, errors, environment, help: __( 'The information this page stores, like phone, e-mail or address.', 'libreforge' ) } ),
		{
			id: 'advanced',
			title: __( 'Advanced settings', 'libreforge' ),
			icon: 'admin-tools',
			keys: [ 'slug' ],
			content: (
				<>
					<Identifier slug={ slug } setSlug={ setSlug } errors={ errors } maxLength={ 64 } isNew={ isNew } help={ sprintf( /* translators: %s: option name */ __( 'Values are saved in the option "%s".', 'libreforge' ), option ) } />
					<Text label={ __( 'Menu title', 'libreforge' ) } help={ __( 'Shorter text for the menu. Empty = same as the page title.', 'libreforge' ) } value={ config.menu_title } placeholder={ config.title } onChange={ ( menu_title ) => set( { menu_title } ) } />
					<Text label={ __( 'Menu position', 'libreforge' ) } type="number" value={ config.position ?? '' } onChange={ ( value ) => set( { position: value === '' ? null : Number( value ) } ) } />
					<Text label={ __( 'Required capability', 'libreforge' ) } help={ __( 'Who can open the page. Keep "manage_options" for administrators only.', 'libreforge' ) } value={ config.capability || 'manage_options' } mono onChange={ ( capability ) => set( { capability } ) } />
					<Toggle label={ __( 'Autoload', 'libreforge' ) } help={ __( 'Loads the values on every page. Turn off for pages with many fields used rarely.', 'libreforge' ) } checked={ config.autoload ?? true } onChange={ ( autoload ) => set( { autoload } ) } />
				</>
			),
		},
	];
}

/* -------------------------------------------------------------------------- glossaries */

export function glossarySections( { config, set, errors, slug, setSlug, isNew } ) {
	const options = Array.isArray( config.options ) ? config.options : [];
	const syncSlug = followSlug( { slug, setSlug, isNew, maxLength: 64, previous: config.title } );

	return [
		{
			id: 'general',
			title: __( 'General settings', 'libreforge' ),
			icon: 'admin-generic',
			open: true,
			keys: [ 'title', 'slug' ],
			content: (
				<>
					<Text
						label={ __( 'Glossary name', 'libreforge' ) }
						help={ __( 'Ex.: States, Amenities, Sizes.', 'libreforge' ) }
						value={ config.title }
						placeholder={ __( 'States', 'libreforge' ) }
						error={ errors.title }
						onChange={ ( title ) => {
							syncSlug( title );
							set( { title } );
						} }
					/>
					<Identifier slug={ slug } setSlug={ setSlug } errors={ errors } maxLength={ 64 } isNew={ isNew } />
				</>
			),
		},
		{
			id: 'options',
			title: __( 'Options', 'libreforge' ),
			icon: 'list-view',
			open: true,
			count: options.length,
			keys: [ 'options' ],
			content: (
				<>
					<p className="lf-intro">{ __( 'The "value" is what gets saved; the "label" is what people see. Ex.: sp → São Paulo.', 'libreforge' ) }</p>
					{ errors.options && <p className="lf-row__error">{ errors.options }</p> }
					<OptionsEditor value={ options } onChange={ ( value ) => set( { options: value } ) } />
				</>
			),
		},
	];
}
