<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use DomainException;
use InvalidArgumentException;
use Lemonade\Cms\CmsPublicationChange;
use Lemonade\Cms\CmsPublicationState;
use Lemonade\Cms\CmsTagNormalizer;
use Lemonade\Cms\Content\Models\CmsContentModel;
use Lemonade\Cms\Routing\Cms\CmsRouteReservationService;
use Lemonade\Cms\Routing\Cms\CmsRouteValidationException;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Support\Slug\Slugger;
use RuntimeException;

/**
 * Orchestruje nehierarchicky lokalizovany obsah v jedne auditovane transakci
 *
 * @template T of object
 */
final readonly class CmsContentLifecycle
{
    /**
     * Nastavuje tabulky, domenove mapovani, routy a transakcni runtime
     *
     * @param CmsDomainMapping<T> $mapping
     */
    public function __construct(
        private CmsContentDefinition $definition,
        private CmsContentModel $model,
        private CmsDomainMapping $mapping,
        private CmsRouteReservationService $routes,
        private Slugger $slugger,
        private CmsContentRuntime $runtime,
    ) {}

    /**
     * Zalozi prazdny root a vychozi jazykovou mutaci
     */
    public function createDraft(int $actorUserId, string $locale): int
    {
        return $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $locale): int {
            $id = $this->model->createDraft($actorUserId, $locale, date('Y-m-d H:i:s'));
            if ($id < 1) {
                throw new RuntimeException($this->validation('save_failed'));
            }
            $this->record($events, 'created', $id);

            return $id;
        });
    }

    /**
     * Ulozi obsah a domenove hodnoty v jedne auditovane transakci
     *
     * @param T $domain
     */
    public function save(int $actorUserId, int $id, CmsContentInput $input, object $domain): void
    {
        $values = $this->mapping->values($domain);
        if (array_key_exists($this->definition->translationForeignKey, $values['localized'])) {
            throw new InvalidArgumentException('CMS domain mapping cannot replace the translation owner.');
        }
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $id, $input, $domain, $values): void {
                    $root = $this->model->rootForUpdate($id);
                    if ($root === null) {
                        throw new DomainException($this->validation('not_found'));
                    }
                    $translation = $this->model->translationForUpdate($id, $input->locale);
                    if ($translation === null && !$this->runtime->isEnabledLocale($input->locale)) {
                        throw new DomainException($this->validation('locale_invalid'));
                    }
                    if ($input->title === '') {
                        throw new DomainException($this->validation('title_required'));
                    }
                    if (mb_strlen($input->title) > 180) {
                        throw new DomainException($this->validation('title_max_length'));
                    }
                    $hasRoute = $this->routes->hasReservation($this->definition->moduleCode, $id, $input->locale);
                    $slug = $hasRoute ? null : $this->slugger->slug($input->title, 180, $input->locale);
                    if ($slug === '') {
                        throw new DomainException('admin.url_segment.invalid');
                    }
                    try {
                        $tags = CmsTagNormalizer::normalize($input->tags);
                    } catch (InvalidArgumentException $exception) {
                        throw new DomainException($this->validation('tag_max_length'), previous: $exception);
                    }
                    $existingTags = $translation === null ? [] : $this->model->tags((int) $translation['id']);
                    $domainChanges = $this->mapping->changes($domain, $root, $translation);
                    $settingsChanged = false;
                    foreach ($input->displaySettings as $column => $value) {
                        if ((int) $root[$column] !== $value) {
                            $settingsChanged = true;
                        }
                    }
                    $content = [
                        'title' => $input->title,
                        'page_title' => $input->pageTitle,
                        'summary' => $input->summary,
                        'content' => $input->content,
                        'meta_description' => $input->metaDescription,
                    ];
                    $translationChanged = $translation === null || !$hasRoute || $domainChanges['localized'] || $existingTags !== $tags;
                    if ($translation !== null) {
                        foreach ($content as $column => $value) {
                            if ($translation[$column] !== $value) {
                                $translationChanged = true;
                            }
                        }
                    }
                    $now = date('Y-m-d H:i:s');
                    try {
                        [$publicationState, $publishedAt, $publicationChanged] = CmsPublicationChange::resolve(
                            $input->hasPublication,
                            $input->publicationState,
                            $input->publishedAt,
                            (int) ($translation['publication_state'] ?? CmsPublicationState::New->value),
                            $translation['published_at'] ?? null,
                            $now,
                        );
                    } catch (InvalidArgumentException $exception) {
                        throw new DomainException($this->validation('publication_invalid'), previous: $exception);
                    }
                    if (!$domainChanges['root'] && !$settingsChanged && !$translationChanged && !$publicationChanged) {
                        $events->noOp();

                        return;
                    }
                    if ($domainChanges['root'] || $settingsChanged) {
                        $this->model->saveRoot($id, array_merge($values['root'], $input->displaySettings), $now);
                        $this->record($events, 'updated', $id);
                    }
                    if ($translationChanged) {
                        if ($slug !== null) {
                            $this->routes->reserveAutomatic($this->definition->moduleCode, $id, $input->locale, $slug, 180);
                        }
                        $translationId = $this->model->saveTranslation(
                            $id,
                            $input->locale,
                            $translation === null ? null : (int) $translation['id'],
                            array_merge($content, $values['localized']),
                            $now,
                        );
                        if ($existingTags !== $tags) {
                            $this->model->saveTags($translationId, $tags, $now);
                        }
                        $this->model->promoteDraft($id, $input->locale, $now);
                        $this->record($events, $translation === null ? 'translation_created' : 'translation_updated', $id, ['locale' => $input->locale]);
                    }
                    if ($publicationChanged) {
                        $this->model->savePublication($id, $input->locale, $publicationState, $publishedAt, $now);
                        if ($publicationState === CmsPublicationState::Published
                            || (int) ($translation['publication_state'] ?? CmsPublicationState::New->value) === CmsPublicationState::Published->value
                        ) {
                            $this->record($events, $publicationState === CmsPublicationState::Published ? 'published' : 'unpublished', $id, ['locale' => $input->locale]);
                        }
                    }
                    $this->model->saveRoot($id, ['updated_by' => $actorUserId], $now);
                });

                return;
            } catch (DatabaseException $exception) {
                $message = strtolower($exception->getMessage());
                if ($attempt === 3 || (!str_contains($message, 'uq_cms_route_locale_path')
                    && !str_contains($message, 'uq_cms_route_target')
                    && !str_contains($message, $this->definition->translationUniqueConstraint))) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Explicitne upravi canonical segment jedne mutace
     */
    public function changeUrlSegment(int $actorUserId, int $id, string $locale, string $segment): string
    {
        $locale = strtolower(trim($locale));
        $segment = $this->slugger->slug($segment, 180, $locale);
        if ($segment === '') {
            throw new DomainException('admin.url_segment.invalid');
        }

        return $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $id, $locale, $segment): string {
            if ($this->model->rootForUpdate($id) === null) {
                throw new DomainException($this->validation('not_found'));
            }
            if ($this->model->translationForUpdate($id, $locale) === null && !$this->runtime->isEnabledLocale($locale)) {
                throw new DomainException($this->validation('locale_invalid'));
            }
            $currentPath = $this->routes->reservationPath($this->definition->moduleCode, $id, $locale);
            if ($currentPath !== null && basename($currentPath) === $segment) {
                $events->noOp();

                return $segment;
            }
            try {
                $this->routes->reserveExplicit($this->definition->moduleCode, $id, $locale, $segment);
            } catch (CmsRouteValidationException $exception) {
                throw new DomainException(match ($exception->getMessage()) {
                    'cms.route.path_reserved' => 'admin.url_segment.reserved',
                    'cms.route.path_occupied' => 'admin.url_segment.occupied',
                    default => 'admin.url_segment.invalid',
                }, previous: $exception);
            }
            $this->model->saveRoot($id, ['updated_by' => $actorUserId], date('Y-m-d H:i:s'));
            $this->record($events, 'updated', $id, ['locale' => $locale]);

            return $segment;
        });
    }

    /**
     * Soft delete zachova rezervovane canonical routy
     */
    public function delete(int $actorUserId, int $id): void
    {
        $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $id): void {
            $root = $this->model->lifecycleForUpdate($id);
            if ($root === null) {
                throw new DomainException($this->validation('not_found'));
            }
            if ($root['deleted_at'] !== null) {
                $events->noOp();

                return;
            }
            $now = date('Y-m-d H:i:s');
            $this->model->saveRoot($id, ['deleted_at' => $now, 'deleted_by' => $actorUserId, 'updated_by' => $actorUserId], $now);
            $this->routes->softDeleteForTarget($this->definition->moduleCode, $id, $now);
            $this->record($events, 'deleted', $id);
        });
    }

    /**
     * Obnovi vsechny mutace jako koncept a znovu aktivuje routy
     */
    public function restore(int $actorUserId, int $id): void
    {
        $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $id): void {
            $root = $this->model->lifecycleForUpdate($id);
            if ($root === null) {
                throw new DomainException($this->validation('not_found'));
            }
            if ($root['deleted_at'] === null) {
                $events->noOp();

                return;
            }
            $now = date('Y-m-d H:i:s');
            $this->model->restoreDraft($id, $actorUserId, $now);
            $this->routes->restoreForTarget($this->definition->moduleCode, $id, $now);
            $this->record($events, 'restored', $id);
        });
    }

    /**
     * Preda vlastnictvi existujicimu lokalnimu uzivateli
     */
    public function transferOwner(int $actorUserId, int $id, int $newOwnerId): void
    {
        $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $id, $newOwnerId): void {
            $root = $this->model->lifecycleForUpdate($id);
            if ($root === null) {
                throw new DomainException($this->validation('not_found'));
            }
            if (!$this->runtime->ownerExists($newOwnerId)) {
                throw new DomainException($this->validation('owner_invalid'));
            }
            $previous = $root['owner_id'] === null ? null : (int) $root['owner_id'];
            if ($previous === $newOwnerId) {
                $events->noOp();

                return;
            }
            $this->model->saveRoot($id, ['owner_id' => $newOwnerId, 'updated_by' => $actorUserId], date('Y-m-d H:i:s'));
            $this->record($events, 'owner_transferred', $id, ['previous_owner_id' => $previous, 'new_owner_id' => $newOwnerId]);
        });
    }

    /**
     * Vrati modulovy klic validacni chyby
     */
    private function validation(string $key): string
    {
        return $this->definition->validationPrefix . '.validation.' . $key;
    }

    /**
     * Zaznamena udalost pod canonical identitou konkretniho modulu
     *
     * @param array<string,mixed> $payload
     */
    private function record(CmsContentEvents $events, string $suffix, int $id, array $payload = []): void
    {
        $events->record(
            $this->definition->moduleCode . '.' . $suffix,
            $this->definition->moduleCode,
            $this->definition->entityType,
            $id,
            $payload,
        );
    }
}
