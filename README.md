---
### :warning: This plugin is currently in Beta. It is designed to run on [WordPress VIP][wpvip]. This beta release is not intended for use on a production environment.
---

# VIP Governance plugin

This WordPress plugin enables additional governance capabilities to the block editor.

We have approached this plugin from an opt-in standpoint. In other words, enabling this plugin without any rules will severly limit the editing experience. The goal is to create a stable editor with new blocks and features being enabled explicitly via rules, rather than implicitly via updates.

We consider two dimensions:

- Insertion: restricts what kind of blocks can be inserted into the block editor. Only what’s allowed can be inserted, and nothing else. This means that even if new core blocks are introduced they would not be permitted.
- Interaction: This adds the ability to control the styling available for blocks at any level.

## Table of contents

- [Installation](#installation)
  - [Install via `git subtree`](#install-via-git-subtree)
  - [Install via ZIP file](#install-via-zip-file)
  - [Plugin activation](#plugin-activation)
- [Usage](#usage)
  - [Schema Basics](#schema-basics)
    - [Limitations](#limitations)
  - [Sample Rules](#sample-rules)
    - [Default](#default)
    - [Restrictions](#restrictions)
- [Code Filters](#code-filters)
  - [`vip_governance__is_block_allowed_for_insertion`](#vip_governance__is_block_allowed_for_insertion)
- [Endpoints](#endpoints)
  - [`vip-governance/v1/<role>/rules`](#vip-governancev1rolerules)
  - [Example](#example)
- [Analytics](#analytics)
- [Development](#development)
  - [Tests](#tests)

## Installation

The latest version of the VIP Governance plugin is available in the default `trunk` branch of this repository.

### Install via `git subtree`

We recommend installing the latest plugin version [via `git subtree`][wpvip-plugin-subtrees] within your site's repository:

```bash
# Enter your project's root directory:
cd my-site-repo/

# Add a subtree for the trunk branch:
git subtree add --prefix plugins/vip-governance git@github.com:wpcomvip/vip-governance-plugin.git trunk --squash
```

To deploy the plugin to a remote branch, `git push` the committed subtree.

The `trunk` branch will stay up to date with the latest version of the plugin. Use this command to pull the latest `trunk` branch changes:

```bash
git subtree pull --prefix plugins/vip-governance git@github.com:wpcomvip/vip-governance-plugin.git trunk --squash
```

Ensure that the plugin is up-to-date by pulling changes often.

Note: We **do not recommend** using `git submodule`. [Submodules on WPVIP that require authentication][wpvip-plugin-submodules] will fail to deploy.

### Install via ZIP file

The latest version of the plugin can be downloaded from the [repository's Releases page][repo-releases]. Unzip the downloaded plugin and add it to the `plugins/` directory of your site's GitHub repository.

### Plugin activation

Usually VIP recommends [activating plugins with code][wpvip-plugin-activate]. In this case, we are recommending activating the plugin in the WordPress Admin dashboard. This will allow the plugin to be more easily enabled and disabled during testing.

To activate the installed plugin:

1. Navigate to the WordPress Admin dashboard as a logged-in user.
2. Select **Plugins** from the lefthand navigation menu.
3. Locate the "VIP Governance" plugin in the list and select the "Activate" link located below it.

## Usage

In order to start using this plugin, you'll need to create `governance-rules.json` in [your private folder][wpvip-private-dir].

### Your First Rule

Each ruleset must define your `default` rule. You can see an example definition in `governance-rules.json` in this repository. We recommend duplicating this file into your [private folder][wpvip-private-dir] as a start. In order to use the rules schema for in-editor support, also duplicate the `governance-schema.json` into your private folder.

This default rule represents the absolute minimum that will be available to website users. It is sensible to set your default rule to your most common settings and then override with role specific rules.

### Schema Basics

You can find the schema definition that's used for the rules [here][repo-schema-location]. Including a schema entry in your rules will provide for code completion in most editors.

We have allowed significant space for customization. Which means it is also possible to create unintented rule interactions. We recommend making rule changes one or two at a time to be able to best troubleshoot these interactions.

Each rule is an object in an array. The one required property is `type` which can either be `default` or `role`. Your rules should only have one entry of the `default` type, as described above.

Rule's of the type `role` require an array of `roles` that will use this particular rule. These are the name/slug of any [default][wp-default-roles] or [custom][wp-custom-roles] roles.

Each rule can have any one of the following properties.

- `allowedFeatures`: This is an array of the features that are allowed in the block editor. This list will expand with time, but we currently support two values: `codeEditor` and `moveBlocks`. If you do not want to enable these features, simply omit them from the array.
- `blockSettings`: These are specific settings related to the styling available for a block. They match the settings availble in theme.json [as defined here][gutenberg-block-settings]. Unlike theme.json, you can nest these rules to apply different settings depending on the parent of a particular block. Additionaly you can set `allowedChildren` to restrict nested blocks.
- `allowedBlocks`: These are the blocks that are allowed to be inserted into the block editor.

The role specific rule will be merged with the default rule. This is done intentionally to avoid needless repetition of your default properties.

#### Limitations

- Currently, this plugin does not support disabling child blocks nested inside a parent. The plugin will prevent you from inserting additoinal blocks, but existing blocks in existing content will not be removed or restricted.

### Sample Rules

Below are some examples of some rules that you can use to build your `governance-rules.json`.

#### Default

This is the default rule set used by the plugin.

```json
{
	"$schema": "./governance-schema.json",
	"version": "0.1.0",
	"rules": [
		{
			"type": "default",
			"allowedFeatures": [ "codeEditor", "moveBlocks" ],
			"allowedBlocks": [ "*" ]
		}
	]
}
```

With this rule set, the following rules will apply:

- All blocks can be inserted across all the roles.
- No restrictions apply for what's allowed under a block.
- The code editor is accessible for everyone.
- Blocks can be locked, unlocked and moved.

#### Restrictions

This is an example in which we want to apply different restrictions based on user role. This will include restrictions on features available in the block editor, the blocks available, and what style controls are available.

```json
{
	"$schema": "./governance-schema.json",
	"version": "0.1.0",
	"rules": [
		{
			"type": "role",
			"roles": [ "administrator" ],
			"allowedFeatures": [ "codeEditor", "moveBlocks" ],
			"allowedBlocks": [ "core/quote", "core/media-text", "core/image" ],
			"blockSettings": {
				"core/media-text": {
					"allowedChildren": [ "core/paragraph", "core/heading", "core/image" ],
					"core/heading": {
						"color": {
							"text": true,
							"palette": [
								{
									"color": "#ff0000",
									"name": "Custom red",
									"slug": "custom-red"
								}
							]
						}
					}
				},
				"core/quote": {
					"allowedChildren": [ "core/paragraph", "core/heading" ],
					"core/paragraph": {
						"color": {
							"text": true,
							"palette": [
								{
									"color": "#00FF00",
									"name": "Custom green",
									"slug": "custom-green"
								}
							]
						}
					}
				}
			}
		},
		{
			"type": "default",
			"allowedBlocks": [ "core/heading", "core/paragraph" ],
			"blockSettings": {
				"core/heading": {
					"color": {
						"text": true,
						"palette": [
							{
								"color": "#FFFF00",
								"name": "Custom yellow",
								"slug": "custom-yellow"
							}
						]
					}
				}
			}
		}
	]
}
```

With this rule set, the following rules will apply:

- Default: Rules that apply to everyone as a baseline:
  - Heading/paragraph blocks are allowed
  - For a heading at the root level, a custom yellow colour will appear as a possible text colour option.
  - Blocks cannot be locked/unlocked or moved.
  - The code editor is not accessible.
- Administrator role: Role-specific rules combined with the default set of rules:
  - In addition to the default allowed blocks, quote/media-text and image blocks is allowed as well.
  - A quote block is allowed to have heading, and paragraph as its children while a media-text block is allowed to have heading, paragraph and image as its children.
  - A heading at the root level is a custom yellow colour as a possible text colour option.
  - A heading sitting inside a media-text is allowed to have a custom red colour as it's text.
  - A paragraph sitting inside a quote is allowed to have a custom green colour as it's text.
  - The code editor is accessible.
  - Blocks can be locked, unlocked and moved.

## Code Filters

There are filters in place, that can be applied to change the behaviour for what's allowed and what's not allowed.

### `vip_governance__is_block_allowed_for_insertion`

Change what blocks are allowed to be inserted in the block editor. By default, root level and children blocks are compared against the governance rules and then a decision is made to allow them or reject them. This filter will allow you to further add on any logic you may have to change if its allowed or not.

```js
/**
 * Change what blocks are allowed to be inserted in the block editor.
 *
 * @param isAllowed Whether or not the block will be allowed
 * @param blockType The block, whose name can be accessed using blockType.name
 * @param governanceRules The governance rules for the current user. The relevant property on this is allowedBlocks and blockSettings
 * @param rootClientId The ID of parent of this block. It will be null if it's a root level block
 * @param getBlock A selector populated with the right state so it can be used to get the parent of the block
 */
return apply_filters(
	'vip_governance__is_block_allowed_for_insertion',
	isAllowed,
	blockType,
	governanceRules,
	rootClientId,
	getBlock
);
```

For example, this filter can be used to allow the insertion of a custom block even if its not allowed by the governance rules:

```js
addFilter(
	'vip_governance__is_block_allowed_for_insertion',
	'example/restrict-insertion`,
	( result, rootClientId, blockType, getBlock, governanceRules ) => {
		if ( rootClientId && blockType.name === 'custom/my-amazing-block' ) {
			return true;
		}

		return result;
	}
);
```

## Endpoints

The examples in the below endpoints are using the rule file found in the example rule file [above](#restrictions).

### `vip-governance/v1/<role>/rules`

This endpoint is meant to return the combined rules for a given role, so it's easy to visualize what the rules would look like in practice. It's used within the admin panel, to provide this functionality. It's guarded by being limited to users with the `manage_options` permission only.

It has only three root level keys: `allowedBlocks`, `blockSettings` and `allowedFeatures`.

#### Example

This example involves making a call to `http://my.site/wp-json/vip-governance/v1/editor/rules` for an `editor` role:

```json
{
	"allowedBlocks": [ "core/heading", "core/paragraph" ],
	"blockSettings": {
		"core/heading": {
			"color": {
				"text": true,
				"palette": [
					{
						"color": "#FFFF00",
						"name": "Custom yellow",
						"slug": "custom-yellow"
					}
				]
			}
		}
	},
	"allowedFeatures": []
}
```

## Analytics

The plugin records a single data point for analytics:

1. A usage metric when the block editor is loaded with the VIP Governance plugin activated. This analytic data simply is a counter, and includes no information about the post's content or metadata.

   When the plugin is used on the [WordPress VIP][wpvip] platform, analytics data will include the customer site ID associated with usage. All other usage of this plugin outside of WordPress VIP is marked with an `Unknown` source.

This data point is a counter that is incremented, and does not contain any other telemetry or sensitive data. You can see what's being [collected in code here][analytics-file].

## Development

In order to ensure no dev dependencies go in, the following can be done while installing the packages:

```
composer install --no-dev
```

### Tests

Run tests locally with [`wp-env`][wp-env] and Docker:

```
wp-env start
composer install
composer run test
```

<!-- Links -->

[analytics-file]: governance/analytics.php
[repo-schema-location]: governance-schema.json
[repo-issue-create]: https://github.com/wpcomvip/vip-governance-plugin/issues/new/choose
[repo-releases]: https://github.com/wpcomvip/vip-governance-plugin/releases
[vip-go-mu-plugins]: https://github.com/Automattic/vip-go-mu-plugins/
[wp-env]: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/
[wpvip]: https://wpvip.com/
[wpvip-page-cache]: https://docs.wpvip.com/technical-references/caching/page-cache/
[wpvip-plugin-activate]: https://docs.wpvip.com/how-tos/activate-plugins-through-code/
[wpvip-plugin-submodules]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-submodules
[wpvip-plugin-subtrees]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-subtrees
[wpvip-private-dir]: https://docs.wpvip.com/technical-references/vip-codebase/private-directory/
[gutenberg-block-settings]: https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-json/#settings
[wp-default-roles]: https://wordpress.org/documentation/article/roles-and-capabilities/
[wp-custom-roles]: https://developer.wordpress.org/reference/functions/add_role/
