(()=>{"use strict";const e=window.wp.hooks,t=window.wp.data,n=window.wp.blockEditor,o=window.wp.notices,r=window.wp.i18n,i=window.lodash,c=function(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:{},n=arguments.length>2&&void 0!==arguments[2]&&arguments[2];const o=["allowedBlocks"];for(const[i,l]of Object.entries(e))if(!o.includes(i))if(i.includes("/"))Object.entries(e).forEach((e=>{let[n,r]=e;o.includes(n)||c(r,t,n)}));else if(!1!==n){var r;const e=s(l,`${i}.`);t[n]={...null!==(r=t[n])&&void 0!==r?r:{},...e}}return t},l=function(e,t,n){let o=arguments.length>3&&void 0!==arguments[3]?arguments[3]:{depth:0,value:void 0},r=arguments.length>4&&void 0!==arguments[4]?arguments[4]:1;const[c,...s]=e,a=n[c];if(0===s.length){const e=(0,i.get)(a,t);return void 0!==e&&r>=o.depth&&(o.depth=r,o.value=e),o}return void 0!==a&&(o=l(s,t,a,o,r+1)),l(s,t,n,o,r)};function s(e){let t=arguments.length>1&&void 0!==arguments[1]?arguments[1]:"";const n={};return Object.entries(e).forEach((e=>{let[o,r]=e;"object"==typeof r&&r&&!Array.isArray(r)?(n[`${t}${o}`]=!0,Object.assign(n,s(r,`${t}${o}.`))):n[`${t}${o}`]=!0})),n}const a=window.wp.element,d=window.wp.components,u=window.wp.compose,p={"core/list":["core/list-item"],"core/columns":["core/column"],"core/page-list":["core/page-list-item"],"core/navigation":["core/navigation-link","core/navigation-submenu"]};function g(t,n,o){const r=(0,e.applyFilters)("vip_governance__is_block_allowed_in_hierarchy",!1,t,n,o)||0===n.length?[...o.allowedBlocks]:[];if(o.blockSettings&&n.length>0){if(p[n[0]]&&p[n[0]].includes(t))return!0;const e=l(n.reverse(),"allowedBlocks",o.blockSettings);e&&e.value&&r.push(...e.value)}return function(e,t){return t.some((t=>function(e,t){return t.includes("*")?e.match(new RegExp(t.replace("*",".*"))):t===e}(e,t)))}(t,r)}const w={};!function(){if(VIP_GOVERNANCE.error)return void(0,t.dispatch)(o.store).createErrorNotice(VIP_GOVERNANCE.error,{id:"wpcomvip-governance-error",isDismissible:!0,actions:[{label:(0,r.__)("Open governance settings"),url:VIP_GOVERNANCE.urlSettingsPage}]});const i=VIP_GOVERNANCE.governanceRules;(0,e.addFilter)("blockEditor.__unstableCanInsertBlockType","wpcomvip-governance/block-insertion",((o,r,c,l)=>{let{getBlock:s}=l;if(!1===o)return o;let a=[];if(c){const{getBlockParents:e,getBlockName:o}=(0,t.select)(n.store),r=s(c),i=e(c,!0);a=[r.clientId,...i].map((e=>o(e)))}const d=g(r.name,a,i);return(0,e.applyFilters)("vip_governance__is_block_allowed_for_insertion",d,r.name,a,i)}));const s=VIP_GOVERNANCE.nestedSettings,p=c(s);(0,e.addFilter)("blockEditor.useSetting.before","wpcomvip-governance/nested-block-settings",((e,o,r,i)=>{if(void 0===p[i]||!0!==p[i][o])return e;const c=[r,...(0,t.select)(n.store).getBlockParents(r,!0)].map((e=>(0,t.select)(n.store).getBlockName(e))).reverse();return({value:e}=l(c,o,s)),e.theme?e.theme:e})),i?.allowedBlocks&&function(o){const r=(0,u.createHigherOrderComponent)((r=>i=>{const{name:c,clientId:l}=i,{getBlockParents:s,getBlockName:u}=(0,t.select)(n.store),p=s(l,!0),v=p.some((e=>function(e){return e in w}(e)));if(v)return(0,a.createElement)(r,i);const m=p.map((e=>u(e)));let _=g(c,m,o);return _=(0,e.applyFilters)("vip_governance__is_block_allowed_for_editing",_,c,m,o),_?(0,a.createElement)(r,i):(function(e){w[e]=!0}(l),(0,a.createElement)(a.Fragment,null,(0,a.createElement)(d.Disabled,null,(0,a.createElement)("div",{style:{opacity:.6,"background-color":"#eee",border:"2px dashed #999"}},(0,a.createElement)(r,i)))))}),"withDisabledBlocks");(0,e.addFilter)("editor.BlockEdit","wpcomvip-governance/with-disabled-blocks",r)}(i)}()})();