# WordPress VIP Block Governance plugin

This WordPress plugin adds additional governance capabilities to the block editor. This is accomplished via three dimensions:

- Insertion: restricts what kind of blocks can be inserted into the block editor. Only what’s allowed can be inserted, and nothing else. This means that even if new core blocks are introduced they would not be permitted.
- Interaction: This adds the ability to control the styling available for blocks at any level.
- Features: controls access to the code editor and block locking.

We have approached this plugin from an opt-in standpoint. An empty effective rule set severely limits the editing experience, although the plugin ships with a permissive fallback rule set. The goal is to create a stable editor with new blocks and features being enabled explicitly via rules, rather than implicitly via updates.

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
	- [`vip_governance__default_role_for_user_without_roles`](#vip_governance__default_role_for_user_without_roles)
- [Admin Settings](#admin-settings)
- [Endpoints](#endpoints)
	- [`vip-governance/v1/rules`](#vip-governancev1rules)
		- [Example](#example)
- [Analytics](#analytics)
- [Development](#development)
	- [Tests](#tests)

## Try it out

Try out the VIP Governance plugin in your browser [with WordPress Playground][playground-blueprint].

## Installation

To use the WordPress VIP Block Governance plugin after activation, skip to [Usage](#usage).

### Install on WordPress VIP

The WordPress VIP Block Governance plugin is authored and maintained by [WordPress VIP][wpvip], and made available to all WordPress sites by the  [VIP Integrations Center][vip-ic]. Customers who host on WordPress VIP or use [`vip dev-env`](https://docs.wpvip.com/how-tos/local-development/use-the-vip-local-development-environment/) to develop locally have access to this plugin automatically. We recommend this activation method for WordPress VIP customers.

Enable the Integration by [adding it to your organization][vip-ic-org]. Once that is complete, you can [activate the integration on your application][vip-ic-app]. Activation is for the current environment only, so you may need to activate the Integration on multiple environments.

### Install via ZIP file

The latest version of the plugin can be downloaded from the [repository's Releases page][repo-releases]. Unzip the downloaded plugin and add it to the `plugins/` directory of your site's GitHub repository.

## Usage

On WordPress VIP, place your governance rules in `governance-rules.json` in [your private folder][wpvip-private-dir]. When that file does not exist, the plugin uses the permissive [`governance-rules.json` bundled with the plugin][repo-governance-file-location]. The source can also be changed with the filters described below.

Note: The [private folder][wpvip-private-dir] is only supported on VIP sites, or while using [`vip dev-env`](https://docs.wpvip.com/how-tos/local-development/use-the-vip-local-development-environment/) locally.

### Schema Basics

You can find the JSON Schema for authoring rules [here][repo-schema-location]. Use `https://api.wpvip.com/schemas/plugins/governance.json` as the `$schema` value to get code completion and validation in supported editors. At runtime, the plugin parses JSON, rejects unrecoverable configuration errors, and corrects recoverable rule problems rather than evaluating this schema file directly.

We have allowed significant space for customization. This means it is also possible to create unintended rule interactions. We recommend making rule changes one or two at a time to troubleshoot these interactions.

Each rule is an object in an array. The one required property is `type`, which can be `default`, `role`, or `postType`. At most one `default` rule is allowed. Although the parser accepts an empty file or object as no rules, use a functional default rule to define the intended editor configuration explicitly. When no usable rules remain, the editor uses an equivalent permissive fallback so an invalid configuration does not unexpectedly restrict customers.

Rules not of type `default` require an additional field. These are broken down below, along with examples of their possible values:

| Rule Type  | Required Field | Possible Values                                                                                |
| ---------- | -------------- | ---------------------------------------------------------------------------------------------- |
| `role`     | `roles`        | name/slug of any [default][wp-default-roles] or [custom][wp-custom-roles] roles                |
| `postType` | `postTypes`    | name/slug of any [default][wp-default-post-types] or [custom][wp-custom-post-types] post types |

Each rule can have any of the following properties.

- `allowedFeatures`: This is an array of the features that are allowed in the block editor. This list will expand with time, but we currently support two values: `codeEditor` (viewing the content of your post as code in the editor) and `lockBlocks` (ability to lock/unlock blocks that restrict movement/deletion). Use an empty array or omit the property when no optional features should be enabled by that rule.
- `blockSettings`: These are specific settings related to the styling available for a block. They match the settings available in theme.json under the key `blocks`. The definition for that can be [found here][gutenberg-block-settings]. Unlike theme.json, you can nest these rules under a block name to apply different settings depending on the parent of a particular block.
- `allowedBlocks`: These are the blocks allowed to be inserted into the block editor. You can also put `allowedBlocks` in an exact parent's `blockSettings`. In the default cascading mode this adds child candidates to the root list; use restrictive hierarchy mode when the parent list must be exclusive.

#### Runtime Parsing

The runtime parser distinguishes fatal configuration failures from problems that can be safely corrected:

- Malformed JSON, a missing or unusable root `version` or `rules` value, and multiple `default` rules reject the entire configuration.
- Individual rules with an invalid type or no usable settings are dropped without preventing other valid rules from loading.
- Safe corrections include converting string list values to arrays, removing invalid or duplicate list entries, removing unsupported features and properties, and retaining valid portions of `blockSettings`.
- Repairs and dropped rules are reported as ordinal warnings on the settings page, such as `3rd rule: removed 1 invalid allowedBlocks value.` These warnings are not passed to the block editor as errors.

Valid configurations are preserved and applied normally. If a mixture of valid and invalid rules is supplied, the valid rules continue to apply after the invalid portions are repaired or dropped. If nothing usable remains, the editor receives a permissive fallback that allows all blocks, the code editor, and block locking. The REST v1 combined-rules endpoint continues to return an empty array for that case.

Non-default rule types are combined with the default rule to avoid needless repetition. Matching non-default rules have the following ascending priority:

1. Post Type
2. Role

Only the first matching rule of each type in file order is used. A matching role rule replaces each field it defines from the matching post-type rule; fields omitted from the role rule retain the post-type value. The default rule is then additive: its `allowedBlocks` and `allowedFeatures` are appended, and its `blockSettings` are recursively merged. If a user has multiple roles, order role rules carefully because the first intersecting role rule wins.

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

By default, the plugin uses [this][repo-governance-file-location] `governance-rules.json`. To start using the plugin with your own rules, create `governance-rules.json` in [your private folder][wpvip-private-dir]. We recommend duplicating one of the starter rule sets provided [below](#starter-rule-sets) and adapting it for your needs. For in-editor schema support, use `https://api.wpvip.com/schemas/plugins/governance.json`.

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
    - In the default cascading mode, all core blocks remain eligible within a quote; heading and paragraph are also explicitly allowed there. Return `false` from [`vip_governance__is_block_allowed_in_hierarchy`](#vip_governance__is_block_allowed_in_hierarchy) to make the parent list restrictive.
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
- Currently, the plugin is restricted to the post editor only and won't work on other pages like site-editor, widgets, etc.
- Starting from WordPress 6.8, the block inserter sidebar will show all the blocks regardless of the ability to insert them or not. Upon attempting to insert a block that isn't allowed, a snackbar will show up in the lower left corner to highlight that this isn't possible. The regular `/blockname` approach will work just fine, and would be the recommended way of inserting blocks when using this plugin.

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

### `vip_governance__default_role_for_user_without_roles`

**Since:** 1.1.0

Provide an alternative role to use when a user has no assigned roles. In WordPress multisite environments, superadmins may have no role for a specific site. This filter allows custom code to provide an alternative role to use instead of falling back to the default ruleset.

**Security Note:** This filter only applies when a user has no assigned roles. Returned roles are validated against existing WordPress roles - invalid or non-existent roles are ignored and the default ruleset is used. Only trusted plugins should hook into this filter, as it can affect governance rule assignment.

```php
/**
 * Filter the role to use when a user has no assigned roles.
 *
 * In WordPress multisite environments, superadmins may have no role for a specific site.
 * This filter allows custom code to provide an alternative role to use instead of
 * falling back to the default ruleset.
 *
 * @param string|array|null $default_role The role(s) to use when user has no roles. Can be a single role string, array of roles, or null to use default ruleset.
 * @param WP_User $current_user The current user object.
 * @param int $site_id The current site ID.
 */
apply_filters( 'vip_governance__default_role_for_user_without_roles', null, $current_user, $site_id );
```

**Return values:**
- `string`: Single role name (e.g., `'editor'`)
- `array`: Multiple role names (e.g., `['editor', 'custom_role']`)
- `null`: Use default ruleset (no custom role assignment)

**Examples:**

**Example 1:** Assign administrator role to superadmins in multisite when they have no site-specific role:

```php
add_filter( 'vip_governance__default_role_for_user_without_roles', function( $default_role, $current_user, $site_id ) {
    // For superadmins in multisite, use administrator role
    if ( is_multisite() && is_super_admin( $current_user->ID ) ) {
        return 'administrator';
    }

    return $default_role; // null to use default ruleset
}, 10, 3 );
```

**Example 2:** Site-specific roles based on site ID:

```php
add_filter( 'vip_governance__default_role_for_user_without_roles', function( $default_role, $current_user, $site_id ) {
    if ( is_multisite() && is_super_admin( $current_user->ID ) ) {
        // Assign different roles based on site
        return match ( $site_id ) {
            1 => 'administrator',      // Main site
            2 => 'editor',              // Staging site
            default => 'contributor',   // Other sites
        };
    }
    return $default_role;
}, 10, 3 );
```

**Example 3:** Assign multiple roles:

```php
add_filter( 'vip_governance__default_role_for_user_without_roles', function( $default_role, $current_user, $site_id ) {
    if ( is_super_admin( $current_user->ID ) ) {
        return array( 'editor', 'custom_role' );
    }
    return $default_role;
}, 10, 3 );
```

## Admin Settings

There is an admin settings menu titled `VIP Block Governance` that's created with the use of this plugin. This page offers:

- Turning on and off the plugin quickly, without re-deploying.
- View all the rules at once, including fatal JSON or rule-logic parsing errors and non-fatal parser warnings.
- View combined rules as a specific user role and/or for a specific post type.

Governance validation has three states:

- `❌ Failed to load`: The configuration contains a fatal error and no rules are applied.
- `⚠️ Rules loaded with warnings`: Usable rules continue to apply. Rule-level warnings identify the rule ordinal and, where relevant, the nested property path.
- `✅ Rules loaded successfully`: The configuration loaded without any corrections.

Warnings are escaped and displayed only on this server-rendered validation page. They do not change the editor-side `VIP_GOVERNANCE` payload. The combined-rules preview remains available when warnings exist.

![Admin setting in action][settings-panel-example-gif]

## Endpoints

### `vip-governance/v1/rules`

This endpoint returns effective rules for an optional role and/or post type. The settings page uses it only for the combined-rules preview; settings validation and parser warnings are rendered separately on the server. It is available only to users with the `manage_options` capability.

Pass `role` and `postType` as query parameters. Each is optional, and supplied values must name a registered WordPress role or post type. If `role` is omitted, resolution uses the authenticated user's roles:

```text
GET /wp-json/vip-governance/v1/rules?role=editor&postType=post
```

It has only three root-level keys: `allowedBlocks`, `blockSettings`, and `allowedFeatures`. Parser warnings are not included, so the v1 response shape remains unchanged when rules are repaired or dropped.

#### Example

This example is the response to `http://my.site/wp-json/vip-governance/v1/rules?role=editor` while using [the role starter rule set](#default-and-user-role-rule-set):

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

1. A usage metric sampled on roughly 10% of governance configuration loads. It is a counter associated with the customer site ID and includes no post content or metadata.

2. When an error occurs from within the plugin on the [WordPress VIP][wpvip] platform. This is used to identify issues with customers for private follow-up.

Both data points are counters and do not contain other telemetry or sensitive data. If usage and error events are queued in the same request, only the error is sent. You can see what's being [collected in code here][repo-analytics].

## Development

Development uses Node.js 24 (see `.nvmrc`), npm, Composer, Docker, and `wp-env`.

Install development dependencies with:

```bash
npm ci
composer install
```

The npm `postinstall` script installs production Composer dependencies, and the explicit `composer install` adds PHPUnit and PHPCS. Production installations can omit those development tools with `composer install --no-dev`.

### Tests

The PHP, JavaScript, and end-to-end tests can be run locally with [`wp-env`][wp-env] and Docker.

The default configuration starts a development site at `http://localhost:8888`. Automated tests use an isolated configuration at `http://localhost:8889`, which mounts the governance fixture from `tests/private` without affecting the development site.

For the PHP unit tests:

```
npx wp-env start --config=.wp-env.test.json
composer run test
```

For the JS unit tests:

```
npm run test:js
```

For the e2e tests:

```
npx wp-env start --config=.wp-env.test.json
npx playwright install chromium --with-deps
npm run test:e2e
```

Run multisite PHP coverage with `composer run test-multisite`. Playwright uses the isolated test site at `http://localhost:8889`, with `admin` / `password` as the default credentials.

Run both PHP and JavaScript unit tests with `npm test` after the isolated test environment is running.

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
[vip-ic]:https://docs.wpvip.com/integrations/center/
[vip-ic-org]:https://docs.wpvip.com/integrations/org-integrations/
[vip-ic-app]:https://docs.wpvip.com/integrations/app-integrations/
