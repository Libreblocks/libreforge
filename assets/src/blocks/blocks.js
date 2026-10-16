/**
 * LibreForge blocks for the block editor. Rendering happens on the server
 * (ServerSideRender), so what you see while editing is what visitors get.
 */
import apiFetch from '@wordpress/api-fetch';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { Button, Notice, PanelBody, RangeControl, SelectControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import './blocks.scss';

import loopGrid from '../../../blocks/loop-grid/block.json';
import field from '../../../blocks/field/block.json';
import image from '../../../blocks/image/block.json';
import link from '../../../blocks/link/block.json';
import terms from '../../../blocks/terms/block.json';
import repeater from '../../../blocks/repeater/block.json';

const config = window.libreforgeBlocks || {};

/* -------------------------------------------------------------------------- shared data */

let dataPromise;

function useForgeData() {
	const [ data, setData ] = useState( null );

	useEffect( () => {
		dataPromise = dataPromise || apiFetch( { path: '/libreblocks/v1/forge/blocks-data' } );
		dataPromise.then( setData ).catch( () => setData( { queries: [], templates: [], post_types: [], taxonomies: [], fields: [], options: [], sizes: [], urls: {} } ) );
	}, [] );

	return data;
}

const BUILTIN = [
	{ value: 'title', label: __( 'Title / name', 'libreforge' ) },
	{ value: 'excerpt', label: __( 'Excerpt / description', 'libreforge' ) },
	{ value: 'content', label: __( 'Full content', 'libreforge' ) },
	{ value: 'date', label: __( 'Publication date', 'libreforge' ) },
	{ value: 'modified', label: __( 'Last update', 'libreforge' ) },
	{ value: 'author', label: __( 'Author name', 'libreforge' ) },
	{ value: 'permalink', label: __( 'Address (URL)', 'libreforge' ) },
	{ value: 'post_type', label: __( 'Content type', 'libreforge' ) },
	{ value: 'comment_count', label: __( 'Number of comments', 'libreforge' ) },
	{ value: 'count', label: __( 'Number of items (terms)', 'libreforge' ) },
	{ value: 'avatar_url', label: __( 'Avatar URL (users)', 'libreforge' ) },
	{ value: 'id', label: __( 'ID', 'libreforge' ) },
];

const SOURCES = [
	{ value: 'object', label: __( 'Item data (title, date…)', 'libreforge' ) },
	{ value: 'meta', label: __( 'Custom field', 'libreforge' ) },
	{ value: 'option', label: __( 'Options page', 'libreforge' ) },
	{ value: 'token', label: __( 'Text with tokens', 'libreforge' ) },
];

/** Fields that belong to what the template shows (or every field when unknown). */
function fieldsFor( data, types = null ) {
	const source = config.templateSource || '';
	const list = ( data?.fields || [] ).filter( ( item ) => ! source || item.where.includes( source.replace( /^term:/, '' ) ) || item.object === source.replace( /:.*/, '' ) );

	return ( list.length ? list : data?.fields || [] ).filter( ( item ) => ! types || types.includes( item.type ) );
}

/** Select of fields with an "other" option to type any name. */
function FieldKey( { label, value, onChange, options, help } ) {
	const known = options.some( ( option ) => option.value === value );
	const [ custom, setCustom ] = useState( value && ! known );

	return (
		<>
			<SelectControl
				label={ label }
				help={ help }
				value={ custom ? '__custom' : value }
				options={ [ { value: '', label: __( '— Choose —', 'libreforge' ) }, ...options, { value: '__custom', label: __( 'Other (type the name)…', 'libreforge' ) } ] }
				onChange={ ( next ) => {
					setCustom( next === '__custom' );
					if ( next !== '__custom' ) {
						onChange( next );
					}
				} }
				__nextHasNoMarginBottom
			/>
			{ custom && <TextControl label={ __( 'Field name', 'libreforge' ) } value={ value } onChange={ onChange } __nextHasNoMarginBottom /> }
		</>
	);
}

function Preview( { name, attributes } ) {
	return (
		<ServerSideRender
			block={ name }
			attributes={ attributes }
			skipBlockSupportAttributes
			LoadingResponsePlaceholder={ () => (
				<div className="lf-block-loading">
					<Spinner />
				</div>
			) }
			EmptyResponsePlaceholder={ () => <div className="lf-block-empty">{ __( 'Nothing to show for this item yet.', 'libreforge' ) }</div> }
		/>
	);
}

const fieldLabel = ( item ) => `${ item.label } (${ item.value })`;

/* -------------------------------------------------------------------------- loop grid */

function LoopGridEdit( { attributes, setAttributes, name } ) {
	const data = useForgeData();
	const props = useBlockProps();
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );
	const ready = attributes.queryId || attributes.postType;

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'What to list', 'libreforge' ) }>
					{ ! data ? (
						<Spinner />
					) : (
						<>
							<SelectControl
								label={ __( 'Saved query', 'libreforge' ) }
								help={ __( 'Built in LibreForge → Queries. Or leave empty and pick a post type below.', 'libreforge' ) }
								value={ String( attributes.queryId || '' ) }
								options={ [ { value: '', label: __( '— Simple mode (post type) —', 'libreforge' ) }, ...data.queries.map( ( query ) => ( { value: String( query.value ), label: query.label } ) ) ] }
								onChange={ ( value ) => setAttributes( { queryId: Number( value ) || 0 } ) }
								__nextHasNoMarginBottom
							/>
							{ ! attributes.queryId && (
								<>
									<SelectControl label={ __( 'Post type', 'libreforge' ) } value={ attributes.postType } options={ [ { value: '', label: __( '— Choose —', 'libreforge' ) }, ...data.post_types ] } onChange={ set( 'postType' ) } __nextHasNoMarginBottom />
									<RangeControl label={ __( 'How many', 'libreforge' ) } value={ attributes.perPage } onChange={ set( 'perPage' ) } min={ 1 } max={ 48 } __nextHasNoMarginBottom />
									<SelectControl
										label={ __( 'Sort by', 'libreforge' ) }
										value={ `${ attributes.orderby }:${ attributes.order }` }
										options={ [
											{ value: 'date:DESC', label: __( 'Newest first', 'libreforge' ) },
											{ value: 'date:ASC', label: __( 'Oldest first', 'libreforge' ) },
											{ value: 'title:ASC', label: __( 'Title A→Z', 'libreforge' ) },
											{ value: 'title:DESC', label: __( 'Title Z→A', 'libreforge' ) },
											{ value: 'menu_order:ASC', label: __( 'Manual order', 'libreforge' ) },
											{ value: 'rand:DESC', label: __( 'Random', 'libreforge' ) },
										] }
										onChange={ ( value ) => {
											const [ orderby, order ] = value.split( ':' );
											setAttributes( { orderby, order } );
										} }
										__nextHasNoMarginBottom
									/>
								</>
							) }
							<Button variant="link" href={ data.urls.queries } target="_blank">
								{ __( 'Create a query with filters', 'libreforge' ) }
							</Button>
						</>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Item design', 'libreforge' ) }>
					{ data && (
						<>
							<SelectControl
								label={ __( 'Item template', 'libreforge' ) }
								help={ __( 'The card repeated for every item. Without one, only the title is shown.', 'libreforge' ) }
								value={ String( attributes.templateId || '' ) }
								options={ [ { value: '', label: __( '— Only the title —', 'libreforge' ) }, ...data.templates.map( ( template ) => ( { value: String( template.value ), label: template.label } ) ) ] }
								onChange={ ( value ) => setAttributes( { templateId: Number( value ) || 0 } ) }
								__nextHasNoMarginBottom
							/>
							<div className="lf-block-links">
								{ attributes.templateId > 0 && (
									<Button variant="secondary" href={ data.templates.find( ( template ) => template.value === attributes.templateId )?.edit_url } target="_blank">
										{ __( 'Edit this template', 'libreforge' ) }
									</Button>
								) }
								<Button variant="link" href={ data.urls.templates } target="_blank">
									{ __( 'New template', 'libreforge' ) }
								</Button>
							</div>
						</>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Layout', 'libreforge' ) }>
					<RangeControl label={ __( 'Columns', 'libreforge' ) } value={ attributes.columns } onChange={ set( 'columns' ) } min={ 1 } max={ 6 } __nextHasNoMarginBottom />
					<RangeControl label={ __( 'Columns on tablets', 'libreforge' ) } value={ attributes.columnsTablet } onChange={ set( 'columnsTablet' ) } min={ 1 } max={ 6 } __nextHasNoMarginBottom />
					<RangeControl label={ __( 'Columns on phones', 'libreforge' ) } value={ attributes.columnsMobile } onChange={ set( 'columnsMobile' ) } min={ 1 } max={ 6 } __nextHasNoMarginBottom />
					<RangeControl label={ __( 'Space between items (px)', 'libreforge' ) } value={ attributes.gap } onChange={ set( 'gap' ) } min={ 0 } max={ 80 } __nextHasNoMarginBottom />
					<SelectControl
						label={ __( 'Pagination', 'libreforge' ) }
						value={ attributes.pagination }
						options={ [
							{ value: 'none', label: __( 'None', 'libreforge' ) },
							{ value: 'numbers', label: __( 'Page numbers', 'libreforge' ) },
							{ value: 'prev_next', label: __( 'Previous / next', 'libreforge' ) },
						] }
						onChange={ set( 'pagination' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl label={ __( 'Message when empty', 'libreforge' ) } value={ attributes.emptyText } placeholder={ __( 'Nothing found.', 'libreforge' ) } onChange={ set( 'emptyText' ) } __nextHasNoMarginBottom />
				</PanelBody>
			</InspectorControls>

			{ ready ? (
				<Preview name={ name } attributes={ attributes } />
			) : (
				<div className="lf-block-placeholder">
					<span className="dashicons dashicons-grid-view" aria-hidden="true" />
					<strong>{ __( 'Loop Grid', 'libreforge' ) }</strong>
					<p>{ __( 'Choose what to list in the sidebar: a saved query or a post type.', 'libreforge' ) }</p>
					{ data && (
						<SelectControl
							label={ __( 'Quick start: post type', 'libreforge' ) }
							value=""
							options={ [ { value: '', label: __( '— Choose —', 'libreforge' ) }, ...data.post_types ] }
							onChange={ set( 'postType' ) }
							__nextHasNoMarginBottom
						/>
					) }
				</div>
			) }
		</div>
	);
}

/* -------------------------------------------------------------------------- field */

function FieldEdit( { attributes, setAttributes, name } ) {
	const data = useForgeData();
	const props = useBlockProps();
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'What to show', 'libreforge' ) }>
					<SelectControl label={ __( 'Source', 'libreforge' ) } value={ attributes.source } options={ SOURCES } onChange={ ( source ) => setAttributes( { source, key: source === 'object' ? 'title' : '' } ) } __nextHasNoMarginBottom />
					{ attributes.source === 'object' && <SelectControl label={ __( 'Data', 'libreforge' ) } value={ attributes.key } options={ BUILTIN } onChange={ set( 'key' ) } __nextHasNoMarginBottom /> }
					{ attributes.source === 'meta' && data && <FieldKey label={ __( 'Custom field', 'libreforge' ) } value={ attributes.key } options={ fieldsFor( data ).map( ( item ) => ( { value: item.value, label: fieldLabel( item ) } ) ) } onChange={ set( 'key' ) } /> }
					{ attributes.source === 'option' && data && <FieldKey label={ __( 'Option', 'libreforge' ) } value={ attributes.key } options={ data.options } onChange={ set( 'key' ) } /> }
					{ attributes.source === 'token' && (
						<TextControl label={ __( 'Text', 'libreforge' ) } help={ __( 'Ex.: Hello {{user.meta|first_name}}!', 'libreforge' ) } value={ attributes.key } onChange={ set( 'key' ) } __nextHasNoMarginBottom />
					) }
				</PanelBody>
				<PanelBody title={ __( 'Format', 'libreforge' ) }>
					<SelectControl
						label={ __( 'Show as', 'libreforge' ) }
						value={ attributes.format }
						options={ [
							{ value: 'auto', label: __( 'Automatic (recommended)', 'libreforge' ) },
							{ value: 'text', label: __( 'Plain text', 'libreforge' ) },
							{ value: 'date', label: __( 'Date', 'libreforge' ) },
							{ value: 'number', label: __( 'Number', 'libreforge' ) },
							{ value: 'currency', label: __( 'Money', 'libreforge' ) },
							{ value: 'label', label: __( 'Option label', 'libreforge' ) },
							{ value: 'trim', label: __( 'Short text (limit words)', 'libreforge' ) },
							{ value: 'html', label: __( 'Formatted text (HTML)', 'libreforge' ) },
							{ value: 'image', label: __( 'Image', 'libreforge' ) },
						] }
						onChange={ set( 'format' ) }
						__nextHasNoMarginBottom
					/>
					{ attributes.format === 'date' && <TextControl label={ __( 'Date format', 'libreforge' ) } help={ __( 'Ex.: d/m/Y. Empty = site default.', 'libreforge' ) } value={ attributes.dateFormat } onChange={ set( 'dateFormat' ) } __nextHasNoMarginBottom /> }
					{ [ 'number', 'currency' ].includes( attributes.format ) && <RangeControl label={ __( 'Decimals', 'libreforge' ) } value={ attributes.decimals } onChange={ set( 'decimals' ) } min={ 0 } max={ 4 } __nextHasNoMarginBottom /> }
					{ attributes.format === 'currency' && <TextControl label={ __( 'Currency symbol', 'libreforge' ) } value={ attributes.currency } onChange={ set( 'currency' ) } __nextHasNoMarginBottom /> }
					{ attributes.format === 'trim' && <RangeControl label={ __( 'Words', 'libreforge' ) } value={ attributes.words } onChange={ set( 'words' ) } min={ 3 } max={ 100 } __nextHasNoMarginBottom /> }
					<SelectControl
						label={ __( 'HTML tag', 'libreforge' ) }
						value={ attributes.tag }
						options={ [ 'div', 'p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'small' ].map( ( tag ) => ( { value: tag, label: tag } ) ) }
						onChange={ set( 'tag' ) }
						__nextHasNoMarginBottom
					/>
					<TextControl label={ __( 'Text before', 'libreforge' ) } value={ attributes.before } placeholder={ __( 'Ex.: Price: ', 'libreforge' ) } onChange={ set( 'before' ) } __nextHasNoMarginBottom />
					<TextControl label={ __( 'Text after', 'libreforge' ) } value={ attributes.after } onChange={ set( 'after' ) } __nextHasNoMarginBottom />
					<TextControl label={ __( 'If empty, show', 'libreforge' ) } value={ attributes.fallback } onChange={ set( 'fallback' ) } __nextHasNoMarginBottom />
					<ToggleControl label={ __( 'Link to the item', 'libreforge' ) } checked={ attributes.link } onChange={ set( 'link' ) } __nextHasNoMarginBottom />
				</PanelBody>
			</InspectorControls>
			<Preview name={ name } attributes={ attributes } />
		</div>
	);
}

/* -------------------------------------------------------------------------- image */

function ImageEdit( { attributes, setAttributes, name } ) {
	const data = useForgeData();
	const props = useBlockProps();
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'Image', 'libreforge' ) }>
					<SelectControl
						label={ __( 'Source', 'libreforge' ) }
						value={ attributes.source }
						options={ [
							{ value: 'featured', label: __( 'Featured image', 'libreforge' ) },
							{ value: 'meta', label: __( 'Image field', 'libreforge' ) },
							{ value: 'option', label: __( 'Options page', 'libreforge' ) },
						] }
						onChange={ set( 'source' ) }
						__nextHasNoMarginBottom
					/>
					{ attributes.source === 'meta' && data && <FieldKey label={ __( 'Image field', 'libreforge' ) } value={ attributes.key } options={ fieldsFor( data, [ 'media', 'gallery' ] ).map( ( item ) => ( { value: item.value, label: fieldLabel( item ) } ) ) } onChange={ set( 'key' ) } /> }
					{ attributes.source === 'option' && data && <FieldKey label={ __( 'Option', 'libreforge' ) } value={ attributes.key } options={ data.options.filter( ( item ) => [ 'media', 'gallery' ].includes( item.type ) ) } onChange={ set( 'key' ) } /> }
					{ data && <SelectControl label={ __( 'Size', 'libreforge' ) } value={ attributes.size } options={ data.sizes } onChange={ set( 'size' ) } __nextHasNoMarginBottom /> }
					<SelectControl
						label={ __( 'Link', 'libreforge' ) }
						value={ attributes.link }
						options={ [
							{ value: 'none', label: __( 'No link', 'libreforge' ) },
							{ value: 'item', label: __( 'To the item', 'libreforge' ) },
							{ value: 'file', label: __( 'To the image file', 'libreforge' ) },
						] }
						onChange={ set( 'link' ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<Preview name={ name } attributes={ attributes } />
		</div>
	);
}

/* -------------------------------------------------------------------------- link */

function LinkEdit( { attributes, setAttributes, name } ) {
	const data = useForgeData();
	const props = useBlockProps();
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'Link', 'libreforge' ) }>
					<TextControl label={ __( 'Text', 'libreforge' ) } help={ __( 'Tokens allowed, e.g. See {{object.prop|post_title}}', 'libreforge' ) } value={ attributes.text } placeholder={ __( 'See more', 'libreforge' ) } onChange={ set( 'text' ) } __nextHasNoMarginBottom />
					<SelectControl
						label={ __( 'Goes to', 'libreforge' ) }
						value={ attributes.urlSource }
						options={ [
							{ value: 'item', label: __( 'The item page', 'libreforge' ) },
							{ value: 'meta', label: __( 'A field (URL, e-mail or phone)', 'libreforge' ) },
							{ value: 'option', label: __( 'An options page field', 'libreforge' ) },
							{ value: 'custom', label: __( 'A custom address', 'libreforge' ) },
						] }
						onChange={ set( 'urlSource' ) }
						__nextHasNoMarginBottom
					/>
					{ attributes.urlSource === 'meta' && data && <FieldKey label={ __( 'Field', 'libreforge' ) } value={ attributes.urlKey } options={ fieldsFor( data ).map( ( item ) => ( { value: item.value, label: fieldLabel( item ) } ) ) } onChange={ set( 'urlKey' ) } /> }
					{ attributes.urlSource === 'option' && data && <FieldKey label={ __( 'Option', 'libreforge' ) } value={ attributes.urlKey } options={ data.options } onChange={ set( 'urlKey' ) } /> }
					{ attributes.urlSource === 'custom' && <TextControl label={ __( 'Address', 'libreforge' ) } value={ attributes.urlKey } placeholder="https://wa.me/55{{object.meta|whatsapp}}" onChange={ set( 'urlKey' ) } __nextHasNoMarginBottom /> }
					<SelectControl
						label={ __( 'Style', 'libreforge' ) }
						value={ attributes.style }
						options={ [
							{ value: 'button', label: __( 'Button', 'libreforge' ) },
							{ value: 'link', label: __( 'Text link', 'libreforge' ) },
						] }
						onChange={ set( 'style' ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl label={ __( 'Open in a new tab', 'libreforge' ) } checked={ attributes.newTab } onChange={ set( 'newTab' ) } __nextHasNoMarginBottom />
				</PanelBody>
			</InspectorControls>
			<Preview name={ name } attributes={ attributes } />
		</div>
	);
}

/* -------------------------------------------------------------------------- terms */

function TermsEdit( { attributes, setAttributes, name } ) {
	const data = useForgeData();
	const props = useBlockProps();
	const set = ( key ) => ( value ) => setAttributes( { [ key ]: value } );

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'Terms', 'libreforge' ) }>
					{ data && <SelectControl label={ __( 'Taxonomy', 'libreforge' ) } value={ attributes.taxonomy } options={ data.taxonomies } onChange={ set( 'taxonomy' ) } __nextHasNoMarginBottom /> }
					<SelectControl
						label={ __( 'Style', 'libreforge' ) }
						value={ attributes.style }
						options={ [
							{ value: 'text', label: __( 'Text with separator', 'libreforge' ) },
							{ value: 'badges', label: __( 'Badges', 'libreforge' ) },
						] }
						onChange={ set( 'style' ) }
						__nextHasNoMarginBottom
					/>
					{ attributes.style === 'text' && <TextControl label={ __( 'Separator', 'libreforge' ) } value={ attributes.separator } onChange={ set( 'separator' ) } __nextHasNoMarginBottom /> }
					<RangeControl label={ __( 'Maximum (0 = all)', 'libreforge' ) } value={ attributes.limit } onChange={ set( 'limit' ) } min={ 0 } max={ 20 } __nextHasNoMarginBottom />
					<ToggleControl label={ __( 'Link each term', 'libreforge' ) } checked={ attributes.link } onChange={ set( 'link' ) } __nextHasNoMarginBottom />
				</PanelBody>
			</InspectorControls>
			<Preview name={ name } attributes={ attributes } />
		</div>
	);
}

