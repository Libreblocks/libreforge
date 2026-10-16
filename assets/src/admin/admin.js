/**
 * LibreForge admin app. Each LibreForge menu page mounts it with a section:
 * "dashboard" or one definition kind (post types, taxonomies, field groups,
 * options pages, glossaries) with its list and editor.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SearchControl, Spinner, ToggleControl } from '@wordpress/components';
import { createRoot, useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs, getQueryArg } from '@wordpress/url';

import { AppShell, Badge, CardGrid, Collapsible, EditorColumns, EmptyState, IconTile, ItemCard, ModuleCard, PageHeader, Panel, StatCard, StatGrid } from '@libreblocks/ui';

import { deleteDefinition, getDefinition, getEnvironment, listDefinitions, saveDefinition } from './api';
import { MenuPreview } from './components';
import { QueryPreview, querySections } from './query-form';
import TemplatesScreen from './templates-screen';
import { relationSections } from './relation-form';
import { fieldGroupSections, glossarySections, optionsPageSections, postTypeSections, taxonomySections } from './forms';
import './admin.scss';

const data = window.libreforgeAdmin || { tabs: [] };
const tabUrl = ( key ) => data.tabs.find( ( tab ) => tab.key === key )?.href || '#';

const KINDS = {
	post_type: {
		title: __( 'Post Types', 'libreforge' ),
		singular: __( 'post type', 'libreforge' ),
		icon: 'admin-post',
		addLabel: __( 'New Post Type', 'libreforge' ),
		createLabel: __( 'Create Post Type', 'libreforge' ),
		emptyTitle: __( 'Create your first Post Type', 'libreforge' ),
		empty: __( 'Post types are content of their own, like Properties, Courses or Team members, each with its own menu.', 'libreforge' ),
		sections: postTypeSections,
		itemIcon: ( item ) => item.config.menu_icon || 'admin-post',
		describe: ( item ) =>
			[
				/* translators: %d: number of items */
				sprintf( _n( '%d item', '%d items', item.summary.items || 0, 'libreforge' ), item.summary.items || 0 ),
				/* translators: %d: number of fields */
				sprintf( _n( '%d field', '%d fields', item.summary.fields || 0, 'libreforge' ), item.summary.fields || 0 ),
				/* translators: %d: number of taxonomies */
				sprintf( _n( '%d taxonomy', '%d taxonomies', item.summary.taxonomies || 0, 'libreforge' ), item.summary.taxonomies || 0 ),
				/* translators: %d: number of field groups */
				sprintf( _n( '%d field group', '%d field groups', item.summary.field_groups || 0, 'libreforge' ), item.summary.field_groups || 0 ),
			].join( ' · ' ),
		defaults: { singular: '', plural: '', public: true, has_archive: true, show_in_rest: true, show_ui: true, show_in_menu: true, show_in_nav_menus: true, publicly_queryable: true, supports: [ 'title', 'editor', 'thumbnail' ], taxonomies: [], menu_icon: 'dashicons-admin-post', with_front: true, labels: {}, fields: [], fields_title: '', fields_context: 'normal' },
	},
	taxonomy: {
		title: __( 'Taxonomies', 'libreforge' ),
		singular: __( 'taxonomy', 'libreforge' ),
		icon: 'tag',
		addLabel: __( 'New taxonomy', 'libreforge' ),
		createLabel: __( 'Create taxonomy', 'libreforge' ),
		emptyTitle: __( 'Create your first taxonomy', 'libreforge' ),
		empty: __( 'Taxonomies classify content, like Cities or Property types, and power filters.', 'libreforge' ),
		sections: taxonomySections,
		itemIcon: ( item ) => ( item.config.hierarchical ? 'category' : 'tag' ),
		describe: ( item ) =>
			[
				/* translators: %d: number of terms */
				sprintf( _n( '%d term', '%d terms', item.summary.terms || 0, 'libreforge' ), item.summary.terms || 0 ),
				item.summary.fields ? sprintf( /* translators: %d: number of fields */ _n( '%d field', '%d fields', item.summary.fields, 'libreforge' ), item.summary.fields ) : '',
				item.config.hierarchical ? __( 'Hierarchical', 'libreforge' ) : __( 'Tag-like', 'libreforge' ),
				( item.summary.object_types || [] ).join( ', ' ),
			]
				.filter( Boolean )
				.join( ' · ' ),
		defaults: { singular: '', plural: '', object_types: [], hierarchical: true, public: true, publicly_queryable: true, show_in_rest: true, show_admin_column: true, show_in_quick_edit: true, show_in_nav_menus: true, with_front: true, labels: {}, fields: [] },
	},
	field_group: {
		title: __( 'Meta Boxes', 'libreforge' ),
		singular: __( 'meta box', 'libreforge' ),
		icon: 'forms',
		addLabel: __( 'New Meta Box', 'libreforge' ),
		createLabel: __( 'Create Meta Box', 'libreforge' ),
		emptyTitle: __( 'Create your first Meta Box', 'libreforge' ),
		empty: __( 'Meta Boxes add custom fields, like price, gallery or address, to posts, terms or user profiles.', 'libreforge' ),
		sections: fieldGroupSections,
		itemIcon: ( item ) => ( { post: 'admin-post', term: 'tag', user: 'admin-users' }[ item.config.object ] || 'forms' ),
		describe: ( item ) => {
			const where = { post: item.config.post_types, term: item.config.taxonomies, user: [ __( 'User profiles', 'libreforge' ) ] }[ item.config.object ] || [];
			/* translators: %d: number of fields */
			return [ sprintf( _n( '%d field', '%d fields', item.summary.fields || 0, 'libreforge' ), item.summary.fields || 0 ), where.join( ', ' ) ].filter( Boolean ).join( ' · ' );
		},
		defaults: { title: '', object: 'post', post_types: [], taxonomies: [], roles: [], context: 'normal', priority: 'default', fields: [] },
	},
	options_page: {
		title: __( 'Options Pages', 'libreforge' ),
		singular: __( 'options page', 'libreforge' ),
		icon: 'admin-settings',
		addLabel: __( 'New Options Page', 'libreforge' ),
		createLabel: __( 'Create Options Page', 'libreforge' ),
		emptyTitle: __( 'Create your first Options Page', 'libreforge' ),
		empty: __( 'Options Pages hold site-wide settings, like contact details, social links or a promo banner.', 'libreforge' ),
		sections: optionsPageSections,
		itemIcon: ( item ) => item.config.icon || 'admin-settings',
		/* translators: %d: number of fields */
		describe: ( item ) => sprintf( _n( '%d field', '%d fields', item.summary.fields || 0, 'libreforge' ), item.summary.fields || 0 ),
		defaults: { title: '', menu_title: '', parent: '', icon: 'dashicons-admin-generic', capability: 'manage_options', autoload: true, fields: [] },
	},
	relation: {
		title: __( 'Relations', 'libreforge' ),
		singular: __( 'relation', 'libreforge' ),
		icon: 'networking',
		addLabel: __( 'New relation', 'libreforge' ),
		createLabel: __( 'Create relation', 'libreforge' ),
		emptyTitle: __( 'Create your first relation', 'libreforge' ),
		empty: __( 'Relations connect content: a broker and their properties, a course and its lessons. Then a listing can show "the properties of this broker".', 'libreforge' ),
		sections: relationSections,
		itemIcon: () => 'networking',
		describe: ( item ) => `${ item.config.parent_object || '…' } → ${ item.config.child_object || '…' }`,
		defaults: { title: '', parent_object: '', child_object: '', type: 'one_to_many', parent_label: '', child_label: '', show_in_parent: true, show_in_child: true },
	},
	query: {
		title: __( 'Query Builder', 'libreforge' ),
		singular: __( 'query', 'libreforge' ),
		icon: 'filter',
		addLabel: __( 'New Query', 'libreforge' ),
		createLabel: __( 'Create Query', 'libreforge' ),
		emptyTitle: __( 'Create your first Query', 'libreforge' ),
		empty: __( 'A query picks which items a listing shows, like "the 6 newest properties in São Paulo". Build it once, reuse it in any page.', 'libreforge' ),
		sections: querySections,
		aside: ( definition ) => <QueryPreview config={ definition.config } />,
		itemIcon: ( item ) => ( { posts: 'admin-post', terms: 'tag', users: 'admin-users' }[ item.config.type ] || 'filter' ),
		describe: ( item ) =>
			[
				{ posts: ( item.config.post_types || [] ).join( ', ' ), terms: ( item.config.taxonomies || [] ).join( ', ' ), users: __( 'Users', 'libreforge' ) }[ item.config.type ],
				/* translators: %d: items per page */
				item.config.per_page > 0 ? sprintf( __( '%d per page', 'libreforge' ), item.config.per_page ) : __( 'All items', 'libreforge' ),
			]
				.filter( Boolean )
				.join( ' · ' ),
		defaults: { title: '', type: 'posts', post_types: [ 'post' ], statuses: [ 'publish' ], per_page: 6, orderby: 'date', order: 'DESC', exclude_current: true, ignore_sticky: true, tax_rules: [], meta_rules: [], tax_relation: 'AND', meta_relation: 'AND' },
	},
	glossary: {
		title: __( 'Glossaries', 'libreforge' ),
		singular: __( 'glossary', 'libreforge' ),
		icon: 'list-view',
		addLabel: __( 'New glossary', 'libreforge' ),
		createLabel: __( 'Create glossary', 'libreforge' ),
		emptyTitle: __( 'Create your first glossary', 'libreforge' ),
		empty: __( 'Glossaries are option lists, like States or Amenities, reused by many fields.', 'libreforge' ),
		sections: glossarySections,
		itemIcon: () => 'list-view',
		/* translators: %d: number of options */
		describe: ( item ) => sprintf( _n( '%d option', '%d options', item.summary.options || 0, 'libreforge' ), item.summary.options || 0 ),
		defaults: { title: '', options: [] },
	},
};

