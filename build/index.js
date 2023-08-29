/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/block-locking.jsx":
/*!*******************************!*\
  !*** ./src/block-locking.jsx ***!
  \*******************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "setupBlockLocking": function() { return /* binding */ setupBlockLocking; }
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/hooks */ "@wordpress/hooks");
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_hooks__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/compose */ "@wordpress/compose");
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_compose__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _block_utils__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./block-utils */ "./src/block-utils.js");

/**
 * WordPress dependencies
 */






/**
 * Internal dependencies
 */

function setupBlockLocking(governanceRules) {
  const withDisabledBlocks = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_3__.createHigherOrderComponent)(BlockEdit => {
    return props => {
      const {
        name: blockName,
        clientId
      } = props;
      const {
        getBlockParents,
        getBlockName
      } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_5__.select)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4__.store);
      const parentClientIds = getBlockParents(clientId, true);
      const isParentLocked = parentClientIds.some(parentClientId => isBlockLocked(parentClientId));
      if (isParentLocked) {
        // To avoid layout issues, only disable the outermost locked block
        return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(BlockEdit, props);
      }
      const parentBlockNames = parentClientIds.map(parentClientId => getBlockName(parentClientId));
      let isAllowed = (0,_block_utils__WEBPACK_IMPORTED_MODULE_6__.isBlockAllowedInHierarchy)(blockName, parentBlockNames, governanceRules);

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
      isAllowed = (0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_2__.applyFilters)('vip_governance__is_block_allowed_for_editing', isAllowed, blockName, parentBlockNames, governanceRules);
      if (isAllowed) {
        return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(BlockEdit, props);
      } else {
        // Mark block as locked so that children can detect they're within an existing locked block
        setBlockLocked(clientId);
        return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Disabled, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
          style: {
            opacity: 0.6,
            'background-color': '#eee',
            border: '2px dashed #999'
          }
        }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(BlockEdit, props))));
      }
    };
  }, 'withDisabledBlocks');
  (0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_2__.addFilter)('editor.BlockEdit', 'wpcomvip-governance/with-disabled-blocks', withDisabledBlocks);
}

/**
 * In-memory map of block clientIds that have been marked as locked.
 *
 * This replaces using props.setAttributes() to set lock status, as this caused an
 * "unsaved changes" warning to appear in the editor when block locking was in use.
 */
const lockedBlockMap = {};

/**
 * Marks a block as locked via the block's clientId.
 *
 * @param {string} clientId Block clientId in editor
 * @returns {void}
 */
function setBlockLocked(clientId) {
  lockedBlockMap[clientId] = true;
}

/**
 * Returns true if a block has previously been marked as locked, false otherwise.
 *
 * @param {string} clientId Block clientId in editor
 * @returns {boolean}
 */
function isBlockLocked(clientId) {
  return clientId in lockedBlockMap;
}

/***/ }),

/***/ "./src/block-utils.js":
/*!****************************!*\
  !*** ./src/block-utils.js ***!
  \****************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "doesBlockNameMatchBlockRegex": function() { return /* binding */ doesBlockNameMatchBlockRegex; },
/* harmony export */   "isBlockAllowedByBlockRegexes": function() { return /* binding */ isBlockAllowedByBlockRegexes; },
/* harmony export */   "isBlockAllowedInHierarchy": function() { return /* binding */ isBlockAllowedInHierarchy; }
/* harmony export */ });
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/hooks */ "@wordpress/hooks");
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _nested_governance_loader__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./nested-governance-loader */ "./src/nested-governance-loader.js");



// The list of default core blocks that should be allowed to be inserted, in order to make life easier.
const DEFAULT_CORE_BLOCK_LIST = {
  'core/list': ['core/list-item'],
  'core/columns': ['core/column'],
  'core/page-list': ['core/page-list-item'],
  'core/navigation': ['core/navigation-link', 'core/navigation-submenu']
};

