# Maintaining nested governance

`NestedGovernanceProcessing` inherits WordPress's preset helpers and keeps only governance-specific traversal, origin wrapping, selector handling, and CSS ordering. The renderer normalizes settings and generates CSS in one traversal without constructing an intermediate `theme.json` tree or collecting node paths.

The comparison baseline is plugin trunk `5a55166` and [WordPress 6.8's `WP_Theme_JSON`](https://github.com/WordPress/WordPress/blob/6ba3d560fc2b033654f6dbfc6093fefb5c50f148/wp-includes/class-wp-theme-json.php).

## What comes from core

These four protected static helpers are inherited directly, so their algorithms no longer need to be copied or synchronized:

- `get_settings_values_by_slug()` resolves values, normalizes slugs, and supplies the block settings to preset callbacks.
- `get_settings_slugs()` de-duplicates slugs for preset classes.
- `replace_slug_in_string()` expands property and class templates.
- `to_ruleset()` serializes CSS with the existing formatting.

Governance explicitly requests only the `theme` origin. Preset metadata still comes from `WP_Theme_JSON_Gutenberg::PRESETS_METADATA` when Gutenberg is active, otherwise from core. Metadata-provided callbacks and paths are passed through to the inherited helpers.

WordPress [documents `WP_Theme_JSON` as internal](https://developer.wordpress.org/reference/classes/wp_theme_json/). Selective inheritance is a deliberate dependency on protected core APIs, not a supported public API guarantee. The renderer never constructs a `WP_Theme_JSON` instance, sanitizes governance rules through its constructor, or uses production reflection. Test the inherited method signatures and output on the minimum and current supported versions whenever those versions change.

## What stays local

| Code                                                                                  | Reason                                                                                                                                                                               |
| ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Nested rule traversal                                                                 | Core's constructor removes governance's arbitrary nested block keys. A single local traversal preserves the existing rules and their order.                                          |
| `scope_nested_selector()`, `split_nested_selector_list()`, `append_nested_selector()` | WordPress 6.8 splits selectors on every comma, breaking functional selectors and quoted attribute values. Local names also avoid overriding newer core selector methods.             |
| Preset wrapping and CSS assembly                                                      | Governance wraps unkeyed presets under `theme` and emits each preset's variables followed by its classes. Core's full stylesheet generator groups variables and classes differently. |
| Wildcard selectors                                                                    | Preserve trunk's broad `p, [class*=wp-block]` selector, including its namespace limitation.                                                                                          |
| Request cache                                                                         | Preserve trunk's first-result cache behavior. Processing must not modify the caller's settings.                                                                                      |

`wp_style_engine_get_stylesheet_from_css_rules()` is not a replacement for the serializer: it changes formatting and filters declarations. Adding adapters to undo that output would not reduce maintenance.

Newer core versions have a protected selector-list parser, but it is absent in 6.8. Revisit that reuse when the minimum WordPress version increases; verify all selector fixtures before removing the local parser.

## Font-size correction

The old port omitted the second callback argument added by core in 6.6. The inherited implementation now supplies the block settings, correcting fluid calculations to use governance's typography and layout settings.

For the `fluid typography settings` fixture:

| Before | After                                                     |
| ------ | --------------------------------------------------------- |
| `48px` | `clamp(27.894px, 1.743rem + ((1vw - 4px) * 3.351), 48px)` |

A setting omitted from governance retains the context WordPress used to emit that preset. An inherited root preset keeps its root context unless a native block preset overrides the same slug. Native block presets use core's shallow root/block merge; newly introduced presets also use that block context. Explicit governance settings recursively override the selected context, so partial governance configurations retain unspecified native bounds and explicit opt-outs remain effective. Only governance's declared presets are emitted; inherited defaults do not add presets to governed output.

The public output tests compare omitted fluid settings with WordPress's actual generated CSS, including mixed inherited and native preset lists, unrelated native typography settings, explicit opt-outs, explicit min/max sizes, and partial viewport bounds. Both a universal recursive merge and a universal shallow merge change native output in these cases; callback defaults are selected per preset without copying core's preset-value algorithm.

## Frontend lookup

Core's `blockEditor.useSetting.before` filter remains the integration point. Core's normal lookup does not implement governance's block-name ancestor rules, wildcard precedence, or nested `allowedBlocks`.

The resolver now iterates over distinct matching rule objects rather than recursively revisiting equivalent ancestor paths. It keeps the deepest match and preserves trunk's later-match tie breaking. Property lookup is iterative too, preserving falsy values, inherited properties, and existing bracket-path behavior.

A snapshot captured before the hierarchy rewrite covers all 63 group/quote ancestor combinations through depth five. A deterministic comparison of 10,000 generated settings/hierarchy cases against trunk also matched. The repeated-group regression test returns the same value while reducing rule-property reads from 1,585 to 50; its bound is based on reads rather than timing.

The hardcoded core-child exceptions in `block-utils.ts` are unchanged. Replacing them with each registered block's `allowedBlocks` metadata would change which blocks bypass governance, so that is not a behavior-preserving reduction.

Browser verification in Helium on WordPress 6.8 and 7.1.4 exercises ten resolver scenarios with actual block-editor store selectors, comparing trunk and current results with explicit expected values. These cover ancestor order, deepest and equal-depth precedence, skipped and repeated ancestors, wildcard precedence, false/null values, empty lists, and native fallback. The installed plugin hook also returns the expected root and nested palettes; selecting the nested custom color updates both the block attribute and its computed canvas color. This is a focused editor integration check, not a full E2E suite run.

## Tests and future updates

`tests/private/nested-governance-expected.json` remains the original, unchanged trunk output captured under the WordPress 6.8 PHPUnit bootstrap. Normal site bootstraps can have different global theme settings and are not suitable for recapturing these fixtures.

`tests/NestedGovernanceCompatibilityTest.php` uses named cases to compare complete output and input preservation in one renderer call. The original 13 fixture pairs remain unchanged; the authorized font-size correction is an explicit expected-CSS override in the provider. Two additional cases retain the old real-core-selector test and check a root compound selector, replacing the overlapping private-selector tests. Named font-size cases verify rendered CSS directly, without reflecting into inherited WordPress helpers. Request caching has one focused test.

When updating WordPress or Gutenberg:

1. Review changes to the inherited helpers and preset metadata in the supported core versions, beginning with the 6.8 baseline. Update this document when the integration changes.
2. Run `composer test -- --filter=NestedGovernance` and `composer test-multisite -- --filter=NestedGovernance` in the configured minimum and current WordPress environments.
3. Run `npm run test:js -- --runInBand`, `npm run lint:js`, `npm run typecheck`, and `npm run build` when changing the frontend resolver. Include the generated bundles.
4. Treat any changed golden output or precedence snapshot as a behavior change to investigate. Do not regenerate expectations to make a refactor pass.
5. Keep version-specific failures visible. Do not add a copied helper or broad fallback to hide a core change without documenting its exact compatibility impact.

The Gutenberg test environment loads the official latest stable ZIP through `.wp-env.gutenberg.json`. Run `npx wp-env start --config=.wp-env.gutenberg.json`, then `composer test-gutenberg` and `composer test-multisite-gutenberg`. These commands explicitly load Gutenberg in PHPUnit's separate bootstrap and fail if its theme JSON class does not initialize. The ZIP is mounted by wp-env as `gutenberg.latest-stable`, which is the directory the bootstrap loads. Keep the core-only config separate: current Gutenberg requires a newer WordPress version than our 6.8 minimum. CI runs the Gutenberg suites on latest WordPress with PHP 8.2 and 8.5, alongside the existing core matrix.

The final patch passes 149 tests and 262 assertions in each of eighteen local runs: WordPress 7.1.4 on PHP 8.2–8.5, both site modes, with and without Gutenberg 24.4.1, plus WordPress 6.8 on PHP 8.2 in both site modes. The Gutenberg ZIP-loaded editor also passes the same ten real-store resolver scenarios, installed-plugin palette/fallback checks, and nested color-selection/computed-color check in Helium. These are local results; the updated GitHub workflow has not been run remotely.
