const RULES_ENDPOINT = '/vip-governance/v1/rules';

/**
 * Initialize the role and post type rules viewer on the plugin settings page.
 *
 * @param {Object}   options          Initialization options.
 * @param {Document} [options.root]   Document containing the settings page.
 * @param {Function} options.request  Function used to request combined rules.
 * @return {void}
 */
export function initializeRulesViewer( { root = document, request } ) {
	const roleSelector = root.getElementById( 'user-role-selector' );
	const postTypeSelector = root.getElementById( 'post-type-selector' );
	const viewButton = root.getElementById( 'view-rules-button' );
	const spinner = root.querySelector( '.vip-governance-query-spinner' );
	const output = root.getElementById( 'combined-governance-rules-json' );

	if (
		! roleSelector ||
		! postTypeSelector ||
		! viewButton ||
		! spinner ||
		! output ||
		typeof request !== 'function'
	) {
		return;
	}

	let latestRequest = 0;

	const getRequestData = () => {
		const data = {};

		if ( roleSelector.value ) {
			data.role = roleSelector.value;
		}

		if ( postTypeSelector.value ) {
			data.postType = postTypeSelector.value;
		}

		return data;
	};

	const updateControls = () => {
		const hasSelection = Object.keys( getRequestData() ).length > 0;
		viewButton.hidden = ! hasSelection;

		if ( ! hasSelection ) {
			output.hidden = true;
		}
	};

	const showOutput = value => {
		output.textContent = value;
		output.hidden = false;
	};

	const handleRequest = async () => {
		const data = getRequestData();

		if ( Object.keys( data ).length === 0 ) {
			return;
		}

		const requestId = ++latestRequest;
		roleSelector.disabled = true;
		postTypeSelector.disabled = true;
		viewButton.disabled = true;
		spinner.hidden = false;
		spinner.classList.add( 'is-active' );

		try {
			const rules = await request( {
				path: `${ RULES_ENDPOINT }?${ new URLSearchParams( data ) }`,
			} );

			if ( requestId === latestRequest ) {
				showOutput( JSON.stringify( rules, null, 4 ) );
			}
		} catch ( error ) {
			if ( requestId === latestRequest ) {
				showOutput( error?.message || output.dataset.errorMessage );
			}
		} finally {
			if ( requestId === latestRequest ) {
				roleSelector.disabled = false;
				postTypeSelector.disabled = false;
				viewButton.disabled = false;
				spinner.hidden = true;
				spinner.classList.remove( 'is-active' );
			}
		}
	};

	roleSelector.addEventListener( 'change', updateControls );
	postTypeSelector.addEventListener( 'change', updateControls );
	viewButton.addEventListener( 'click', handleRequest );

	// Preserve the original settings-page behavior on reload and browser restore.
	roleSelector.value = '';
	postTypeSelector.value = '';
	updateControls();
}
