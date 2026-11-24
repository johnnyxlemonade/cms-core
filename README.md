# Lemonade CMS Core

[![Tests](https://img.shields.io/github/actions/workflow/status/johnnyxlemonade/cms-core/phpunit.yml?branch=main&label=Tests&logo=githubactions&logoColor=white&labelColor=0f766e)](https://github.com/johnnyxlemonade/cms-core/actions/workflows/phpunit.yml)
[![PHP 8.3–8.6](https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5%20%7C%208.6-777BB4?logo=php&logoColor=white)](#requirements)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/johnnyxlemonade/cms-core/phpstan.yml?branch=main&label=PHPStan&logo=php&logoColor=white&labelColor=5b4baf)](https://github.com/johnnyxlemonade/cms-core/actions/workflows/phpstan.yml)
[![License](https://img.shields.io/badge/license-Apache--2.0-green.svg)](LICENSE)

`johnnyxlemonade/cms-core` provides shared CMS content lifecycle and public
routing for Lemonade Framework applications. It owns the canonical `cms_route`
URL index, route reservation and resolution, locale-aware public routing, and
contracts for module-provided public handlers.

The package does not own Admin UI, permissions, DataGrid, AdminEditor, public
HTML, or file storage. Content modules use its shared publication and audit
lifecycle. The host supplies locale and module-state runtime ports and
registers handlers that render public CMS responses.

## Requirements

- PHP 8.3 or newer, tested on PHP 8.3, 8.4, 8.5 and 8.6.
- `johnnyxlemonade/framework:dev-main`.

## Installation

    composer require johnnyxlemonade/cms-core:dev-main

Register `Lemonade\Cms\CmsCoreServiceProvider` in the host application and bind
implementations of `PublicLocaleRegistryInterface` and
`PublicModuleStateResolverInterface`.

## Public CMS routing

CMS Core resolves a public request through the current
`PublicLocaleResolution`, the canonical `cms_route` record, module lifecycle
state, and the host-provided public handler. A route record establishes a
canonical address; it is not proof that the target content remains public. The
module handler remains authoritative for publication state, soft deletion,
visibility windows, and locale-specific content availability.

Detail handlers implement `PublicCmsRouteHandlerInterface`. Collection pages are
ordinary host application routes and remain outside CMS Core.

## Package boundaries

- `johnnyxlemonade/framework` owns the base application runtime and generic infrastructure.
- CMS Core owns public CMS route resolution and handler contracts.
- Content modules own domain-specific publication rules and public read models.
- The host owns public presentation, locale ports and concrete handlers.
- Admin packages must not become a dependency of CMS Core.

## Development and QA

From the package root, run:

```bash
composer cs:check
composer stan
composer test
composer qa
```

## Non-hierarchical content reads

`CmsContentReadModel(Database, CmsContentDefinition)` supplies `exists()`,
`ownerId()`, `metadata()`, `translation()`, `publication()`, `findForEditor()`,
`tagNamesForLocale()`, `editorLocales()`, `dataGridLocales()` and `enabledLocales()`.
`CmsDomainMapping::columns()` reads the same `CmsRoot` and `CmsLocalized`
attributes used by `CmsContentLifecycle`; modules do not maintain another list
of domain database columns or implement a read adapter. Explicitly attributed
private properties are supported as well; unannotated private state is excluded.
Public domain properties retain the requirement for exactly one mapping attribute.

The editor projection includes all stored translations, ordered localized tags,
canonical paths, domain values, ownership and audit names. Active reads exclude
soft-deleted roots; owner lookup includes them. Translation and publication
lookups retain the stored mutations of deleted roots. Editor languages include
enabled languages plus disabled languages with a stored mutation; the grid
language list considers mutations across the whole module, including deleted
records. Tag suggestions retain their existing locale-wide alphabetical order.

`CmsContentQueries` contains the shared QueryBuilder projections and canonical
and audit joins. It has no Admin dependency. Pagination, search, filters and
DataGrid transport are provided by `admin-platform`.

`PublishedContentScope::query($database, $definition, $locale)` builds the
shared public SQL scope for nonhierarchical routed content. News, Auction and
Rental use it for published translations, active languages, nondeleted roots,
nonempty titles and active canonical routes. A `null` locale checks visibility
in any enabled language. The caller still checks module availability and owns
its domain filters, projections and batched hydration. Page retains its
ancestor visibility rules; Content Types retain their optional canonical route
and dynamic type rules.

## Shared-table content definitions

`CmsContentDefinition::rootScope()` optionally constrains a shared root table to an
explicit partition (for example a content type ID). CMS root reads, locks, writes,
translation reads and locale projections preserve that scope; domain mapping
cannot overwrite it. Existing definitions have an empty scope and retain their
behavior.

The definition, editor input, canonical route, route reservation and locale
resolution expose immutable values through `getXxx()` methods. Boolean input
presence uses `CmsContentInput::hasPublication()`. `rootScope()` and
`routePrefix($locale)` remain operations for scoped queries and locale-specific
path lookup. `CmsContentDefinition` keeps its named constructor arguments while
its properties are private. For example:

```php
$moduleCode = $definition->getModuleCode();
$rootTable = $definition->getRootTable();
$hasBody = $definition->hasBodyColumns();
$pathPrefix = $definition->routePrefix('cs');
```

An optional `CmsContentValueWriter` participates in `CmsContentLifecycle`'s existing
transaction. It checks creation before root insertion, initializes draft defaults,
validates/prepares values after the effective publication state is resolved, and
writes them once the translation exists. Preparation runs even on otherwise
unchanged published content; the change flag participates in CMS no-op detection.
System data, related values, canonical reservations and audit remain atomic. The
writer must not start a parallel lifecycle or dispatch an independent audit.
