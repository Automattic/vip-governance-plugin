# VIP Governance plugin

This is a plugin that's meant to add in Governance features into Gutenberg. At the moment, it adds the ability to style nested blocks using a file called `block-governance.json` within your site's theme.

## Setup

At the moment, this plugin depends on 2 PRs into Gutenberg:

- [PR 1](https://github.com/WordPress/gutenberg/pull/45089)
- [PR 2](https://github.com/WordPress/gutenberg/pull/45505)

The changes from both those branches would be needed in your copy of Gutenberg, or else your site will not work.

Place this plugin in the plugins directory of your site and run the following:

```bash
$ npm install
$ npm run build

# For watching and building on change:
$ npm start
```

Ensure that both Gutenberg and this plugin are then activated for your site.