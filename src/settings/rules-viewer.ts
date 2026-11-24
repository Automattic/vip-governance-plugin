const RULES_ENDPOINT = '/vip-governance/v1/rules';

type RulesRequest = ( options: { path: string } ) => Promise< unknown >;

interface RulesViewerOptions {
	request: RulesRequest;
	root?: Document;
}

type RequestData = Record< string, string >;

function getErrorMessage( error: unknown, fallback: string ): string {
	if ( error instanceof Error && error.message ) {
		return error.message;
	}

	if (
		typeof error === 'object' &&
		error !== null &&
		'message' in error &&
		typeof error.message === 'string' &&
		error.message
	) {
		return error.message;
	}

	return fallback;
}

/** Initialize the role and post type rules viewer on the plugin settings page. */
export function initializeRulesViewer( { root = document, request }: RulesViewerOptions ): void {
	const roleSelector = root.getElementById( 'user-role-selector' );
	const postTypeSelector = root.getElementById( 'post-type-selector' );
	const viewButton = root.getElementById( 'view-rules-button' );
	const spinner = root.querySelector( '.vip-governance-query-spinner' );
	const status = root.getElementById( 'vip-governance-preview-status' );
	const output = root.getElementById( 'json' );

	if (
		! ( roleSelector instanceof HTMLSelectElement ) ||
		! ( postTypeSelector instanceof HTMLSelectElement ) ||
		! ( viewButton instanceof HTMLButtonElement ) ||
		! ( spinner instanceof HTMLElement ) ||
		! status ||
		! ( output instanceof HTMLElement )
	) {
		return;
	}

	const getRequestData = (): RequestData => {
		const data: RequestData = {};

		if ( roleSelector.value ) {
			data.role = roleSelector.value;
		}

		if ( postTypeSelector.value ) {
			data.postType = postTypeSelector.value;
		}

		return data;
	};

	let latestRequestId = 0;

	const clearOutput = () => {
		output.hidden = true;
		output.textContent = '';
	};

	const setLoading = ( isLoading: boolean ) => {
		spinner.hidden = ! isLoading;
		spinner.classList.toggle( 'is-active', isLoading );
	};

	const updateControls = () => {
		latestRequestId++;
		viewButton.hidden = ! ( roleSelector.value || postTypeSelector.value );
		clearOutput();
		setLoading( false );
		status.textContent = '';
	};

	const handleRequest = async () => {
		const data = getRequestData();

		if ( Object.keys( data ).length === 0 ) {
			return;
		}

		const requestId = ++latestRequestId;
		clearOutput();
		setLoading( true );
		status.textContent = status.dataset.loading ?? '';

		let value: string;
		let announcement = status.dataset.loaded ?? '';
		try {
			const rules = await request( {
				path: `${ RULES_ENDPOINT }?${ new URLSearchParams( data ) }`,
			} );

			value = JSON.stringify( rules, null, 4 );
		} catch ( error ) {
			value = getErrorMessage( error, output.dataset.errorMessage ?? 'Unable to load rules.' );
			announcement = value;
		}

		if ( requestId !== latestRequestId ) {
			return;
		}

		output.textContent = value;
		output.hidden = false;
		status.textContent = announcement;
		setLoading( false );
	};

	roleSelector.addEventListener( 'change', updateControls );
	postTypeSelector.addEventListener( 'change', updateControls );
	viewButton.addEventListener( 'click', () => {
		void handleRequest();
	} );

	roleSelector.value = '';
	postTypeSelector.value = '';
	updateControls();
}
