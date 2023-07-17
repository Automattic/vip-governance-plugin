/* eslint-disable no-undef */
// eslint-disable-next-line no-unused-vars
function showRulesForUserRole() {
	const roleSelector = document.getElementById( 'user-role-selector' );
	if ( roleSelector && roleSelector.value && window.wp && window.wp.apiRequest ) {
		window.wp
			.apiRequest( { path: `/vip-governance/v1/${ roleSelector.value }/rules` } )
			.done( rules => {
				document.getElementById( 'json' ).innerHTML = JSON.stringify( rules, undefined, 4 );
				document.getElementById( 'rules-json' ).removeAttribute( 'hidden' );
			} )
			.fail( error => {
				document.getElementById( 'json' ).innerHTML = error.responseJSON.message;
				document.getElementById( 'rules-json' ).removeAttribute( 'hidden' );
			} );
	}
}
