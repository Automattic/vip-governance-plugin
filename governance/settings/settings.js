/* eslint-disable no-undef */
// eslint-disable-next-line no-unused-vars
( () => {
	function showRulesForUserRole() {
		const roleSelector = document.getElementById( 'user-role-selector' );
		if ( roleSelector && roleSelector.value && window.wp && window.wp.apiRequest ) {
			document.querySelector( '.vip-governance-role-query-spinner' ).classList.add( 'is-active' );
			window.wp
				.apiRequest( { path: `/vip-governance/v1/rules?role=${ roleSelector.value }` } )
				.done( rules => {
					const rulesPrefix = '"' + roleSelector.value + '": ';
					document.getElementById( 'json' ).textContent =
						rulesPrefix + JSON.stringify( rules, undefined, 4 );
					document.getElementById( 'json' ).removeAttribute( 'hidden' );
				} )
				.fail( error => {
					document.getElementById( 'json' ).textContent = error.responseJSON.message;
					document.getElementById( 'json' ).removeAttribute( 'hidden' );
				} )
				.complete( () => {
					document
						.querySelector( '.vip-governance-role-query-spinner' )
						.classList.remove( 'is-active' );
				} );
		}
	}

	const roleSelector = document.getElementById( 'user-role-selector' );

	if ( roleSelector ) {
		// Reset to the default value on refresh
		roleSelector.value = '';

		roleSelector.addEventListener( 'change', showRulesForUserRole );
	}
} )();
