# VIP Governance plugin

This is a WordPress plugin that's meant to add in governance within the Block Editor, specifically for two dimensions:

- Insertion: This adds the ability to restrict what kind of blocks can be inserted into the block editor. Only what’s allowed can be inserted, and nothing else. This means that even if new core blocks are introduced or existing ones are modified, they would not be permitted.
- Interaction: This adds the ability to control the styling available for blocks at any level. This also has an extra addition on top which we call lockdown mode, that will disable any kind of interaction including block movements, unless you have permission to do so.

## Table of contents

- [Installation](#installation)
	- [Install via `git subtree`](#install-via-git-subtree)
	- [Install via ZIP file](#install-via-zip-file)
	- [Plugin activation](#plugin-activation)
- [Usage](#usage)
    - [Examples](#examples)
        - [Default](#default)
        - [Restrict Children](#restrict-children)
    - [Schema Definition](#schema-definition)    
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

In order to start using this plugin, you'll need to create `governance-rules.json` in your private folder. Below are some examples that you can use as a starting point for your rules.

### Examples

#### Default

This is the default rule set used by the plugin.

```json
{
	"$schema": "./governance-schema.json",
	"version": "0.1.0",
	"rules": [
		{
			"type": "default",
			"allowedBlocks": [ "*" ]
		}
	]
}
```

With this default rule set, you'll get the following rules:

- All blocks are allowed to be inserted across all the roles. 
    - There are no restrictions, including on what children are allowed under a block.

#### Restrict Children

This is an example in which you want to separate the rules on a per role basis, and restrict the children available but not ban them outright. In addition, you want to provide an extra colour option for these children.

```json
{
	"$schema": "./governance-schema.json",
	"version": "0.1.0",
	"rules": [
		{
			"type": "role",
			"roles": [ "editor", "administrator" ],
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
									"color": "#ff0000",
									"name": "Custom red",
									"slug": "custom-red"
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
								"color": "#ff0000",
								"name": "Custom red",
								"slug": "custom-red"
							}
						]
					}
				}
			}
		}
	]
}
```

With this example, you'll get the following rules:

- Default: This is going to apply to everyone as a baseline.
    - Heading/paragraph blocks are allowed
    - For a heading at the root level, a custom red colour will appear as a possible text colour option.
- Editor/Administrator role: Since only one `blockSettings` can apply the one mentioned here will be used, and the `allowedBlocks` will be combined. What we will get:
    - Besides the default allowed blocks, quote/media-text and image blocks will be allowed as well. A quote block will be allowed to have heading, and paragraph as its children while a media-text block will be allowed to have heading, paragraph and image as its children.
    - A heading sitting inside a media-text will be allowed to have a custom red colour as it's text.
    - The heading at the root level will not have custom red colour available.

### Schema Definition

You can find the schema that's used for validating the rules [here][repo-schema-location]. This will help you in crafting up the custom rules file if the example provided above is not sufficient.

## Code Filters

There are filters in place, that can be applied to change the behaviour for what's allowed and what's not allowed.

### `vip_governance__block_allowed_for_insertion`

Change what blocks are allowed to be inserted in the block editor. By default, root level and children blocks are compared against the governance rules and then a decision is made to allow them or reject them. This filter will allow you to further add on any logic you may have to change if its allowed or not.

```js
/**
 * Change what blocks are allowed to be inserted in the block editor.
 * 
 * @param result Whether or not the block will be allowed
 * @param rootClientId The ID of parent of this block. It will be null if it's a root level block
 * @param blockType The block, whose name can be accessed using blockType.name
 * @param getBlock A selector populated with the right state so it can be used to get the parent of the block
 * @param governanceRules The governance rules for the current user. The relevant property on this is allowedBlocks and blockSettings
 */
return apply_filters( 'vip_governance__block_allowed_for_insertion', result, rootClientId, blockType, getBlock, governanceRules );
```

For example, this filter can be used to allow the insertion of a custom block even if its not allowed by the governance rules:

```js
addFilter(
	'vip_governance__block_allowed_for_insertion',
	'example/restrict-insertion`,
	( result, rootClientId, blockType, getBlock, governanceRules ) => {
		if ( rootClientId && blockType.name === 'custom/my-amazing-block' ) {
			return true;
		}

		return result;
	}
);
```

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
[repo-schema-location]: governance-schema.json
[repo-issue-create]: https://github.com/wpcomvip/vip-governance-plugin/issues/new/choose
[repo-releases]: https://github.com/wpcomvip/vip-governance-plugin/releases
[vip-go-mu-plugins]: https://github.com/Automattic/vip-go-mu-plugins/
[wp-env]: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/
[wpvip-page-cache]: https://docs.wpvip.com/technical-references/caching/page-cache/
[wpvip-plugin-activate]: https://docs.wpvip.com/how-tos/activate-plugins-through-code/
[wpvip-plugin-submodules]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-submodules
[wpvip-plugin-subtrees]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-subtrees
