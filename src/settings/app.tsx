import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

export interface SettingsData {
	enabled: boolean;
	error: string | false;
	warnings: string[];
	source: string | false;
	roles: Record< string, string >;
	postTypes: Record< string, string >;
	version: string;
}

type PreviewResult = { value: string; error: boolean };

export function SettingsApp( settings: SettingsData ) {
	const [ enabled, setEnabled ] = useState( settings.enabled );
	const [ role, setRole ] = useState( '' );
	const [ postType, setPostType ] = useState( '' );
	const [ preview, setPreview ] = useState< PreviewResult | 'loading' | null >( null );
	const previewRequestRef = useRef< AbortController | null >( null );

	function clearPreview() {
		previewRequestRef.current?.abort();
		previewRequestRef.current = null;
		setPreview( null );
	}

	async function viewRules() {
		previewRequestRef.current?.abort();
		const controller = new AbortController();
		previewRequestRef.current = controller;
		setPreview( 'loading' );
		const query = new URLSearchParams();
		if ( role ) {
			query.set( 'role', role );
		}
		if ( postType ) {
			query.set( 'postType', postType );
		}
		let result: PreviewResult;
		try {
			const rules = await apiFetch( {
				path: `/vip-governance/v1/rules?${ query }`,
				signal: controller.signal,
			} );
			result = { value: JSON.stringify( rules, null, 4 ), error: false };
		} catch ( failure ) {
			const message =
				typeof failure === 'object' &&
				failure !== null &&
				'message' in failure &&
				typeof failure.message === 'string'
					? failure.message
					: '';
			result = {
				value: message || __( 'Unable to load governance rules.', 'vip-governance' ),
				error: true,
			};
		}
		if ( ! controller.signal.aborted ) {
			previewRequestRef.current = null;
			setPreview( result );
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
	const loading = preview === 'loading';
	const result = loading ? null : preview;
	let announcement = '';
	if ( loading ) {
		announcement = __( 'Loading governance rules…', 'vip-governance' );
	} else if ( result?.value && ! result.error ) {
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
							onChange={ value => {
								setRole( value );
								clearPreview();
							} }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Post type', 'vip-governance' ) }
							value={ postType }
							options={ options( settings.postTypes ) }
							onChange={ value => {
								setPostType( value );
								clearPreview();
							} }
						/>
						{ ( role || postType ) && (
							<Button variant="secondary" type="button" onClick={ () => void viewRules() }>
								{ __( 'View Rules', 'vip-governance' ) }
							</Button>
						) }
						{ loading && <Spinner /> }
					</div>
					<div role="status" className="screen-reader-text">
						{ announcement }
					</div>
					{ result?.error && (
						<Notice status="error" isDismissible={ false }>
							{ result.value }
						</Notice>
					) }
					{ result?.value && ! result.error && (
						<pre id="json" tabIndex={ 0 }>
							{ result.value }
						</pre>
					) }
				</PanelBody>
			) }
			<p>
				{ sprintf(
					/* translators: %s: Plugin version. */
					__( 'Plugin Version: %s', 'vip-governance' ),
					settings.version
				) }
			</p>
		</div>
	);
}