/**
 * Given a block name, a parent list and a set of governance rules, determine if
 * the block can be inserted.
 *
 * By default, will return if the block is allowed to be inserted at the root level
 * per the user's rules. If a parent block contains a rule for allowedBlocks,
 * the function will return if the block is allowed as a child of that parent.
 *
 * Rules declared in allowedBlocks will override root level rules when the block
 * is currently a child of the parent with allowedBlocks.
 *
 * @param {string}   blockName        The current block's name.
 * @param {string[]} parentBlockNames A list of zero or more parent block names,
 *                                    starting with the most recent parent ancestor.
 * @param {Object}   governanceRules  An object containing the full set of governance
 *                                    rules for the current user.
 * @returns True if the block is allowed in set of parent blocks, or false otherwise.
 */
function isBlockAllowedInHierarchy(blockName, parentBlockNames, governanceRules) {
  // Filter to decide if the mode should be cascading or restrictive, where true is cascading and false is restrictive.
  const isInCascadingMode = (0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.applyFilters)('vip_governance__is_block_allowed_in_hierarchy', true, blockName, parentBlockNames, governanceRules);

  // Build the blocks that are allowed using the root level blocks for cascading mode or if no parent has been past, or empty otherwise.
  const blocksAllowedToBeInserted = isInCascadingMode || parentBlockNames.length === 0 ? [...governanceRules.allowedBlocks] : [];

  // Only execute this if we know we have blockSettings to check against.
  if (governanceRules.blockSettings && parentBlockNames.length > 0) {
    // Shortcircuit the parent-child hierarchy for some core blocks
    if (DEFAULT_CORE_BLOCK_LIST[parentBlockNames[0]] && DEFAULT_CORE_BLOCK_LIST[parentBlockNames[0]].includes(blockName)) {
      return true;
    }

    // Get the child block's parent block settings at whatever depth its located at.
    const nestedSetting = (0,_nested_governance_loader__WEBPACK_IMPORTED_MODULE_1__.getNestedSetting)(parentBlockNames.reverse(), 'allowedBlocks', governanceRules.blockSettings);

    // If we found the allowedBlocks for the parent block, add that to the array of blocks that can be inserted.
    if (nestedSetting && nestedSetting.value) {
      blocksAllowedToBeInserted.push(...nestedSetting.value);
    }
  }

  // Check if the block is allowed using the array of blocks that can be inserted.
  return isBlockAllowedByBlockRegexes(blockName, blocksAllowedToBeInserted);
}

/**
 * Matches a block name to a list of block regex rules.
 * For regex rules, see doesBlockNameMatchBlockRegex().
 *
 * @param {string} blockName
 * @param {string[]} rules
 * @returns True if the block name matches any of the rules, false otherwise.
 */
function isBlockAllowedByBlockRegexes(blockName, rules) {
  return rules.some(rule => doesBlockNameMatchBlockRegex(blockName, rule));
}

/**
 * Matches a rule to a block name, with the following cases being possible:
 *
 * 1. ['*'] - matches all blocks
 * 2. '*' can be located somewhere else alongside a string, e.g. 'core/*' - matches all core blocks
 * 3. ['core/paragraph'] - matches only the core/paragraph block
 *
 * @param {string} blockName
 * @param {string} rule
 * @returns True if the block name matches the rule, or false otherwise
 */
function doesBlockNameMatchBlockRegex(blockName, rule) {
  if (rule.includes('*')) {
    // eslint-disable-next-line security/detect-non-literal-regexp
    return blockName.match(new RegExp(rule.replace('*', '.*')));
  }
  return rule === blockName;
}

/***/ }),

/***/ "./src/nested-governance-loader.js":
/*!*****************************************!*\
  !*** ./src/nested-governance-loader.js ***!
  \*****************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "getNestedSetting": function() { return /* binding */ getNestedSetting; },
/* harmony export */   "getNestedSettingPaths": function() { return /* binding */ getNestedSettingPaths; }
/* harmony export */ });
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! lodash */ "lodash");
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(lodash__WEBPACK_IMPORTED_MODULE_0__);

