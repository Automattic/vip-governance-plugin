# VIP Governance plugin

This is a WordPress plugin that's meant to add in governance within the Block Editor, specifically for two dimensions:

- Insertion: This is the ability to control the blocks that are allowed to be inserted.
- Interaction: This is the ability to control the styling available for nested blocks.

## Requirements

This plugin requires `governance-rules.json` to exist in the private folder, or within the plugin itself to work. 

### Example & Schema Validation

Check out [this sample file](https://github.com/Automattic/vip-governance-plugin/blob/trunk/governance-rules.json) to see an example of what this looks like.

If that above sample file is used, the rules are as follows:

- Default: This is going to apply to everyone as a baseline. Heading/paragraph blocks are allowed, and for a heading a custom red colour will appear as a possible text colour option.
- Editor/Administrator role: Besides the default allowed blocks, quote/media-text and image blocks will be allowed as well. A heading sitting inside a media-text will be allowed to have a custom red colour as it's text. But the default rule of custom red for a heading at the root level will nto appear.

There's also a schema available [here](https://github.com/Automattic/vip-governance-plugin/blob/trunk/governance-schema.json), to help you craft up a custom rules file.

## Setup

Place this plugin in the plugins directory of your site and run the following:

```bash
$ npm install
$ npm run build

# For watching and building on change:
$ npm start
```