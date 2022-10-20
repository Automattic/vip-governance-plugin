# VIP Governance plugin

This is a plugin that's meant to add in Governance features into Gutenberg. At the moment, it adds the ability to style nested blocks using a file called `block-governance.json` within your site's theme.

## Setup

At the moment, this plugin depends on [this PR into Gutenberg](https://github.com/WordPress/gutenberg/pull/45089). So that branch would need to be cloned, and placed into the plugins folder of your site.

Place this folder in the plugins directory of your site and run the following:

```bash
$ npm install
$ npm run build

# For watching and building on change:
$ npm start
```

Ensure that both these plugins are then activated for your site.