/* -------------------------------------------------------------------------- repeater */

function RepeaterEdit( { attributes, setAttributes } ) {
	const data = useForgeData();
	const props = useBlockProps( { className: 'lf-block-repeater' } );
	const repeaters = data ? fieldsFor( data, [ 'repeater' ] ) : [];
	const chosen = repeaters.find( ( item ) => item.value === attributes.key );
	const firstChild = chosen?.children?.[ 0 ]?.value || '';

	return (
		<div { ...props }>
			<InspectorControls>
				<PanelBody title={ __( 'Repeater', 'libreforge' ) }>
					{ data && (
						<FieldKey
							label={ __( 'Repeater field', 'libreforge' ) }
							help={ __( 'The blocks inside are repeated for each row. Use Dynamic Field blocks with "Custom field" and the sub-field name.', 'libreforge' ) }
							value={ attributes.key }
							options={ repeaters.map( ( item ) => ( { value: item.value, label: fieldLabel( item ) } ) ) }
							onChange={ ( key ) => setAttributes( { key } ) }
						/>
					) }
					{ chosen?.children?.length > 0 && (
						<Notice status="info" isDismissible={ false }>
							{ sprintf( /* translators: %s: sub-field names */ __( 'Sub-fields: %s', 'libreforge' ), chosen.children.map( ( child ) => child.value ).join( ', ' ) ) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>
			<div className="lf-block-repeater__label">
				<span className="dashicons dashicons-controls-repeat" aria-hidden="true" />
				{ attributes.key ? sprintf( /* translators: %s: field name */ __( 'Repeated for each row of "%s"', 'libreforge' ), attributes.key ) : __( 'Choose the repeater field in the sidebar', 'libreforge' ) }
			</div>
			<InnerBlocks
				template={ [ [ 'libreforge/field', { source: 'meta', key: firstChild, tag: 'p' } ] ] }
				templateLock={ false }
			/>
		</div>
	);
}

/* -------------------------------------------------------------------------- register */

const server = () => null;

registerBlockType( loopGrid.name, { ...loopGrid, edit: LoopGridEdit, save: server } );
registerBlockType( field.name, { ...field, edit: FieldEdit, save: server } );
registerBlockType( image.name, { ...image, edit: ImageEdit, save: server } );
registerBlockType( link.name, { ...link, edit: LinkEdit, save: server } );
registerBlockType( terms.name, { ...terms, edit: TermsEdit, save: server } );
registerBlockType( repeater.name, { ...repeater, edit: RepeaterEdit, save: () => <InnerBlocks.Content /> } );