/**
 * Keeps the current view in the URL (&view=edit&id=3) so reloads and the back button work.
 * Not "action": admin.php fires admin_action_{action} hooks for that parameter.
 */
function useRoute() {
	const read = () => ( { view: getQueryArg( window.location.href, 'view' ) || 'list', id: Number( getQueryArg( window.location.href, 'id' ) ) || 0 } );
	const [ route, setRoute ] = useState( read );

	useEffect( () => {
		const onPop = () => setRoute( read() );
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [] );

	const go = useCallback( ( view, id = 0 ) => {
		const page = getQueryArg( window.location.href, 'page' );
		window.history.pushState( {}, '', addQueryArgs( window.location.pathname, view === 'list' ? { page } : { page, view, id: id || undefined } ) );
		setRoute( { view, id } );
		window.scrollTo( 0, 0 );
	}, [] );

	return [ route, go ];
}

/* -------------------------------------------------------------------------- dashboard */

function Dashboard() {
	const [ overview, setOverview ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/libreblocks/v1/forge/overview' } )
			.then( setOverview )
			.catch( ( err ) => setError( err.message ) );
	}, [] );

	const total = ( kind ) => overview?.counts[ kind ]?.total;
	const isEmpty = overview && ! total( 'post_type' ) && ! total( 'field_group' );

	return (
		<>
			<PageHeader title="LibreForge" subtitle={ __( 'Build the content structure of your site: post types, taxonomies, fields and settings pages.', 'libreforge' ) } />

			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }

			<StatGrid>
				{ Object.entries( KINDS ).map( ( [ kind, meta ] ) => (
					<StatCard key={ kind } label={ meta.title } value={ total( kind ) } icon={ meta.icon } href={ tabUrl( kind ) } />
				) ) }
			</StatGrid>

			{ isEmpty && (
				<Panel title={ __( 'Get started in three steps', 'libreforge' ) } description={ __( 'A typical setup for a real estate site, a course catalog or a team page.', 'libreforge' ) }>
					<ol className="lf-steps">
						<li>
							<IconTile icon="admin-post" />
							<div>
								<strong>{ __( 'Create a post type', 'libreforge' ) }</strong>
								<p>{ __( 'For example "Properties". It gets its own menu.', 'libreforge' ) }</p>
							</div>
							<Button variant="primary" href={ addQueryArgs( tabUrl( 'post_type' ), { view: 'new' } ) }>
								{ __( 'Create', 'libreforge' ) }
							</Button>
						</li>
						<li>
							<IconTile icon="tag" />
							<div>
								<strong>{ __( 'Add a taxonomy', 'libreforge' ) }</strong>
								<p>{ __( 'For example "Cities", to classify and filter.', 'libreforge' ) }</p>
							</div>
							<Button variant="secondary" href={ addQueryArgs( tabUrl( 'taxonomy' ), { view: 'new' } ) }>
								{ __( 'Create', 'libreforge' ) }
							</Button>
						</li>
						<li>
							<IconTile icon="forms" />
							<div>
								<strong>{ __( 'Add custom fields', 'libreforge' ) }</strong>
								<p>{ __( 'Price, gallery, address: everything each item needs.', 'libreforge' ) }</p>
							</div>
							<Button variant="secondary" href={ addQueryArgs( tabUrl( 'field_group' ), { view: 'new' } ) }>
								{ __( 'Create', 'libreforge' ) }
							</Button>
						</li>
					</ol>
				</Panel>
			) }

			{ overview?.post_types.length > 0 && (
				<>
					<h2 className="lb-section-title">{ __( 'Your content', 'libreforge' ) }</h2>
					<CardGrid columns={ 4 }>
						{ overview.post_types.map( ( type ) => (
							<a key={ type.id } className="lb-card lf-content-card" href={ type.url }>
								<IconTile icon={ type.icon || 'admin-post' } />
								<span>
									<strong>{ type.title }</strong>
									{ /* translators: %d: number of items */ }
									<small>{ sprintf( _n( '%d item', '%d items', type.items, 'libreforge' ), type.items ) }</small>
								</span>
							</a>
						) ) }
					</CardGrid>
				</>
			) }

			<h2 className="lb-section-title lf-spaced">
				{ __( 'Modules', 'libreforge' ) }
				<Badge tone="pro">{ __( 'Coming with LibreBlocks Pro', 'libreforge' ) }</Badge>
			</h2>
			{ ! overview && ! error && <Spinner /> }
			<CardGrid columns={ 3 }>
				{ ( overview?.modules || [] ).map( ( module ) => (
					<ModuleCard
						key={ module.id }
						icon={ module.icon }
						title={ module.title }
						description={ module.description }
						badge={ module.available ? <Badge tone="success">{ __( 'Active', 'libreforge' ) }</Badge> : <Badge>{ __( 'Coming soon', 'libreforge' ) }</Badge> }
					/>
				) ) }
			</CardGrid>
		</>
	);
}

