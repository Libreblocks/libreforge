/**
 * Generic list + editor screen for LibreForge definitions.
 */
import { Button, Notice, SearchControl, Spinner, ToggleControl } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs, getQueryArg } from '@wordpress/url';

import { Badge, CardGrid, Collapsible, EditorColumns, EmptyState, ItemCard, PageHeader } from '@libreblocks/ui';

import { deleteDefinition, getDefinition, getEnvironment, listDefinitions, saveDefinition } from './api';
import { MenuPreview } from './components';

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

/* -------------------------------------------------------------------------- list */

function List( { kind, meta, onEdit, onCreate } ) {
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
		const standard =
			kind === 'post_type' || kind === 'taxonomy'
				? __( 'Delete this definition? The content already created stays in the database but will no longer be visible until you recreate it with the same slug.', 'libreforge' )
				: __( 'Delete this definition? Values already saved stay in the database.', 'libreforge' );
		const message = meta.deleteMessage || standard;

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
									{ item.summary.list_url && <Button icon="external" size="small" label={ meta.listUrlLabel || __( 'Open the items', 'libreforge' ) } href={ item.summary.list_url } /> }
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

function Editor( { kind, meta, id, onBack, onCreated } ) {
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

/* -------------------------------------------------------------------------- screen */

/**
 * List + editor of one definition kind. `meta` describes the kind (titles,
 * defaults, sections...). Other LibreBlocks plugins reuse it for their own kinds.
 */
export function KindScreen( { kind, meta } ) {
	const [ route, go ] = useRoute();

	if ( route.view === 'edit' || route.view === 'new' ) {
		return <Editor key={ route.id || 'new' } kind={ kind } meta={ meta } id={ route.id } onBack={ () => go( 'list' ) } onCreated={ ( id ) => go( 'edit', id ) } />;
	}

	return <List kind={ kind } meta={ meta } onEdit={ ( id ) => go( 'edit', id ) } onCreate={ () => go( 'new' ) } />;
}

