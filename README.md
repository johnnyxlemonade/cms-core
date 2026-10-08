# Lemonade CMS Core

[![PHPStan](https://github.com/johnnyxlemonade/cms-core/actions/workflows/phpstan.yml/badge.svg)](https://github.com/johnnyxlemonade/cms-core/actions/workflows/phpstan.yml)
[![Tests](https://github.com/johnnyxlemonade/cms-core/actions/workflows/phpunit.yml/badge.svg)](https://github.com/johnnyxlemonade/cms-core/actions/workflows/phpunit.yml)
[![Coding Standards](https://github.com/johnnyxlemonade/cms-core/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/johnnyxlemonade/cms-core/actions/workflows/coding-standards.yml)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue.svg)](composer.json)

`johnnyxlemonade/cms-core` provides reusable public CMS routing for Lemonade
Framework applications. It owns the canonical `cms_route` URL index, route
reservation and resolution, locale-aware public routing, and contracts for
module-provided public detail and collection handlers.

The package is runtime-only. It does not own Admin UI, permissions, DataGrid,
AdminEditor, public HTML, content publication policy, or file storage. The host
supplies locale, module-state and route-prefix runtime ports. A host or content
module registers the handler that authoritatively renders a public CMS response.

## Installation

The package requires PHP `>=8.3 <8.6` and `johnnyxlemonade/framework`.

    composer require johnnyxlemonade/cms-core:dev-main

Register `Lemonade\Cms\CmsCoreServiceProvider` in the host application and bind
implementations of `PublicLocaleRegistryInterface`,
`PublicModuleStateResolverInterface`, and
`PublicModuleRoutePrefixRepositoryInterface`.

## Public CMS routing

CMS Core resolves a public request through the current
`PublicLocaleResolution`, the canonical `cms_route` record, module lifecycle
state, and the host-provided public handler. A route record establishes a
canonical address; it is not proof that the target content remains public. The
module handler remains authoritative for publication state, soft deletion,
visibility windows, and locale-specific content availability.

Detail handlers implement `PublicCmsRouteHandlerInterface`. Collection handlers
implement `PublicCmsCollectionHandlerInterface` and are resolved through an
active request container; handlers must not retain request or locale state.

## Package boundaries

- `johnnyxlemonade/framework` owns the base application runtime and generic infrastructure.
- CMS Core owns public CMS route resolution and handler contracts.
- The host owns public presentation, module publication rules, locale ports and concrete handlers.
- Admin packages must not become a dependency of CMS Core.

## Development and QA

From the package root, run:

```bash
composer cs:check
composer stan
composer test
composer qa
```
