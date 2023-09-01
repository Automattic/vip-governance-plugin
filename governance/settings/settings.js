/* eslint-disable no-undef */
// eslint-disable-next-line no-unused-vars
( () => {
	function showValuesForRuleType() {
		const typeSelector = document.getElementById( 'rule-type-selector' );
		if ( typeSelector && typeSelector.value ) {
			const roleSelector = document.getElementById( 'user-role-selector' );
			const postTypeSelector = document.getElementById( 'post-type-selector' );

			// show user-role-selector or post-type-selector based on the value
			if ( 'role' === typeSelector.value ) {
				document.getElementById( 'json' ).hidden = true;
				roleSelector.hidden = false;
				postTypeSelector.hidden = true;
			} else if ( 'postType' === typeSelector.value ) {
				document.getElementById( 'json' ).hidden = true;
				roleSelector.hidden = true;
				postTypeSelector.hidden = false;
			}
		}
	}

	function showRulesForRuleType( ruleType, elementId ) {
		const ruleTypeValueSelector = document.getElementById( elementId );
		if (
			ruleTypeValueSelector &&
			ruleTypeValueSelector.value &&
			window.wp &&
			window.wp.apiRequest
		) {
			document.querySelector( '.vip-governance-query-spinner' ).classList.add( 'is-active' );
			window.wp
				.apiRequest( {
					path: `/vip-governance/v1/rules?${ ruleType }=${ ruleTypeValueSelector.value }`,
				} )
				.done( rules => {
					const rulesPrefix = '"' + ruleTypeValueSelector.value + '": ';
					document.getElementById( 'json' ).textContent =
						rulesPrefix + JSON.stringify( rules, undefined, 4 );
					document.getElementById( 'json' ).hidden = false;
				} )
				.fail( error => {
					document.getElementById( 'json' ).textContent = error.responseJSON.message;
					document.getElementById( 'json' ).hidden = false;
				} )
				.complete( () => {
					document.querySelector( '.vip-governance-query-spinner' ).classList.remove( 'is-active' );
				} );
		}
	}

	const ruleTypeSelector = document.getElementById( 'rule-type-selector' );
	if ( ruleTypeSelector ) {
		// Reset to the default value on refresh
		ruleTypeSelector.value = '';

		ruleTypeSelector.addEventListener( 'change', showValuesForRuleType );
	}

	const roleSelector = document.getElementById( 'user-role-selector' );

	if ( roleSelector ) {
		// Reset to the default value on refresh
		roleSelector.value = '';

		roleSelector.addEventListener(
			'change',
			showRulesForRuleType.bind( this, 'role', 'user-role-selector' )
		);
	}

	const postTypeSelector = document.getElementById( 'post-type-selector' );

	if ( postTypeSelector ) {
		// Reset to the default value on refresh
		postTypeSelector.value = '';

		postTypeSelector.addEventListener(
			'change',
			showRulesForRuleType.bind( this, 'postType', 'post-type-selector' )
		);
	}
} )();
