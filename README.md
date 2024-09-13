# WordPress VIP Block Governance plugin

This WordPress plugin adds additional governance capabilities to the block editor. This is accomplished via two dimensions:

- Insertion: restricts what kind of blocks can be inserted into the block editor. Only what’s allowed can be inserted, and nothing else. This means that even if new core blocks are introduced they would not be permitted.
- Interaction: This adds the ability to control the styling available for blocks at any level.

We have approached this plugin from an opt-in standpoint. In other words, enabling this plugin without any rules will severely limit the editing experience. The goal is to create a stable editor with new blocks and features being enabled explicitly via rules, rather than implicitly via updates.

This plugin is currently developed for use on WordPress sites hosted on the VIP Platform.

- [Try it out](#try-it-out)
- [Installation](#installation)
	- [Install on WordPress VIP](#install-on-wordpress-vip)
	- [Install via ZIP file](#install-via-zip-file)
- [Usage](#usage)
	- [Schema Basics](#schema-basics)
		- [Wildcards](#wildcards)
	- [Quick Start](#quick-start)
	- [Starter Rule Sets](#starter-rule-sets)
		- [Default Rule Set](#default-rule-set)
		- [Default Rule Set With Restrictions](#default-rule-set-with-restrictions)
		- [Default and User Role Rule Set](#default-and-user-role-rule-set)
		- [Default and Post Type Rule Set](#default-and-post-type-rule-set)
		- [Default Wildcard Rule Set](#default-wildcard-rule-set)
	- [Limitations](#limitations)
- [Code Filters](#code-filters)
	- [`vip_governance__governance_file_path`](#vip_governance__governance_file_path)
	- [`vip_governance__governance_rules_json`](#vip_governance__governance_rules_json)
	- [`vip_governance__is_block_allowed_for_insertion`](#vip_governance__is_block_allowed_for_insertion)
	- [`vip_governance__is_block_allowed_for_editing`](#vip_governance__is_block_allowed_for_editing)
	- [`vip_governance__is_block_allowed_in_hierarchy`](#vip_governance__is_block_allowed_in_hierarchy)
- [Admin Settings](#admin-settings)
- [Endpoints](#endpoints)
	- [`vip-governance/v1/<role>/rules`](#vip-governancev1rolerules)
		- [Example](#example)
- [Analytics](#analytics)
- [Development](#development)
	- [Tests](#tests)

## Try it out

Try out the VIP Governance plugin in your browser [with WordPress Playground][playground-blueprint].

## Installation

To use the WordPress VIP Block Governance plugin after activation, skip to [Usage](#usage).

### Install on WordPress VIP

The WordPress VIP Block Governance plugin is authored and maintained by [WordPress VIP][wpvip], and made available to all WordPress sites by [VIP MU plugins][vip-go-mu-plugins]. Customers who host on WordPress VIP or use [`vip dev-env`](https://docs.wpvip.com/how-tos/local-development/use-the-vip-local-development-environment/) to develop locally have access to this plugin automatically. We recommend this activation method for WordPress VIP customers.

Enable the plugin by adding the method shown below to your application's [`client-mu-plugins/plugin-loader.php`][vip-go-skeleton-plugin-loader-example]:

```php
// client-mu-plugins/plugin-loader.php

\Automattic\VIP\Integrations\activate( 'vip-governance' );
```

Create this path in your WordPress VIP site if it does not yet exist.

This will automatically install and activate the latest mu-plugins release of the WordPress VIP Block Governance plugin. Remove this line to deactivate the plugin.

### Install via ZIP file

The latest version of the plugin can be downloaded from the [repository's Releases page][repo-releases]. Unzip the downloaded plugin and add it to the `plugins/` directory of your site's GitHub repository.

## Usage

Your governance rules are saved in `governance-rules.json` in [your private folder][wpvip-private-dir]. Before diving into how it's used, a quick run down of the schema will shed light on how it works.

Note: The [private folder][wpvip-private-dir] is only supported on VIP sites, or while using [`vip dev-env`](https://docs.wpvip.com/how-tos/local-development/use-the-vip-local-development-environment/) locally.

### Schema Basics

You can find the schema definition used for the rules [here][repo-schema-location]. You can use `https://api.wpvip.com/schemas/plugins/governance.json` as the schema entry in your rules, to take advantage of code completion in most editors.

We have allowed significant space for customization. This means it is also possible to create unintended rule interactions. We recommend making rule changes one or two at a time to troubleshoot these interactions.

Each rule is an object in an array. The one required property is `type`, which can be `default`, `role`, or `postType`. Your rules should only have one entry of the `default` type, as described below, and it is the only type that is required in your rule set.

Rules not of type `default` require an additional field. These are broken down below, along with examples of their possible values:

| Rule Type  | Required Field | Possible Values                                                                                |
| ---------- | -------------- | ---------------------------------------------------------------------------------------------- |
| `role`     | `roles`        | name/slug of any [default][wp-default-roles] or [custom][wp-custom-roles] roles                |
| `postType` | `postTypes`    | name/slug of any [default][wp-default-post-types] or [custom][wp-custom-post-types] post types |

Each rule can have any one of the following properties.

- `allowedFeatures`: This is an array of the features that are allowed in the block editor. This list will expand with time, but we currently support two values: `codeEditor` (viewing the content of your post as code in the editor) and `lockBlocks`(ability to lock/unlock blocks that will restrict movement/deletion). If you do not want to enable these features, omit them from the array.
- `blockSettings`: These are specific settings related to the styling available for a block. They match the settings available in theme.json under the key `blocks`. The definition for that can be [found here][gutenberg-block-settings]. Unlike theme.json, you can nest these rules under a block name to apply different settings depending on the parent of a particular block.
- `allowedBlocks`: These are the blocks allowed to be inserted into the block editor. Additionally, you can use `allowedBlocks` in `blockSettings` rules to restrict what blocks can be nested under a parent.

Non-default rule types will be merged with the default rule. This is done intentionally to avoid needless repetition of your default properties. If multiple non-default rule types are provided, they will be applied in the following ascending priority:

1. Post Type
2. Role

So if a matching `postType` and `role` rule is found, the `role` rule will be applied, and the `postType` rule will be ignored. The best analogy is the CSS cascade where more specific rules overwrite less specific rules. We are making a choice that Role-based rules should overwrite Post Type rules. We will introduce a filter in the near future to allow this priority to be customized.

#### Wildcards

The wildcard `*` can be used within `allowedBlocks` and within `blockSettings` to target more than 1 block. The intention is that it will limit repeated rules, and allow greater flexibility in controlling the editor experience.

For an example of this feature, [refer to the example file here](#default-wildcard-rule-set).

Note: `allowedBlocks` are not respected when a parent `blockSettings` also has a wildcard. For example, this will not work:

##### ❌ Using `allowedBlocks` under a parent wildcard:

```js
{
  "$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
  "version": "1.0.0",
  "rules": [
    {
      "type": "default",
      "allowedFeatures": [ "codeEditor", "lockBlocks" ],
      "allowedBlocks": [ "core/*" ],
      "blockSettings": {
        "core/*": {
          "allowedBlocks": [ "core/paragraph", "core/heading" ],  // ← Not allowed under "core/*"
          "color": {
            "text": true,
          }
        }
      }
    }
  ]
}
```

Instead, only apply block settings to wildcards, and specify `allowedBlocks` to individual parent blocks:

##### ✅ Using `allowedBlocks` under defined blocks:

```json
{
  "$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
  "version": "1.0.0",
  "rules": [
    {
      "type": "default",
      "allowedFeatures": [ "codeEditor", "lockBlocks" ],
      "allowedBlocks": [ "core/*" ],
      "blockSettings": {
        "core/*": {
          "color": {
            "text": true
          }
        },
        "core/quote": {
          "allowedBlocks": [ "core/paragraph", "core/heading" ]
        },
        "core/media-text": {
          "allowedBlocks": [ "core/paragraph", "core/heading" ]
        }
      }
    }
  ]
}
```

### Quick Start

By default, the plugin uses [this][repo-governance-file-location] `governance-rules.json`. To start using the plugin with your own rules, you'll need to create your own `governance-rules.json` in [your private folder][wpvip-private-dir]. We recommend duplicating one of the starter rule sets provided [below](#starter-rule-sets), and adapting it for your needs. In order to take advantage of the rules schema for in-editor support, use `https://api.wpvip.com/schemas/plugins/governance.json`.

With this default rule set, all blocks and all features are enabled. It is sensible to set your default rule to the settings you want for your least privileged user then add capabilities with role and/or post type-specific rules.

### Starter Rule Sets

Below is some rule sets that you can use to build your `governance-rules.json`. They cover a wide range of use cases.

#### Default Rule Set

This is the default rule set used by the plugin.

```json
{
  "$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
  "version": "1.0.0",
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

#### Default Rule Set With Restrictions

This expands the default rule set by adding restrictions for all users and post types.

```json
{
  "$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
  "version": "1.0.0",
  "rules": [
    {
      "type": "default",
      "allowedFeatures": [ "codeEditor", "lockBlocks" ],
      "allowedBlocks": [ "core/group", "core/heading", "core/paragraph", "core/image" ],
      "blockSettings": {
        "core/group": {
          "spacing": {
            "spacingSizes": [
              {
                "size": "clamp(2.5rem, 6vw, 3rem)",
                "slug": "300",
                "name": "12"
              }
            ]
          }
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
                "gradient": "linear-gradient(to bottom,#ff0000 0%,#00FF00 100%)",
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
    - The only blocks allowed are group, heading, paragraph and image. Under the group block, only heading, paragraph, image and group can be inserted.
    - The code editor is accessible, and blocks can be locked/unlocked or moved.
    - For a heading at the root level, there are 3 custom colors as well as a custom gradient that will show up in the color palette. In addition, a custom font called Code Font as well as 2 custom font sizes will show up in the typography panel.
    - For a group block, there will be only one option for a spacing size available in padding/margin and block spacing.

#### Default and User Role Rule Set

This example focuses on providing a restrictive default rule set, and expanded permissions for a specific user role.

```json
{
  "$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
  "version": "1.0.0",
  "rules": [
    {
      "type": "role",
      "roles": [ "administrator" ],
      "allowedFeatures": [ "codeEditor", "lockBlocks" ],
      "allowedBlocks": [ "core/quote", "core/media-text", "core/image" ],
      "blockSettings": {
        "core/media-text": {
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
    - For a heading at the root level, a custom yellow color will appear as a possible text color option.
    - Blocks cannot be locked/unlocked or moved.
    - The code editor is not accessible.
- Administrator role: Role-specific rules combined with the default set of rules:
    - In addition to the default allowed blocks, quote/media-text and image blocks are allowed as well. Both the quote, and media-text blocks are allowed to have heading, paragraph, and image blocks inserted under it.
    - A heading at the root level is a custom yellow color as a possible text color option.
    - A heading inside a media-text is allowed to have a custom red color.
    - A paragraph inside a quote is allowed to have a custom green color.
    - The code editor is accessible.
    - Blocks can be locked, unlocked, and moved.

#### Default and Post Type Rule Set

This example focuses on providing a restrictive default rule set, and expanded permissions for a specific post type.

```json
{
  "$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
  "version": "1.0.0",
  "rules": [
    {
      "type": "postType",
      "postTypes": [ "post" ],
      "allowedFeatures": [ "lockBlocks" ],
      "allowedBlocks": [ "core/quote", "core/image" ],
      "blockSettings": {
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
      "allowedFeatures": [ "codeEditor" ],
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
    - For a heading at the root level, a custom yellow color will appear as a possible text color option.
    - Blocks cannot be locked/unlocked or moved.
    - The code editor is accessible.
- Posts: Post specific rules combined with the default set of rules:
    - In addition to the default allowed blocks, quote and image blocks are allowed as well. A quote block is allowed to have heading, paragraph and if [cascading mode](#vip_governance__is_block_allowed_in_hierarchy) is enabled then an image block as well.
    - A heading at the root level is a custom yellow color as a possible text color option.
    - A paragraph inside a quote is allowed to have a custom green color.
    - The code editor is accessible.
    - Blocks can be locked, unlocked and moved.

#### Default Wildcard Rule Set

This example focuses on providing a default rule set, using wildcards within the `blockSettings` and `allowedBlocks`. The use of a wildcard is helpful in targetting a wide variety of blocks, with minimal configuration.

```json
{
  "$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
  "version": "1.0.0",
  "rules": [
    {
      "type": "default",
      "allowedFeatures": [ "codeEditor", "lockBlocks" ],
      "allowedBlocks": [ "core/*" ],
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
        },
        "core/quote": {
          "allowedBlocks": [ "core/paragraph", "core/heading" ],
          "core/*": {
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
    }
  ]
}
```

With this rule set, the following rules will apply:

- Default: Rules that apply to everyone as a baseline:
    - All core blocks are allowed
    - Within a quote block, only heading and paragraph is allowed
    - For a heading at the root level, a custom yellow color will appear as a possible text color option.
    - For a heading or paragraph within the quote block, a custom green color will appear as a possible text color option.
    - Blocks can be locked/unlocked or moved.
    - The code editor is accessible.

### Limitations

- We highly recommend including `core/paragraph` in `allowedBlocks` for the `default` rule so that all users have access to use paragraph blocks. There are some limitations with the editor that make this necessary:

    - The Gutenberg editor uses `core/paragraph` blocks as an insertion primitive. If a user is unable to insert paragraph blocks, then they will also be unable to insert any other block in the same place.
    - Some `core` blocks automatically insert `core/paragraph` blocks that can not be blocked by plugin code. For example, the `core/quote` block has a child `core/paragraph` block built-in to block output. Even if a user has `core/paragraph` blocks disabled, they may still be able to access built-in child blocks.

    It is possible to disable `core/paragraph` blocks for a role if it makes sense for your workflow but keep in mind these limitations when doing so.

- Support for `color.duotone` has not been implemented.
- `wp-env.json` within this plugin is intended for tests only, it doesn't work locally.
- Currently, the plugin is restricted to new and existing posts/pages only. So it won't work on the site-editor, widgets, etc. We have support coming in the future to make this possible.

## Code Filters

There are filters in place that can be applied to change the behavior for what's allowed and what's not allowed.

### `vip_governance__governance_file_path`

Change the governance rules file that's used by the plugin, based on a variety of filter options that are available. By default, it is set to the path to `governance-rules.json` in the private directory in a VIP site. For non-vip sites, it is set to the path to `governance-rules.json` in the plugin directory.

```php
/**
 * Filter the governance file path, based on the filter options provided.
 *
 * Currently supported keys:
 *
 * site_id: The site ID for the current site.
 *
 * @param string $governance_file_path Path to the governance file.
 * @param array $filter_options Options that can be used as a filter for determining the right file.
 */
apply_filters( 'vip_governance__governance_file_path', $governance_file_path, $filter_options );
```

For example, this filter can be used to customize the rules file used for a network site:

```php
add_filter( 'vip_governance__governance_file_path', function ( $governance_file_path, $filter_options ) {
    if ( isset( $filter_options['site_id'] ) && $filter_options['site_id'] === 2 ) {
        return WPCOM_VIP_PRIVATE_DIR . '/site/2/' . WPCOMVIP_GOVERNANCE_RULES_FILENAME;
    }

    return $governance_file_path;
}, 10, 2 );
```

### `vip_governance__governance_rules_json`

Change, or programmatically set the governance rules used by the plugin, based on a variety of filter options that are available. By default, the rules read from the `governance-rules.json` are set regardless of a site being VIP or non-vip.

```php
/**
 * Filter the governance rules, based on the filter options provided.
 *
 * Currently supported keys:
 *
 * site_id: The site ID for the current site.
 *
 * This filter can be used to either modify the governance rules content before it's parsed, or to generate the content dynamically.
 *
 * @param string $governance_rules_json Governance rules content.
 * @param array $filter_options Options that can be used as a filter for determining the right rules.
 */
apply_filters( 'vip_governance__governance_rules_json', $governance_rules_json, $filter_options );
```

For example, this filter can be used to programmatically set the rules used by the plugin instead of using the default rule set provided by the plugin:

```php
add_filter( 'vip_governance__governance_rules_json', function ( $governance_rules_json, $filter_options ) {
			return '{
				"$schema": "https://api.wpvip.com/schemas/plugins/governance.json",
				"version": "1.0.0",
				"rules": [
					{
					"type": "default",
					"allowedFeatures": [ "codeEditor", "lockBlocks" ],
					"allowedBlocks": [ "core/heading", "core/paragraph" ],
					"blockSettings": {
						"core/heading": {
						"typography": {
							"fontFamilies": [
							{
								"name": "Arial",
								"slug": "arial",
								"css": "Arial, sans-serif"
							}
							]
						}
						}
					}
					}
				]
			}';
		}, 10, 2 );
```

This way the `governance-rules.json` no longer needs to be created outside the plugin.

### `vip_governance__is_block_allowed_for_insertion`

Change what blocks are allowed to be inserted in the block editor. By default, root level and children blocks are compared against the governance rules, and then a decision is made to allow or reject them. This filter will allow you to override the default logic for insertion.

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

For example, this filter can be used to allow the insertion of a custom block even if it's not allowed by the governance rules:

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

Change what blocks are allowed to be edited in the block editor. Disabled blocks will display with a grey border and will not be editable. By default, root level and children blocks are compared against the governance rules, and then a decision is made to allow or reject them. This filter will allow you to override the default logic for editing.

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

For example, this filter can be used to allow the editing of a custom block type even if it is disabled by governance rules:

```js
addFilter(
    'vip_governance__is_block_allowed_for_editing',
    'example/allow-custom-block-editing',
    ( isAllowed, blockName, parentBlockNames, governanceRules ) => {
        if ( blockName === 'custom/my-amazing-block' ) {
            return true;
        }

        return isAllowed;
    }
);
```

### `vip_governance__is_block_allowed_in_hierarchy`

Select the mode that's used for determining if a block should be allowed or not, between cascading and restrictive. Cascading works similarly to CSS in that, the rules of the parent are looked up first, followed by the root-level rules for determining if the block is to be allowed or not. On the other hand, restrictive only looks up the rules under the parent. If there are no rules under a parent or a block is not allowed under a parent, then that block cannot be inserted. Cascading allows for a simpler rule file avoiding excessive repetition of blocks under a parent. Restrictive does result in more repetition in the rules file, but it results in a more locked-down editor experience. By default, the filter is set to cascading mode. Note that, you have access to the parent block names, block name, and the governance rules in order to decide what mode should be used. So you can fine tune the mode based on any of these values.

```js
/**
 * Select the mode used to determine if a block should be allowed or not, between cascading and restrictive.
 *
 * @param {bool}                      True, if cascading mode is to be used or false if restrictive is to be used.
 * @param {string}   blockName        The name of the block to be edited.
 * @param {string[]} parentBlockNames An array of zero or more parent block names,
 *                                    starting with the most recent parent ancestor.
 * @param {Object}   governanceRules  An object containing the full set of governance
 *                                    rules for the current user.
 */
  applyFilters(
    'vip_governance__is_block_allowed_in_hierarchy',
    true,
    blockName,
    parentBlockNames,
    governanceRules
  );
```

## Admin Settings

There is an admin settings menu titled `VIP Block Governance` that's created with the use of this plugin. This page offers:

- Turning on and off the plugin quickly, without re-deploying.
- View all the rules at once, and also any errors if the schema is invalid.
- View combined rules as a specific user role and/or for a specific post type.

![Admin setting in action][settings-panel-example-gif]

## Endpoints

### `vip-governance/v1/<role>/rules`

This endpoint is used to return the combined rules for a given role. This API is utilized by the settings page to visualize merged default and role rules for a selected role. It's only available to users with the `manage_options` permission.

It has only three root level keys: `allowedBlocks`, `blockSettings`, and `allowedFeatures`.

#### Example

This example involves making a call to `http://my.site/wp-json/vip-governance/v1/editor/rules` for an `editor` role, while using [this](#default-and-user-role-rule-set) rule file found in the starter rule sets:

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

**Please note this is for VIP sites only. Analytics are disabled if this plugin is not being run on VIP sites.**

The plugin records two data points for analytics, on VIP sites:

1. A usage metric when the block editor is loaded with the WordPress VIP Block Governance plugin activated. This analytic data simply is a counter, and includes no information about the post's content or metadata. It will only include the customer site ID to associate the usage.

2. When an error occurs from within the plugin on the [WordPress VIP][wpvip] platform. This is used to identify issues with customers for private follow-up.

Both of these data points are a counter that is incremented and do not contain any other telemetry or sensitive data. You can see what's being [collected in code here][repo-analytics].

## Development

In order to ensure no dev dependencies are installed, the following can be done while installing the packages:

```
composer install --no-dev
```

### Tests

We currently have unit, and e2e tests to ensure thorough code coverage of the plugin. These tests can be run locally with [`wp-env`][wp-env] and Docker.

For the PHP unit tests:

```
wp-env start
composer install
composer run test
```

For the JS unit tests:

```
npm install
npm run test:js
```

For the e2e tests:

```
wp-env start
composer install
npm install
npx playwright install chromium --with-deps
npx playwright test
```

<!-- Links -->

[playground-blueprint]: https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Automattic/vip-governance-plugin/trunk/blueprint.json
[settings-panel-example-gif]: https://github.com/automattic/vip-governance-plugin/blob/media/vip-governance-admin-settings-animation.gif
[analytics-file]: governance/analytics.php
[repo-governance-file-location]: governance-rules.json
[repo-schema-location]: governance-schema.json
[gutenberg-block-settings]: https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-json/#settings
[repo-analytics]: governance/analytics.php
[repo-releases]: https://github.com/automattic/vip-governance-plugin/releases
[vip-go-mu-plugins]: https://github.com/Automattic/vip-go-mu-plugins/
[wp-custom-roles]: https://developer.wordpress.org/reference/functions/add_role/
[wp-default-roles]: https://wordpress.org/documentation/article/roles-and-capabilities/
[wp-custom-post-types]: https://developer.wordpress.org/reference/functions/register_post_type/
[wp-default-post-types]: https://developer.wordpress.org/themes/basics/post-types/
[wp-env]: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/
[wpvip-page-cache]: https://docs.wpvip.com/technical-references/caching/page-cache/
[wpvip-plugin-activate]: https://docs.wpvip.com/how-tos/activate-plugins-through-code/
[wpvip-plugin-submodules]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-submodules
[wpvip-plugin-subtrees]: https://docs.wpvip.com/technical-references/plugins/installing-plugins-best-practices/#h-subtrees
[wpvip-private-dir]: https://docs.wpvip.com/technical-references/vip-codebase/private-directory/
[wpvip]: https://wpvip.com/