const getNestedSettingPaths = function (nestedSettings) {
  let nestedMetadata = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
  let currentBlock = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;
  const SETTINGS_TO_SKIP = ['allowedBlocks'];
  for (const [settingKey, settingValue] of Object.entries(nestedSettings)) {
    if (SETTINGS_TO_SKIP.includes(settingKey)) {
      continue;
    }
    const isNestedBlock = settingKey.includes('/');
    if (isNestedBlock) {
      // This setting contains another block, look at the child for metadata
      Object.entries(nestedSettings).forEach(_ref => {
        let [blockName, blockNestedSettings] = _ref;
        if (!SETTINGS_TO_SKIP.includes(blockName)) {
          getNestedSettingPaths(blockNestedSettings, nestedMetadata, blockName);
        }
      });
    } else if (currentBlock !== false) {
      var _nestedMetadata$curre;
      // This is a leaf block, add setting paths to nestedMetadata
      const settingPaths = flattenSettingPaths(settingValue, `${settingKey}.`);

      // eslint-disable-next-line security/detect-object-injection
      nestedMetadata[currentBlock] = {
        // eslint-disable-next-line security/detect-object-injection
        ...((_nestedMetadata$curre = nestedMetadata[currentBlock]) !== null && _nestedMetadata$curre !== void 0 ? _nestedMetadata$curre : {}),
        ...settingPaths
      };
    }
  }
  return nestedMetadata;
};

/**
 * Find block settings nested in other block settings.
 *
 * Given an array of blocks names from the top level of the editor to the
 * current block (`blockNamePath`), return the value for the deepest-nested
 * settings value that applies to the current block.
 *
 * If two setting values share the same nesting depth, use the last one that
 * occurs in settings (like CSS).
 *
 * @param {string[]} blockNamePath  Block names representing the path to the
 *                                  current block from the top level of the
 *                                  block editor.
 * @param {string}   normalizedPath Path to the setting being retrieved.
 * @param {Object}   settings       Object containing all block settings.
 * @param {Object}   result         Optional. Object with keys `depth` and
 *                                  `value` used to track current most-nested
 *                                  setting.
 * @param {number}   depth          Optional. The current recursion depth used
 *                                  to calculate the most-nested setting.
 * @return {Object}                 Object with keys `depth` and `value`.
 *                                  Destructure the `value` key for the result.
 */
const getNestedSetting = function (blockNamePath, normalizedPath, settings) {
  let result = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : {
    depth: 0,
    value: undefined
  };
  let depth = arguments.length > 4 && arguments[4] !== undefined ? arguments[4] : 1;
  const [currentBlockName, ...remainingBlockNames] = blockNamePath;
  // eslint-disable-next-line security/detect-object-injection
  const blockSettings = settings[currentBlockName];
  if (remainingBlockNames.length === 0) {
    const settingValue = (0,lodash__WEBPACK_IMPORTED_MODULE_0__.get)(blockSettings, normalizedPath);
    if (settingValue !== undefined && depth >= result.depth) {
      result.depth = depth;
      result.value = settingValue;
    }
    return result;
  } else if (blockSettings !== undefined) {
    // Recurse into the parent block's settings
    result = getNestedSetting(remainingBlockNames, normalizedPath, blockSettings, result, depth + 1);
  }

  // Continue down the array of blocks
  return getNestedSetting(remainingBlockNames, normalizedPath, settings, result, depth);
};
function flattenSettingPaths(settings) {
  let prefix = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
  const result = {};
  Object.entries(settings).forEach(_ref2 => {
    let [key, value] = _ref2;
    const isRegularObject = typeof value === 'object' && !!value && !Array.isArray(value);
    if (isRegularObject) {
      result[`${prefix}${key}`] = true;
      Object.assign(result, flattenSettingPaths(value, `${prefix}${key}.`));
    } else {
      result[`${prefix}${key}`] = true;
    }
  });
  return result;
}

/***/ }),

/***/ "lodash":
/*!*************************!*\
  !*** external "lodash" ***!
  \*************************/