/* -------------------------------------------------------------------------- list */

function List( { kind, onEdit, onCreate } ) {
	const meta = KINDS[ kind ];
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ search, setSearch ] = useState( '' );

	const load = () =>
		listDefinitions( kind )
			.then( setItems )
			.catch( ( err ) => setError( err.message ) );

	useEffect( () => {
		load();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ kind ] );

	const visible = useMemo( () => {
		const term = search.trim().toLowerCase();
		return ( items || [] ).filter( ( item ) => ! term || `${ item.title } ${ item.slug }`.toLowerCase().includes( term ) );
	}, [ items, search ] );

	const toggleStatus = ( item ) =>
		saveDefinition( { id: item.id, status: item.status === 'active' ? 'draft' : 'active' } )
			.then( load )
			.catch( ( err ) => setError( err.message ) );

	const remove = ( item ) => {
		const message =
			kind === 'post_type' || kind === 'taxonomy'
				? __( 'Delete this definition? The content already created stays in the database but will no longer be visible until you recreate it with the same slug.', 'libreforge' )
				: __( 'Delete this definition? Values already saved stay in the database.', 'libreforge' );

		// eslint-disable-next-line no-alert
		if ( window.confirm( message ) ) {
			deleteDefinition( item.id )
				.then( load )
				.catch( ( err ) => setError( err.message ) );
		}
	};

	const active = ( items || [] ).filter( ( item ) => item.status === 'active' ).length;

	return (
		<>
			<PageHeader
				icon={ meta.icon }
				title={ meta.title }
				subtitle={ items ? sprintf( /* translators: 1: total, 2: active */ __( '%1$d in total · %2$d active', 'libreforge' ), items.length, active ) : '' }
				actions={
					<>
						{ items?.length > 4 && <SearchControl value={ search } onChange={ setSearch } placeholder={ __( 'Search', 'libreforge' ) } __nextHasNoMarginBottom /> }
						<Button variant="primary" icon="plus-alt2" onClick={ onCreate }>
							{ meta.addLabel }
						</Button>
					</>
				}
			/>

			{ error && <Notice status="error" onRemove={ () => setError( null ) }>{ error }</Notice> }

			{ ! items && ! error && <Spinner /> }

			{ items && items.length === 0 && (
				<EmptyState
					icon={ meta.icon }
					title={ meta.emptyTitle }
					text={ meta.empty }
					action={
						<Button variant="primary" icon="plus-alt2" onClick={ onCreate }>
							{ meta.addLabel }
						</Button>
					}
				/>
			) }

			{ visible.length > 0 && (
				<CardGrid columns={ 3 }>
					{ visible.map( ( item ) => (
						<ItemCard
							key={ item.id }
							icon={ meta.itemIcon( item ) }
							title={ item.title || item.slug }
							code={ item.slug }
							status={ item.status === 'active' ? <Badge tone="success">{ __( 'Active', 'libreforge' ) }</Badge> : <Badge>{ __( 'Draft', 'libreforge' ) }</Badge> }
							description={ meta.describe( item ) }
							onClick={ () => onEdit( item.id ) }
							actions={
								<>
									<ToggleControl
										checked={ item.status === 'active' }
										onChange={ () => toggleStatus( item ) }
										label={ item.status === 'active' ? __( 'Active', 'libreforge' ) : __( 'Draft', 'libreforge' ) }
										__nextHasNoMarginBottom
									/>
									{ item.summary.list_url && <Button icon="external" size="small" label={ __( 'Open the items', 'libreforge' ) } href={ item.summary.list_url } /> }
									<Button icon="edit" size="small" label={ __( 'Edit', 'libreforge' ) } onClick={ () => onEdit( item.id ) } />
									<Button icon="trash" size="small" isDestructive label={ __( 'Delete', 'libreforge' ) } onClick={ () => remove( item ) } />
								</>
							}
						/>
					) ) }
				</CardGrid>
			) }

			{ items && items.length > 0 && visible.length === 0 && <p className="lf-muted">{ __( 'Nothing matches your search.', 'libreforge' ) }</p> }
		</>
	);
}

