# CLAUDE.md - Instructions for Claude Code

Read `AGENTS.md` in this repository for a comprehensive overview of the project architecture, directory structure, governance rules system, development workflow, and testing setup.

## Quick Reference

- **Main plugin file**: `vip-governance.php`
- **PHP backend**: `governance/` directory
- **JS frontend**: `src/` directory (builds to `build/`)
- **Tests**: `tests/` (PHPUnit), `src/**/*.test.js` (Jest), `tests/e2e/` (Playwright)
- **Rules schema**: `governance-schema.json`
- **Default rules**: `governance-rules.json`

## Commands

- `npm run build` — Production build
- `npm run dev` — Watch mode
- `npm run lint` — All linting (Prettier + ESLint + PHPCS)
- `npm run test:js` — Jest unit tests
- `composer run test` — PHPUnit tests (requires `wp-env start`)
- `npx playwright test` — E2E tests (requires `wp-env start`)

## Writing Tests

- **PHP**: Add `tests/test-<name>.php`, class in `WPCOMVIP\Governance\Tests` namespace extending `TestCase`
- **JS**: Add `src/<name>.test.js` co-located with the source file
- **E2E**: Add `tests/e2e/<name>.spec.js` using `@wordpress/e2e-test-utils-playwright`
- See AGENTS.md "Writing Tests" section for full patterns and examples

## Debugging

- Local dev at `http://localhost:8889` (admin/password)
- Inspect `VIP_GOVERNANCE` global in browser devtools for resolved rules
- Admin settings page (`VIP Block Governance`) shows parsed rules and validation errors
- REST API: `GET /wp-json/vip-governance/v1/<role>/rules`
- See AGENTS.md "Debugging" section for common debugging steps

## Conventions

- PHP follows WordPress-VIP-Go coding standards
- JS follows `@automattic/eslint-plugin-wpvip` rules
- PHP 8.1+ required; WordPress 6.0+ required
- Plugin uses opt-in model: only explicitly allowed blocks are available
- Pre-commit hook runs `lint-staged` automatically
