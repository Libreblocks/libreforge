/**
 * LibreForge admin app. Each LibreForge menu page mounts it with a section:
 * "dashboard" or one definition kind (post types, taxonomies, field groups,
 * options pages, glossaries) with its list and editor.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { AppShell, Badge, CardGrid, IconTile, ModuleCard, PageHeader, Panel, StatCard, StatGrid } from '@libreblocks/ui';

import { KindScreen } from './kind-screen';
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

/* -------------------------------------------------------------------------- dashboard */

function Dashboard() {
	const [ overview, setOverview ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/libreblocks/v1/forge/overview' } )
			.then( setOverview )
			.catch( ( err ) => setError( err.message ) );
	}, [] );

	const [ moduleError, setModuleError ] = useState( null );
	const [ moduleChanged, setModuleChanged ] = useState( false );

	const toggleModule = ( id, enabled ) => {
		// Switch at once; undo if the server refuses.
		setOverview( ( current ) => ( { ...current, modules: current.modules.map( ( m ) => ( m.id === id ? { ...m, enabled } : m ) ) } ) );
		apiFetch( { path: '/libreblocks/v1/forge/modules', method: 'POST', data: { id, enabled } } )
			.then( ( response ) => {
				setOverview( ( current ) => ( { ...current, modules: response.modules } ) );
				setModuleChanged( true );
			} )
			.catch( ( err ) => {
				setModuleError( err.message );
				setOverview( ( current ) => ( { ...current, modules: current.modules.map( ( m ) => ( m.id === id ? { ...m, enabled: ! enabled } : m ) ) } ) );
			} );
	};

	const total = ( kind ) => overview?.counts[ kind ]?.total;
	const isEmpty = overview && ! total( 'post_type' ) && ! total( 'field_group' );

	return (
		<>
			<PageHeader title="LibreForge" subtitle={ __( 'Build the content structure of your site: post types, taxonomies, fields and settings pages.', 'libreforge' ) } />

			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }

			<StatGrid>
				{ Object.entries( KINDS ).filter( ( [ kind ] ) => data.tabs.some( ( tab ) => tab.key === kind ) ).map( ( [ kind, meta ] ) => (
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
				<Badge tone="pro">{ __( 'More coming with LibreBlocks Pro', 'libreforge' ) }</Badge>
			</h2>
			{ ! overview && ! error && <Spinner /> }
			{ moduleError && <Notice status="error" onRemove={ () => setModuleError( null ) }>{ moduleError }</Notice> }
			<CardGrid columns={ 3 }>
				{ ( overview?.modules || [] ).map( ( module ) => (
					<ModuleCard
						key={ module.id }
						icon={ module.icon }
						title={ module.title }
						description={ module.description }
						checked={ module.enabled }
						onToggle={ module.available ? ( enabled ) => toggleModule( module.id, enabled ) : undefined }
						badge={ <Badge>{ module.pro ? __( 'Pro · coming soon', 'libreforge' ) : __( 'Coming soon', 'libreforge' ) }</Badge> }
					/>
				) ) }
			</CardGrid>
			{ moduleChanged && (
				<p className="lf-module-hint">
					{ __( 'Module updated. Reload the page to see the change in the menu.', 'libreforge' ) }{ ' ' }
					<Button variant="link" onClick={ () => window.location.reload() }>{ __( 'Reload now', 'libreforge' ) }</Button>
				</p>
			) }
		</>
	);
}

/* -------------------------------------------------------------------------- app */

function App( { section } ) {
	return (
		<AppShell product={ { name: 'LibreForge', icon: data.icon || 'hammer' } } tabs={ data.tabs } current={ section }>
			{ section === 'dashboard' && <Dashboard /> }
			{ section === 'templates' && <TemplatesScreen /> }
			{ KINDS[ section ] && <KindScreen kind={ section } meta={ KINDS[ section ] } /> }
		</AppShell>
	);
}

const mount = document.getElementById( 'libreforge-app' );
const section = mount?.dataset.section;

if ( mount && ( [ 'dashboard', 'templates' ].includes( section ) || KINDS[ section ] ) ) {
	createRoot( mount ).render( <App section={ section } /> );
}

