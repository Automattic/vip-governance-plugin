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
    - [Your First Rule](#your-first-rule)
    - [Schema Basics](#schema-basics)
        - [Limitations](#limitations)
    - [Sample Rules](#sample-rules)
        - [Default](#default)
        - [Restrictions](#restrictions)
          - [Default Restriction Example](#default-restriction-example)
          - [User Role Specific Restriction Example](#user-role-restriction-example)
- [Code Filters](#code-filters)
    - [`vip_governance__is_block_allowed_for_insertion`](#vip_governance__is_block_allowed_for_insertion)
    - [`vip_governance__is_block_allowed_for_editing`](#vip_governance__is_block_allowed_for_editing)
- [Admin Settings](#admin-settings)
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

- `allowedFeatures`: This is an array of the features that are allowed in the block editor. This list will expand with time, but we currently support two values: `codeEditor` and `lockBlocks`. If you do not want to enable these features, simply omit them from the array.
- `blockSettings`: These are specific settings related to the styling available for a block. They match the settings availble in theme.json [as defined here][gutenberg-block-settings]. Unlike theme.json, you can nest these rules to apply different settings depending on the parent of a particular block. Additionaly you can set `allowedBlocks` to restrict nested blocks.
- `allowedBlocks`: These are the blocks that are allowed to be inserted into the block editor.

The role specific rule will be merged with the default rule. This is done intentionally to avoid needless repetition of your default properties.

#### Limitations

- We highly recommend including `core/paragraph` in `allowedBlocks` for the `default` rule so that all users have access to use paragraph blocks. There are some limitations with the editor that make this necessary:

    - The Gutenberg editor uses `core/paragraph` blocks as an insertion primitive. If a user is unable to insert paragraph blocks, then they will also be unable to insert any other block in the same place.
    - Some `core` blocks automatically insert `core/paragraph` blocks that can not be blocked by plugin code. For example, the `core/quote` block has a child `core/paragraph` block built-in to block output. Even if a user has `core/paragraph` blocks disabled, they may still be able to access built-in child blocks.

    It is possible to disable `core/paragraph` blocks for a role if it makes sense for your workflow, but keep in mind these limitations when doing so.

- Currently, this plugin does not support disabling child blocks nested inside a parent. The plugin will prevent you from inserting additional blocks, but existing blocks in existing content will not be removed or restricted.

- Support for `colors.duotone` has not been implemented.

### Sample Rules

Below are some examples of some rules that you can use to build your `governance-rules.json`.

#### Default

This is the default rule set used by the plugin.

```json
{
  "$schema": "./governance-schema.json",
  "version": "0.2.0",
  "rules": [
    {
      "type": "default",
      "allowedFeatures": [ "codeEditor", "lockBlocks" ],
      "allowedBlocks": [ "*" ]
    }
  ]
}
```

With this rule set, the following rules will apply:

- All blocks can be inserted across all the roles.
- No restrictions apply for what's allowed under a block.
- The code editor is accessible for everyone.
- Blocks can be locked and unlocked.

#### Restrictions

There are 2 examples below that show how different restrictions can be set. This will include restrictions on features available in the block editor, the blocks available, and what style controls are available.

##### Default Restriction Example

This example focuses on restricting for all users, regardless of their role.

```json
{
	"$schema": "./governance-schema.json",
	"version": "0.2.0",
	"rules": [
		{
			"type": "default",
			"allowedFeatures": [ "codeEditor", "lockBlocks" ],
			"allowedBlocks": [ "*" ],
			"blockSettings": {
        "core/group": {
					"spacing": {
						"spacingSizes": [
							{
								"size": "clamp(2.5rem, 6vw, 3rem)",
								"slug": "300",
								"name": "12"
							}
						],
					},
        },
				"core/heading": {
					"color": {
						"palette": [
							{
								"color": "#ff0000",
								"name": "Custom red",
								"slug": "custom-red"
							},
							{
								"color": "#00FF00",
								"name": "Custom green",
								"slug": "custom-green"
							},
							{
								"color": "#FFFF00",
								"name": "Custom yellow",
								"slug": "custom-yellow"
							}
						],
						"gradients": [
							{
								"slug": "vertical-red-to-green",
								"gradient": "linear-gradient(to bottom,var(--wp--preset--color--custom-red) 0%,var(--wp--preset--color--custom-green) 100%)",
								"name": "Vertical red to green"
							}
						]
					},
					"typography": {
						"fontFamilies": [
							{
								"fontFamily": "Consolas, Fira Code, monospace",
								"slug": "code-font",
								"name": "Code Font"
							}
						],
						"fontSizes": [
							{
								"name": "Large",
								"size": "2.75rem",
								"slug": "large"
							},
							{
								"name": "X-Large",
								"size": "3.75rem",
								"slug": "x-large"
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
    - All blocks are allowed.
    - The code editor is accessible, and blocks can be locked/unlocked or moved.
    - For a heading at the root level, there are 3 custom colours as well as a custom gradient that will show up in the color palette. In addition, a custom font called Code Font as well as 2 custom font sizes will show up in the typography panel.
    - For a group block, there will be a only one option for a spacing size available in padding/margin and block spacing.

##### User Role Restriction Example

This example focuses on restricting based on the user role.

```json
{
  "$schema": "./governance-schema.json",
  "version": "0.2.0",
  "rules": [
    {
      "type": "role",
      "roles": [ "administrator" ],
      "allowedFeatures": [ "codeEditor", "lockBlocks" ],
      "allowedBlocks": [ "core/quote", "core/media-text", "core/image" ],
      "blockSettings": {
        "core/media-text": {
          "allowedBlocks": [ "core/paragraph", "core/heading", "core/image" ],
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
          "allowedBlocks": [ "core/paragraph", "core/heading" ],
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

Change what blocks are allowed to be inserted in the block editor. By default, root level and children blocks are compared against the governance rules and then a decision is made to allow them or reject them. This filter will allow you to override the default logic for insertion.

```js
/**
 * Change what blocks are allowed to be inserted in the block editor.
 *
 * @param {bool}     isAllowed        Whether or not the block will be allowed.
 * @param {string}   blockName        The name of the block to be inserted.
 * @param {string[]} parentBlockNames An array of zero or more parent block names,
 *                                    starting with the most recent parent ancestor.
 * @param {Object}   governanceRules  An object containing the full set of governance
 *                                    rules for the current user.
 */
return applyFilters(
    'vip_governance__is_block_allowed_for_insertion',
    isAllowed,
    blockType.name,
    parentBlockNames,
    governanceRules
);
```

For example, this filter can be used to allow the insertion of a custom block even if its not allowed by the governance rules:

```js
addFilter(
    'vip_governance__is_block_allowed_for_insertion',
    'example/allow-custom-block-insertion',
    ( isAllowed, blockName, parentBlockNames, governanceRules ) => {
        if ( blockName === 'custom/my-amazing-block' ) {
            return true;
        }

        return isAllowed;
    }
);
```

### `vip_governance__is_block_allowed_for_editing`

Change what blocks are allowed to be edited in the block editor. Disabled blocks will display with a grey border and will not be editable. By default, root level and children blocks are compared against the governance rules and then a decision is made to allow them or reject them. This filter will allow you to override the default logic for editing.

```js
/**
 * Change what blocks are allowed to be edited in the block editor.
 *
 * @param {bool}     isAllowed        Whether or not the block will be allowed.
 * @param {string}   blockName        The name of the block to be edited.
 * @param {string[]} parentBlockNames An array of zero or more parent block names,
 *                                    starting with the most recent parent ancestor.
 * @param {Object}   governanceRules  An object containing the full set of governance
 *                                    rules for the current user.
 */
applyFilters(
    'vip_governance__is_block_allowed_for_editing',
    isAllowed,
    blockName,
    parentBlockNames,
    governanceRules
);
```

For example, this filter can be used to allow the editing a custom block type even if it is disabled by governance rules:

```js
addFilter(
    'vip_governance__is_block_allowed_for_insertion',
    'example/allow-custom-block-editing',
    ( isAllowed, blockName, parentBlockNames, governanceRules ) => {
        if ( blockName === 'custom/my-amazing-block' ) {
            return true;
        }

        return isAllowed;
    }
);
```

## Admin Settings

There is an admin settings menu titled `VIP Governance` that's created with the use of this plugin. This page offers some helpful items such as:

- Turning on and off the plugin quickly, without re-deploying.
- View all the rules at once, and also any errors if it's invalid.
- View the rules as a specific user role.

![Admin setting in action][settings-panel-example-gif]

## Endpoints

The examples in the below endpoints are using the rule file found in the example rule file [above](#restrictions).

### `vip-governance/v1/<role>/rules`

This endpoint is used to return the combined rules for a given role. This API is utilized by the settings page to visualize merged default and role rules for a selected role. It's only available to users with the `manage_options` permission.

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

**Please note that, this is for VIP sites only. Analytics are disabled if this plugin is not being run on VIP sites.**

The plugin records two data points for analytics, on VIP sites:

1. A usage metric when the block editor is loaded with the VIP Governance plugin activated. This analytic data simply is a counter, and includes no information about the post's content or metadata. It will only include the customer site ID to associate the usage.
   
2. When an error occurs from within the plugin on the [WordPress VIP][wpvip] platform. This is used to identify issues with customers for private follow-up.

Both of these data points are a counter that is incremented, and do not contain any other telemetry or sensitive data. You can see what's being [collected in code here][repo-analytics].

## Development

In order to ensure no dev dependencies go in, the following can be done while installing the packages:

```
composer install --no-dev
```

### Tests

We currently have unit tests covering php side of the plugin. Run these tests locally with [`wp-env`][wp-env] and Docker:

```
wp-env start
composer install
composer run test
```

<!-- Links -->

[settings-panel-example-gif]: https://github.com/wpcomvip/vip-governance-plugin/blob/media/vip-governance-admin-settings-animation.gif
[analytics-file]: governance/analytics.php
[repo-schema-location]: governance-schema.json
[gutenberg-block-settings]: https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-json/#settings
[repo-analytics]: governance/analytics.php
[repo-issue-create]: https://github.com/wpcomvip/vip-governance-plugin/issues/new/choose
[repo-releases]: https://github.com/wpcomvip/vip-governance-plugin/releases
[repo-schema-location]: governance-schema.json
[vip-go-mu-plugins]: https://github.com/Automattic/vip-go-mu-plugins/
[wp-custom-roles]: https://developer.wordpress.org/reference/functions/add_role/
[wp-default-roles]: https://wordpress.org/documentation/article/roles-and-capabilities/
[wp-env]: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/
[wpvip-page-cache]: https://docs.wpvip.com/technical-references/caching/page-cache/
[wpvip-plugin-activate]: https://docs.wpvip.com/how-tos/activate-plugins-through-code/
[wpvip-plugin-submodules]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-submodules
[wpvip-plugin-subtrees]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-subtrees
[wpvip-private-dir]: https://docs.wpvip.com/technical-references/vip-codebase/private-directory/
[wpvip]: https://wpvip.com/
