/**
 * Visual editor for a list of field configs (post types, taxonomies, field
 * groups, options pages, and the children of repeater/group fields).
 *
 * Each field is a collapsible card; inside, every setting is a row with its
 * name and a plain-language explanation on the left and the control on the right.
 */
import { Button, SelectControl, TextareaControl, TextControl, ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { Icon, SettingRow } from '@libreblocks/ui';

import { slugify } from './api';
import { CheckboxList, OptionsEditor } from './components';

const CHOICE_TYPES = [ 'select', 'radio', 'checkbox' ];
const LAYOUT_TYPES = [ 'html', 'tab' ];

const CATEGORY_LABELS = {
	basic: __( 'Basic', 'libreforge' ),
	choice: __( 'Choice', 'libreforge' ),
	media: __( 'Media', 'libreforge' ),
	relational: __( 'Relational', 'libreforge' ),
	structure: __( 'Structure', 'libreforge' ),
	layout: __( 'Layout', 'libreforge' ),
};

const OPERATORS = [
	{ value: '=', label: __( 'is', 'libreforge' ) },
	{ value: '!=', label: __( 'is not', 'libreforge' ) },
	{ value: 'in', label: __( 'is one of (comma separated)', 'libreforge' ) },
	{ value: 'not_in', label: __( 'is none of (comma separated)', 'libreforge' ) },
	{ value: 'contains', label: __( 'contains', 'libreforge' ) },
	{ value: 'empty', label: __( 'is empty', 'libreforge' ) },
	{ value: 'not_empty', label: __( 'is not empty', 'libreforge' ) },
	{ value: '>', label: __( 'greater than', 'libreforge' ) },
	{ value: '<', label: __( 'less than', 'libreforge' ) },
];

const WIDTHS = [
	{ value: '100%', label: __( 'Full width (100%)', 'libreforge' ) },
	{ value: '75%', label: '75%' },
	{ value: '66%', label: __( 'Two thirds (66%)', 'libreforge' ) },
	{ value: '50%', label: __( 'Half (50%)', 'libreforge' ) },
	{ value: '33%', label: __( 'One third (33%)', 'libreforge' ) },
	{ value: '25%', label: '25%' },
];

const TYPE_ICONS = {
	text: 'editor-textcolor',
	textarea: 'editor-paragraph',
	wysiwyg: 'editor-kitchensink',
	number: 'calculator',
	email: 'email',
	url: 'admin-links',
	date: 'calendar-alt',
	time: 'clock',
	datetime: 'calendar',
	select: 'menu-alt',
	radio: 'marker',
	checkbox: 'yes-alt',
	switch: 'controls-pause',
	color: 'art',
	media: 'format-image',
	gallery: 'format-gallery',
	post_picker: 'admin-post',
	term_picker: 'tag',
	user_picker: 'admin-users',
	icon: 'star-filled',
	repeater: 'controls-repeat',
	group: 'category',
	html: 'html',
	tab: 'index-card',
};

function typeOptions( types ) {
	const groups = {};

	types.forEach( ( type ) => {
		( groups[ type.category ] = groups[ type.category ] || [] ).push( type );
	} );

	return Object.entries( groups ).flatMap( ( [ category, items ] ) => [
		{ value: `__${ category }`, label: `— ${ CATEGORY_LABELS[ category ] || category } —`, disabled: true },
		...items.map( ( type ) => ( { value: type.type, label: type.label } ) ),
	] );
}

/* -------------------------------------------------------------------------- row helpers */

function Text( { label, help, value, onChange, placeholder, type = 'text', mono = false } ) {
	return (
		<SettingRow label={ label } help={ help }>
			<TextControl label={ label } hideLabelFromVision type={ type } value={ value ?? '' } placeholder={ placeholder } onChange={ onChange } className={ mono ? 'lf-mono' : undefined } __nextHasNoMarginBottom />
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

const toNumber = ( value ) => ( value === '' ? null : Number( value ) );

/* -------------------------------------------------------------------------- conditions */

function Conditions( { field, siblings, onChange } ) {
	const conditions = field.conditions?.rules ? field.conditions : { relation: 'AND', rules: [] };
	const candidates = siblings.filter( ( sibling ) => sibling.name && sibling.name !== field.name && ! LAYOUT_TYPES.includes( sibling.type ) );

	const setRules = ( rules ) => onChange( rules.length ? { ...conditions, rules } : [] );
	const updateRule = ( index, patch ) => setRules( conditions.rules.map( ( rule, i ) => ( i === index ? { ...rule, ...patch } : rule ) ) );

	if ( ! candidates.length ) {
		return <p className="lf-muted">{ __( 'Add other fields to show this one conditionally.', 'libreforge' ) }</p>;
	}

	return (
		<div className="lf-conditions">
			{ conditions.rules.length > 1 && (
				<SelectControl
					label={ __( 'Show when', 'libreforge' ) }
					value={ conditions.relation }
					options={ [
						{ value: 'AND', label: __( 'all rules match', 'libreforge' ) },
						{ value: 'OR', label: __( 'any rule matches', 'libreforge' ) },
					] }
					onChange={ ( relation ) => onChange( { ...conditions, relation } ) }
					__nextHasNoMarginBottom
				/>
			) }
			{ conditions.rules.map( ( rule, index ) => (
				<div className="lf-conditions__rule" key={ index }>
					<SelectControl
						label={ __( 'Field', 'libreforge' ) }
						hideLabelFromVision
						value={ rule.field }
						options={ candidates.map( ( sibling ) => ( { value: sibling.name, label: sibling.label || sibling.name } ) ) }
						onChange={ ( name ) => updateRule( index, { field: name } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl label={ __( 'Condition', 'libreforge' ) } hideLabelFromVision value={ rule.operator } options={ OPERATORS } onChange={ ( operator ) => updateRule( index, { operator } ) } __nextHasNoMarginBottom />
					{ ! [ 'empty', 'not_empty' ].includes( rule.operator ) && (
						<TextControl label={ __( 'Value', 'libreforge' ) } hideLabelFromVision value={ rule.value ?? '' } onChange={ ( value ) => updateRule( index, { value } ) } __nextHasNoMarginBottom />
					) }
					<Button icon="trash" isDestructive label={ __( 'Remove rule', 'libreforge' ) } onClick={ () => setRules( conditions.rules.filter( ( _, i ) => i !== index ) ) } />
				</div>
			) ) }
			<Button variant="secondary" size="small" onClick={ () => setRules( [ ...conditions.rules, { field: candidates[ 0 ].name, operator: '=', value: '' } ] ) }>
				{ conditions.rules.length ? __( 'Add rule', 'libreforge' ) : __( 'Show only when…', 'libreforge' ) }
			</Button>
		</div>
	);
}

/* -------------------------------------------------------------------------- type-specific rows */

function TypeSettings( { field, set, environment } ) {
	switch ( field.type ) {
		case 'text':
		case 'textarea':
			return (
				<>
					<Text label={ __( 'Placeholder', 'libreforge' ) } help={ __( 'Grey example text inside the empty box.', 'libreforge' ) } value={ field.placeholder } onChange={ ( placeholder ) => set( { placeholder } ) } />
					<Text label={ __( 'Character limit', 'libreforge' ) } help={ __( 'Maximum length. Leave empty for no limit.', 'libreforge' ) } type="number" value={ field.max_length ?? '' } onChange={ ( value ) => set( { max_length: toNumber( value ) } ) } />
				</>
			);
		case 'number':
			return (
				<>
					<Text label={ __( 'Minimum', 'libreforge' ) } type="number" value={ field.min ?? '' } onChange={ ( value ) => set( { min: toNumber( value ) } ) } />
					<Text label={ __( 'Maximum', 'libreforge' ) } type="number" value={ field.max ?? '' } onChange={ ( value ) => set( { max: toNumber( value ) } ) } />
					<Text label={ __( 'Step', 'libreforge' ) } help={ __( '1 = whole numbers. 0.01 = allows cents.', 'libreforge' ) } type="number" value={ field.step ?? 1 } onChange={ ( value ) => set( { step: toNumber( value ) ?? 1 } ) } />
				</>
			);
		case 'date':
		case 'datetime':
			return (
				<Choose
					label={ __( 'Save as', 'libreforge' ) }
					help={ __( 'Keep "text" unless a developer asks for timestamps.', 'libreforge' ) }
					value={ field.store_as || 'string' }
					options={ [
						{ value: 'string', label: __( 'Text (recommended for sorting and filters)', 'libreforge' ) },
						{ value: 'timestamp', label: __( 'Unix timestamp', 'libreforge' ) },
					] }
					onChange={ ( store_as ) => set( { store_as } ) }
				/>
			);
		case 'switch':
			return <Text label={ __( 'Text next to the switch', 'libreforge' ) } help={ __( 'Ex.: Accepts pets', 'libreforge' ) } value={ field.toggle_label } onChange={ ( toggle_label ) => set( { toggle_label } ) } />;
		case 'media':
			return (
				<Choose
					label={ __( 'Allowed files', 'libreforge' ) }
					value={ field.mime_type || '' }
					options={ [
						{ value: '', label: __( 'Any file', 'libreforge' ) },
						{ value: 'image', label: __( 'Images', 'libreforge' ) },
						{ value: 'video', label: __( 'Videos', 'libreforge' ) },
						{ value: 'audio', label: __( 'Audio', 'libreforge' ) },
						{ value: 'application/pdf', label: 'PDF' },
					] }
					onChange={ ( mime_type ) => set( { mime_type } ) }
				/>
			);
		case 'post_picker':
		case 'term_picker':
		case 'user_picker': {
			const [ key, options ] = { post_picker: [ 'post_type', environment.post_types ], term_picker: [ 'taxonomy', environment.taxonomies ], user_picker: [ 'role', environment.roles ] }[ field.type ];

			return (
				<>
					<Toggle label={ __( 'Allow choosing more than one', 'libreforge' ) } checked={ field.multiple } onChange={ ( multiple ) => set( { multiple } ) } />
					<SettingRow label={ __( 'Limit to', 'libreforge' ) } help={ __( 'Leave empty to allow all.', 'libreforge' ) }>
						<CheckboxList options={ options } value={ [].concat( field[ key ] || [] ) } onChange={ ( value ) => set( { [ key ]: value } ) } />
					</SettingRow>
				</>
			);
		}
		case 'repeater':
			return (
				<>
					<Text label={ __( 'Minimum items', 'libreforge' ) } type="number" value={ field.min_rows || '' } onChange={ ( value ) => set( { min_rows: toNumber( value ) || 0 } ) } />
					<Text label={ __( 'Maximum items', 'libreforge' ) } help={ __( 'Leave empty for no limit.', 'libreforge' ) } type="number" value={ field.max_rows || '' } onChange={ ( value ) => set( { max_rows: toNumber( value ) || 0 } ) } />
					<Text label={ __( '"Add" button text', 'libreforge' ) } help={ __( 'Ex.: Add room', 'libreforge' ) } value={ field.add_label } onChange={ ( add_label ) => set( { add_label } ) } />
				</>
			);
		case 'html':
			return (
				<SettingRow label={ __( 'Content (HTML)', 'libreforge' ) } help={ __( 'A fixed text or notice shown between the fields.', 'libreforge' ) }>
					<TextareaControl label={ __( 'Content (HTML)', 'libreforge' ) } hideLabelFromVision value={ field.content || '' } onChange={ ( content ) => set( { content } ) } rows={ 4 } __nextHasNoMarginBottom />
				</SettingRow>
			);
		default:
			return null;
	}
}

/* -------------------------------------------------------------------------- one field */

function FieldEditor( { field, siblings, onChange, environment, errorPrefix, errors, topLevel } ) {
	const [ nameTouched, setNameTouched ] = useState( Boolean( field.name ) );
	const set = ( patch ) => onChange( { ...field, ...patch } );
	const isLayout = LAYOUT_TYPES.includes( field.type );
	const isChoice = CHOICE_TYPES.includes( field.type );
	const hasChildren = [ 'repeater', 'group' ].includes( field.type );

	return (
		<div className="lf-field-editor">
			<Text
				label={ __( 'Label', 'libreforge' ) }
				help={ __( 'What people see next to the field. Ex.: Price', 'libreforge' ) }
				value={ field.label }
				onChange={ ( label ) => set( nameTouched || isLayout ? { label } : { label, name: slugify( label ) } ) }
			/>
			{ ! isLayout && (
				<Text
					label={ __( 'Name / ID', 'libreforge' ) }
					help={ __( 'How the value is saved. Filled in from the label. Only lowercase letters, numbers and _.', 'libreforge' ) }
					value={ field.name }
					mono
					onChange={ ( name ) => {
						setNameTouched( true );
						set( { name: slugify( name ) } );
					} }
				/>
			) }
			<Choose label={ __( 'Field type', 'libreforge' ) } help={ __( 'The kind of information: text, number, date, image…', 'libreforge' ) } value={ field.type } options={ typeOptions( environment.field_types ) } onChange={ ( type ) => set( { type } ) } />

			{ isChoice && (
				<>
					<Choose
						label={ __( 'Options come from', 'libreforge' ) }
						help={ __( 'Type the options here or reuse a glossary.', 'libreforge' ) }
						value={ field.glossary || '' }
						options={ [ { value: '', label: __( 'The list below', 'libreforge' ) }, ...environment.glossaries.map( ( glossary ) => ( { value: glossary.value, label: sprintf( /* translators: %s: glossary name */ __( 'Glossary: %s', 'libreforge' ), glossary.label ) } ) ) ] }
						onChange={ ( glossary ) => set( { glossary } ) }
					/>
					{ ! field.glossary && (
						<SettingRow label={ __( 'Options', 'libreforge' ) } help={ __( 'The "value" is saved; the "label" is shown.', 'libreforge' ) } stacked>
							<OptionsEditor value={ field.options || [] } onChange={ ( options ) => set( { options } ) } />
						</SettingRow>
					) }
					{ field.type === 'select' && <Toggle label={ __( 'Allow choosing more than one', 'libreforge' ) } checked={ field.multiple } onChange={ ( multiple ) => set( { multiple } ) } /> }
				</>
			) }

			<TypeSettings field={ field } set={ set } environment={ environment } />

			{ ! isLayout && (
				<>
					<SettingRow label={ __( 'Description', 'libreforge' ) } help={ __( 'Small help text shown under the field.', 'libreforge' ) }>
						<TextareaControl label={ __( 'Description', 'libreforge' ) } hideLabelFromVision value={ field.description || '' } onChange={ ( description ) => set( { description } ) } rows={ 2 } __nextHasNoMarginBottom />
					</SettingRow>
					{ ! hasChildren && field.type !== 'gallery' && (
						<Text label={ __( 'Default value', 'libreforge' ) } help={ __( 'Value filled in automatically on new items.', 'libreforge' ) } value={ field.default ?? '' } onChange={ ( value ) => set( { default: value === '' ? null : value } ) } />
					) }
					<Toggle label={ __( 'Required', 'libreforge' ) } help={ __( 'The field cannot be left empty.', 'libreforge' ) } checked={ field.required } onChange={ ( required ) => set( { required } ) } />
				</>
			) }

			{ field.type !== 'tab' && <Choose label={ __( 'Field width', 'libreforge' ) } help={ __( 'Two fields of 50% sit side by side.', 'libreforge' ) } value={ field.width || '100%' } options={ WIDTHS } onChange={ ( width ) => set( { width } ) } /> }

			{ ! isLayout && topLevel && (
				<>
					<Toggle label={ __( 'Show as a column in the admin list', 'libreforge' ) } help={ __( 'Only on post type lists.', 'libreforge' ) } checked={ field.admin_column } onChange={ ( admin_column ) => set( { admin_column } ) } />
					<Toggle label={ __( 'Available in the REST API', 'libreforge' ) } help={ __( 'Lets apps and the block editor read and save this field.', 'libreforge' ) } checked={ field.show_in_rest !== false } onChange={ ( show_in_rest ) => set( { show_in_rest } ) } />
				</>
			) }

			{ field.type !== 'tab' && (
				<SettingRow label={ __( 'Conditional logic', 'libreforge' ) } help={ __( 'Show this field only when another field has a certain value.', 'libreforge' ) }>
					<Conditions field={ field } siblings={ siblings } onChange={ ( conditions ) => set( { conditions } ) } />
				</SettingRow>
			) }

			{ hasChildren && (
				<div className="lf-children">
					<p className="lf-children__title">{ field.type === 'repeater' ? __( 'Fields of each item', 'libreforge' ) : __( 'Fields of the group', 'libreforge' ) }</p>
					<FieldBuilder fields={ field.fields || [] } onChange={ ( fields ) => set( { fields } ) } environment={ environment } errors={ errors } errorPrefix={ `${ errorPrefix }.fields` } />
				</div>
			) }
		</div>
	);
}

/* -------------------------------------------------------------------------- list of fields */

export default function FieldBuilder( { fields, onChange, environment, errors = {}, errorPrefix = 'fields' } ) {
	const [ open, setOpen ] = useState( null );
	const topLevel = errorPrefix === 'fields';

	const update = ( index, field ) => onChange( fields.map( ( current, i ) => ( i === index ? field : current ) ) );
	const remove = ( index ) => {
		// eslint-disable-next-line no-alert
		if ( ! fields[ index ].label || window.confirm( __( 'Delete this field? Values already saved stay in the database.', 'libreforge' ) ) ) {
			onChange( fields.filter( ( _, i ) => i !== index ) );
			setOpen( null );
		}
	};
	const move = ( index, offset ) => {
		const target = index + offset;

		if ( target < 0 || target >= fields.length ) {
			return;
		}

		const next = [ ...fields ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		onChange( next );
		setOpen( open === index ? target : open );
	};
	const duplicate = ( index ) => {
		const copy = JSON.parse( JSON.stringify( fields[ index ] ) );
		const taken = fields.map( ( item ) => item.name );
		let suffix = 2;

		while ( copy.name && taken.includes( `${ fields[ index ].name }_${ suffix }` ) ) {
			suffix++;
		}

		copy.name = copy.name ? `${ fields[ index ].name }_${ suffix }` : '';
		/* translators: %s: field label */
		copy.label = copy.label ? sprintf( __( '%s (copy)', 'libreforge' ), copy.label ) : '';
		onChange( [ ...fields.slice( 0, index + 1 ), copy, ...fields.slice( index + 1 ) ] );
		setOpen( index + 1 );
	};
	const add = () => {
		onChange( [ ...fields, { label: '', name: '', type: 'text', required: false, options: [], fields: [], width: '100%', conditions: [] } ] );
		setOpen( fields.length );
	};
	const typeLabel = ( type ) => environment.field_types.find( ( item ) => item.type === type )?.label || type;

	return (
		<div className={ `lf-builder${ topLevel ? '' : ' lf-builder--nested' }` }>
			{ fields.length === 0 && (
				<div className="lf-builder__empty">
					<Icon name="forms" />
					<p>{ __( 'No fields yet. Click "New field" to add the first one.', 'libreforge' ) }</p>
				</div>
			) }

			{ fields.map( ( field, index ) => {
				const error = errors[ `${ errorPrefix }.${ index }` ];
				const isOpen = open === index;

				return (
					<div className={ `lf-builder__item${ isOpen ? ' is-open' : '' }${ error ? ' has-error' : '' }` } key={ index }>
						<div className="lf-builder__head">
							<button type="button" className="lf-builder__title" onClick={ () => setOpen( isOpen ? null : index ) } aria-expanded={ isOpen }>
								<Icon name={ isOpen ? 'arrow-down-alt2' : 'arrow-right-alt2' } className="lf-builder__chevron" />
								<span className="lf-builder__type-icon">
									<Icon name={ TYPE_ICONS[ field.type ] || 'admin-generic' } />
								</span>
								<strong>{ field.label || __( '(no label)', 'libreforge' ) }</strong>
								{ field.name && <code>{ field.name }</code> }
								<span className="lf-badge">{ typeLabel( field.type ) }</span>
								{ field.required && <span className="lf-badge lf-badge--warn">{ __( 'required', 'libreforge' ) }</span> }
							</button>
							<Button icon="arrow-up-alt2" size="small" label={ __( 'Move up', 'libreforge' ) } disabled={ index === 0 } onClick={ () => move( index, -1 ) } />
							<Button icon="arrow-down-alt2" size="small" label={ __( 'Move down', 'libreforge' ) } disabled={ index === fields.length - 1 } onClick={ () => move( index, 1 ) } />
							<Button icon="admin-page" size="small" label={ __( 'Duplicate field', 'libreforge' ) } onClick={ () => duplicate( index ) } />
							<Button icon="trash" size="small" isDestructive label={ __( 'Delete field', 'libreforge' ) } onClick={ () => remove( index ) } />
						</div>
						{ error && <p className="lf-row__error lf-builder__error">{ error }</p> }
						{ isOpen && (
							<FieldEditor field={ field } siblings={ fields } onChange={ ( next ) => update( index, next ) } environment={ environment } errors={ errors } errorPrefix={ `${ errorPrefix }.${ index }` } topLevel={ topLevel } />
						) }
					</div>
				);
			} ) }

			<Button variant={ topLevel ? 'primary' : 'secondary' } icon="plus-alt2" className="lf-builder__add" onClick={ add }>
				{ topLevel ? __( 'New field', 'libreforge' ) : __( 'New sub-field', 'libreforge' ) }
			</Button>
		</div>
	);
}
