import {
	Button,
	CheckboxControl,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export interface SettingsData {
	enabled: boolean;
	error: string | false;
	warnings: string[];
	source: string | false;
	roles: Record< string, string >;
	postTypes: Record< string, string >;
	version: string;
}

export type RulesRequest = ( options: { path: string } ) => Promise< unknown >;

export function SettingsApp( {
	settings,
	request,
}: {
	settings: SettingsData;
	request: RulesRequest;
} ) {
	const [ enabled, setEnabled ] = useState( settings.enabled );
	const [ role, setRole ] = useState( '' );
	const [ postType, setPostType ] = useState( '' );
	const [ preview, setPreview ] = useState< { loading: boolean; value: string; error: boolean } >( {
		loading: false,
		value: '',
		error: false,
	} );
	const latestRequestRef = useRef( 0 );

	useEffect(
		() => () => {
			latestRequestRef.current++;
		},
		[]
	);

	function changeSelection( update: ( value: string ) => void, value: string ) {
		latestRequestRef.current++;
		update( value );
		setPreview( { loading: false, value: '', error: false } );
	}

	async function viewRules() {
		const requestId = ++latestRequestRef.current;
		setPreview( { loading: true, value: '', error: false } );
		const query = new URLSearchParams();
		if ( role ) {
			query.set( 'role', role );
		}
		if ( postType ) {
			query.set( 'postType', postType );
		}
		let value: string;
		let error = false;
		try {
			value = JSON.stringify(
				await request( { path: `/vip-governance/v1/rules?${ query }` } ),
				null,
				4
			);
		} catch ( failure ) {
			error = true;
			value =
				typeof failure === 'object' &&
				failure !== null &&
				'message' in failure &&
				typeof failure.message === 'string' &&
				failure.message
					? failure.message
					: __( 'Unable to load governance rules.', 'vip-governance' );
		}
		if ( requestId === latestRequestRef.current ) {
			setPreview( { loading: false, value, error } );
		}
	}

	const options = ( labels: Record< string, string > ) =>
		Object.entries( labels ).map( ( [ value, label ] ) => ( { value, label } ) );

	let validationStatus: 'error' | 'warning' | 'success' = 'success';
	if ( settings.warnings.length ) {
		validationStatus = 'warning';
	}
	if ( settings.error ) {
		validationStatus = 'error';
	}
	let announcement = '';
	if ( preview.loading ) {
		announcement = __( 'Loading governance rules…', 'vip-governance' );
	} else if ( preview.value && ! preview.error ) {
		announcement = __( 'Governance rules loaded.', 'vip-governance' );
	}

	return (
		<div className="vip-governance-settings">
			<PanelBody title={ __( 'Plugin Settings', 'vip-governance' ) }>
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __( 'Enable governance', 'vip-governance' ) }
					help={ __( 'Enable block editor governance rules for all users.', 'vip-governance' ) }
					checked={ enabled }
					onChange={ setEnabled }
					name="vip-governance[is-enabled]"
					value="yes"
				/>
				<Button variant="primary" type="submit">
					{ __( 'Save Changes', 'vip-governance' ) }
				</Button>
			</PanelBody>
			<PanelBody title={ __( 'Governance Rules Validation', 'vip-governance' ) }>
				<Notice status={ validationStatus } isDismissible={ false }>
					{ settings.error ||
						( settings.warnings.length
							? __( 'Rules loaded with warnings:', 'vip-governance' )
							: __( 'Rules loaded successfully.', 'vip-governance' ) ) }
					{ settings.warnings.map( ( warning, index ) => (
						<p key={ index }>{ warning }</p>
					) ) }
				</Notice>
				{ settings.source && (
					<details open={ Boolean( settings.error ) }>
						<summary>{ __( 'Click to expand governance rules', 'vip-governance' ) }</summary>
						<pre tabIndex={ 0 }>{ settings.source }</pre>
					</details>
				) }
			</PanelBody>
			{ ! settings.error && (
				<PanelBody title={ __( 'View Governance Rules For A Rule Type', 'vip-governance' ) }>
					<p>{ __( 'Preview the combined rules for a role and post type.', 'vip-governance' ) }</p>
					<div className="vip-governance-rule-filters">
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'User role', 'vip-governance' ) }
							value={ role }
							options={ options( settings.roles ) }
							onChange={ value => changeSelection( setRole, value ) }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Post type', 'vip-governance' ) }
							value={ postType }
							options={ options( settings.postTypes ) }
							onChange={ value => changeSelection( setPostType, value ) }
						/>
						{ ( role || postType ) && (
							<Button variant="secondary" type="button" onClick={ () => void viewRules() }>
								{ __( 'View Rules', 'vip-governance' ) }
							</Button>
						) }
						{ preview.loading && <Spinner /> }
					</div>
					<div role="status" className="screen-reader-text">
						{ announcement }
					</div>
					{ preview.error && (
						<Notice status="error" isDismissible={ false }>
							{ preview.value }
						</Notice>
					) }
					{ preview.value && ! preview.error && (
						<pre id="json" tabIndex={ 0 }>
							{ preview.value }
						</pre>
					) }
				</PanelBody>
			) }
			<p>
				{ __( 'Plugin Version:', 'vip-governance' ) } { settings.version }
			</p>
		</div>
	);
}