/* -------------------------------------------------------------------------- editor */

const snapshot = ( definition ) => JSON.stringify( { slug: definition.slug, status: definition.status, config: definition.config } );

const sectionHasError = ( section, errors ) =>
	Object.keys( errors ).some( ( key ) => section.keys.some( ( prefix ) => ( prefix.endsWith( '.' ) ? key.startsWith( prefix ) : key === prefix ) ) );

function Editor( { kind, id, onBack, onCreated } ) {
	const meta = KINDS[ kind ];
	const [ environment, setEnvironment ] = useState( null );
	const [ definition, setDefinition ] = useState( id ? null : { slug: '', status: 'active', config: meta.defaults } );
	const [ errors, setErrors ] = useState( {} );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ attempt, setAttempt ] = useState( 0 );
	const saved = useRef( id ? null : snapshot( { slug: '', status: 'active', config: meta.defaults } ) );

	useEffect( () => {
		getEnvironment().then( setEnvironment );

		if ( id ) {
			getDefinition( id )
				.then( ( loaded ) => {
					saved.current = snapshot( loaded );
					setDefinition( loaded );
				} )
				.catch( ( err ) => setNotice( { status: 'error', message: err.message } ) );
		}
	}, [ id ] );

	if ( notice?.status === 'error' && ! definition ) {
		return <Notice status="error" isDismissible={ false }>{ notice.message }</Notice>;
	}

	if ( ! environment || ! definition ) {
		return <Spinner />;
	}

	const set = ( patch ) => setDefinition( ( current ) => ( { ...current, config: { ...current.config, ...patch } } ) );
	const setSlug = ( slug ) => setDefinition( ( current ) => ( { ...current, slug } ) );
	const sections = meta
		.sections( { config: definition.config, set, errors, environment, slug: definition.slug, setSlug, isNew: ! definition.id } )
		.map( ( item ) => ( { ...item, hasError: sectionHasError( item, errors ) } ) );
	const dirty = snapshot( definition ) !== saved.current;
	const title = definition.config.plural || definition.config.title || ( definition.id ? definition.slug : sprintf( /* translators: %s: kind name */ __( 'New %s', 'libreforge' ), meta.singular ) );

	const save = () => {
		setSaving( true );
		setErrors( {} );
		setNotice( null );

		saveDefinition( { id: definition.id, kind, slug: definition.slug, status: definition.status, config: definition.config } )
			.then( ( result ) => {
				saved.current = snapshot( result );
				setDefinition( result );
				setNotice( { status: 'success', message: __( 'Saved.', 'libreforge' ) } );

				if ( ! definition.id ) {
					onCreated( result.id );
				}
			} )
			.catch( ( err ) => {
				const found = err?.data?.errors || {};
				setErrors( found );
				setNotice( { status: 'error', message: err.message || __( 'Could not save.', 'libreforge' ) } );

				// Re-mount the sections so the ones with errors open.
				setAttempt( ( value ) => value + 1 );
				window.scrollTo( 0, 0 );
			} )
			.finally( () => setSaving( false ) );
	};

	const discard = () => {
		setDefinition( { ...definition, ...JSON.parse( saved.current ) } );
		setErrors( {} );
		setNotice( null );
	};

	const actions = (
		<div className="lb-card lf-actions">
			<h3>{ __( 'Actions', 'libreforge' ) }</h3>
			<Button variant="primary" className="lf-actions__save" onClick={ save } isBusy={ saving } disabled={ saving }>
				{ definition.id ? __( 'Save changes', 'libreforge' ) : meta.createLabel }
			</Button>
			<p className={ `lf-actions__status${ dirty ? ' is-dirty' : '' }` }>
				{ dirty || ! definition.id ? __( 'Unsaved changes', 'libreforge' ) : __( 'All changes saved', 'libreforge' ) }
			</p>
			<ToggleControl
				label={ __( 'Active', 'libreforge' ) }
				help={ __( 'Turn off to keep it as a draft without showing it on the site.', 'libreforge' ) }
				checked={ definition.status === 'active' }
				onChange={ ( on ) => setDefinition( { ...definition, status: on ? 'active' : 'draft' } ) }
				__nextHasNoMarginBottom
			/>
			<div className="lf-actions__links">
				{ definition.id > 0 && dirty && (
					<Button variant="link" onClick={ discard }>
						{ __( 'Discard changes', 'libreforge' ) }
					</Button>
				) }
				<Button variant="link" onClick={ onBack }>
					{ sprintf( /* translators: %s: list title */ __( '← Back to %s', 'libreforge' ), meta.title.toLowerCase() ) }
				</Button>
			</div>
		</div>
	);

	return (
		<div className="lf-editor">
			<PageHeader
				back={ { label: meta.title, onClick: onBack } }
				icon={ meta.itemIcon( definition ) }
				title={ title }
				badge={ definition.status === 'active' ? <Badge tone="success">{ __( 'Active', 'libreforge' ) }</Badge> : <Badge>{ __( 'Draft', 'libreforge' ) }</Badge> }
				subtitle={ definition.slug ? <code>{ definition.slug }</code> : meta.title }
			/>

			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) } className="lf-notice">
					{ notice.message }
					{ Object.keys( errors ).length > 0 && (
						<ul>
							{ Object.values( errors ).map( ( message, index ) => (
								<li key={ index }>{ message }</li>
							) ) }
						</ul>
					) }
				</Notice>
			) }

			<EditorColumns
				main={ sections.map( ( item ) => (
					<Collapsible key={ `${ item.id }-${ attempt }` } title={ item.title } icon={ item.icon } count={ item.count } open={ item.open || item.hasError } hasError={ item.hasError }>
						{ item.content }
					</Collapsible>
				) ) }
				aside={
					<>
						{ actions }
						{ meta.aside && meta.aside( definition ) }
						{ kind === 'post_type' && (
							<div className="lb-card lf-aside-card">
								<h3>{ __( 'Menu preview', 'libreforge' ) }</h3>
								<MenuPreview icon={ definition.config.menu_icon } plural={ definition.config.plural } singular={ definition.config.singular } />
							</div>
						) }
						<div className="lb-card lf-aside-card lf-tips">
							<h3>{ __( 'Tips', 'libreforge' ) }</h3>
							<p>{ meta.empty }</p>
							<p>{ __( 'Fill in the open sections and click the save button. Everything else already has a value that works.', 'libreforge' ) }</p>
						</div>
					</>
				}
			/>
		</div>
	);
}

/* -------------------------------------------------------------------------- app */

function KindScreen( { kind } ) {
	const [ route, go ] = useRoute();

	if ( route.view === 'edit' || route.view === 'new' ) {
		return <Editor key={ route.id || 'new' } kind={ kind } id={ route.id } onBack={ () => go( 'list' ) } onCreated={ ( id ) => go( 'edit', id ) } />;
	}

	return <List kind={ kind } onEdit={ ( id ) => go( 'edit', id ) } onCreate={ () => go( 'new' ) } />;
}

function App( { section } ) {
	return (
		<AppShell product={ { name: 'LibreForge', icon: data.icon || 'hammer' } } tabs={ data.tabs } current={ section }>
			{ section === 'dashboard' && <Dashboard /> }
			{ section === 'templates' && <TemplatesScreen /> }
			{ KINDS[ section ] && <KindScreen kind={ section } /> }
		</AppShell>
	);
}

const mount = document.getElementById( 'libreforge-app' );
const section = mount?.dataset.section;

if ( mount && ( [ 'dashboard', 'templates' ].includes( section ) || KINDS[ section ] ) ) {
	createRoot( mount ).render( <App section={ section } /> );
}