/***/ (function(module) {

module.exports = window["lodash"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ (function(module) {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ (function(module) {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/compose":
/*!*********************************!*\
  !*** external ["wp","compose"] ***!
  \*********************************/
/***/ (function(module) {

module.exports = window["wp"]["compose"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ (function(module) {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ (function(module) {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/hooks":
/*!*******************************!*\
  !*** external ["wp","hooks"] ***!
  \*******************************/
/***/ (function(module) {

module.exports = window["wp"]["hooks"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ (function(module) {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/notices":
/*!*********************************!*\
  !*** external ["wp","notices"] ***!
  \*********************************/
/***/ (function(module) {

module.exports = window["wp"]["notices"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
!function() {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/hooks */ "@wordpress/hooks");
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_notices__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/notices */ "@wordpress/notices");
/* harmony import */ var _wordpress_notices__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_notices__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _nested_governance_loader__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./nested-governance-loader */ "./src/nested-governance-loader.js");
/* harmony import */ var _block_locking__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./block-locking */ "./src/block-locking.jsx");
/* harmony import */ var _block_utils__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./block-utils */ "./src/block-utils.js");








function setup() {
  if (VIP_GOVERNANCE.error) {
    (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.dispatch)(_wordpress_notices__WEBPACK_IMPORTED_MODULE_3__.store).createErrorNotice(VIP_GOVERNANCE.error, {
      id: 'wpcomvip-governance-error',
      isDismissible: true,
      actions: [{
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Open governance settings'),
        url: VIP_GOVERNANCE.urlSettingsPage
      }]
    });
    return;
  }
  const governanceRules = VIP_GOVERNANCE.governanceRules;
  (0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.addFilter)('blockEditor.__unstableCanInsertBlockType', `wpcomvip-governance/block-insertion`, (canInsert, blockType, rootClientId, _ref) => {
    let {
      getBlock
    } = _ref;
    if (canInsert === false) {
      return canInsert;
    }
    let parentBlockNames = [];
    if (rootClientId) {
      // This block has parents. Build a list of parentBlockNames
      const {
        getBlockParents,
        getBlockName
      } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.store);
      const parentBlock = getBlock(rootClientId);
      const ancestorClientIds = getBlockParents(rootClientId, true);
      parentBlockNames = [parentBlock.clientId, ...ancestorClientIds].map(parentClientId => getBlockName(parentClientId));
    }
    const isAllowed = (0,_block_utils__WEBPACK_IMPORTED_MODULE_7__.isBlockAllowedInHierarchy)(blockType.name, parentBlockNames, governanceRules);

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
    return (0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.applyFilters)('vip_governance__is_block_allowed_for_insertion', isAllowed, blockType.name, parentBlockNames, governanceRules);
  });
  const nestedSettings = VIP_GOVERNANCE.nestedSettings;
  const nestedSettingPaths = (0,_nested_governance_loader__WEBPACK_IMPORTED_MODULE_5__.getNestedSettingPaths)(nestedSettings);
  (0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_0__.addFilter)('blockEditor.useSetting.before', `wpcomvip-governance/nested-block-settings`, (result, path, clientId, blockName) => {
    const hasCustomSetting =
    // eslint-disable-next-line security/detect-object-injection
    nestedSettingPaths[blockName] !== undefined &&
    // eslint-disable-next-line security/detect-object-injection
    nestedSettingPaths[blockName][path] === true;
    if (!hasCustomSetting) {
      return result;
    }
    const blockNamePath = [clientId, ...(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.store).getBlockParents(clientId, /* ascending */true)].map(candidateId => (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.store).getBlockName(candidateId)).reverse();
    ({
      value: result
    } = (0,_nested_governance_loader__WEBPACK_IMPORTED_MODULE_5__.getNestedSetting)(blockNamePath, path, nestedSettings));
    return result.theme ? result.theme : result;
  });

  // Block locking
  if (governanceRules?.allowedBlocks) {
    (0,_block_locking__WEBPACK_IMPORTED_MODULE_6__.setupBlockLocking)(governanceRules);
  }
}
setup();
}();
/******/ })()
;
//# sourceMappingURL=index.js.map