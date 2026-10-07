# Lemonade CMS Core

`johnnyxlemonade/cms-core` poskytuje znovupoužitelný CMS runtime pro aplikace
nad Lemonade Framework. Vlastní canonical URL index `cms_route`, rezervaci a
resolution CMS cest a kontrakty pro public detail a collection handlery.

Balíček obsahuje pouze runtime. Nevlastní Admin UI, permissions, DataGrid,
AdminEditor ani public frontend HTML. Host poskytuje locale, stav modulu a
route-prefix runtime porty; host nebo content modul registruje public handler,
který vytváří výslednou response.

## Instalace

Balíček vyžaduje PHP `>=8.3 <8.6` a `johnnyxlemonade/framework`.

    composer require johnnyxlemonade/cms-core:dev-main

V host aplikaci zaregistrujte `Lemonade\Cms\CmsCoreServiceProvider` a bindujte
implementace `PublicLocaleRegistryInterface`, `PublicModuleStateResolverInterface`
a `PublicModuleRoutePrefixRepositoryInterface`.

## Vývoj a QA

Kontroly z rootu balíčku:

    composer cs:check
    composer stan
    composer test
    composer qa
