import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const BASE = '/libreblocks/v1/forge';

export const listDefinitions = ( kind ) => apiFetch( { path: addQueryArgs( `${ BASE }/definitions`, { kind } ) } );

export const getDefinition = ( id ) => apiFetch( { path: `${ BASE }/definitions/${ id }` } );

export const saveDefinition = ( { id, ...data } ) =>
	id
		? apiFetch( { path: `${ BASE }/definitions/${ id }`, method: 'PUT', data } )
		: apiFetch( { path: `${ BASE }/definitions`, method: 'POST', data } );

export const deleteDefinition = ( id ) => apiFetch( { path: `${ BASE }/definitions/${ id }`, method: 'DELETE' } );

let environment;

/** Post types, taxonomies, roles, glossaries and field types (cached per page load). */
export const getEnvironment = () => {
	environment = environment || apiFetch( { path: `${ BASE }/environment` } );
	return environment;
};

export const slugify = ( text, separator = '_' ) =>
	String( text || '' )
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, separator )
		.replace( new RegExp( `^\\${ separator }+|\\${ separator }+$`, 'g' ), '' );
