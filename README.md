# VIP Governance plugin

This is a plugin meant to enhance Gutenberg to add in governance features, that will provide the following:

- Control the styling available for nested blocks.
- Control the blocks that are allowed to be inserted, including the children as well.

## Requirements

This plugin requires 2 files at the moment:

`insertions-governance.json`

The format for this can be in two ways:

This format allows you to specify what blocks are allowed. Everything not mentioned here will be blocked.

```json
{
    "allowed": [
        {
            "blockName": "core/quote",
            "children": [
                {
                    "blockName": "core/paragraph"
                }
            ]
        },
        {
            "blockName": "core/paragraph"
        },
        {
            "blockName": "core/heading"
        },
        {
            "blockName": "core/media-text",
            "children": [
                {
                    "blockName": "core/paragraph"
                },
                {
                    "blockName": "core/heading"
                }
            ]
        }
    ]
}
```

This format allows you to specify what blocks are blocked. Everything not mentioned here will be allowed. 

Note: Specifying the children here means that the block itself is allowed, but there's restrictions on what children are allowed within that block.

```json
{
    "blocked": [
        {
            "blockName": "core/quote"
        },
        {
            "blockName": "core/media-text",
            "children": [
                {
                    "blockName": "core/quote"
                }
            ]
        }
    ]
}
```

`interactions-governance.json`

```json
{
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
	}
}
```

## Setup

Place this plugin in the plugins directory of your site and run the following:

```bash
$ npm install
$ npm run build

# For watching and building on change:
$ npm start
```