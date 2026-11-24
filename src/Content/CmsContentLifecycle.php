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
 * Saves nonhierarchical localized content in one audited transaction
 *
 * @template T of object
 */
final readonly class CmsContentLifecycle
{
    private const MAXIMUM_SAVE_ATTEMPTS = 3;

    /**
     * Receives tables, domain mapping, routes and transactional runtime
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
        private ?CmsContentValueWriter $valueWriter = null,
    ) {}

    /**
     * Creates an empty root record and its initial translation
     */
    public function createDraft(int $actorUserId, string $locale): int
    {
        return $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $locale): int {
            $this->valueWriter?->beforeCreateDraft($locale);
            $id = $this->model->createDraft($actorUserId, $locale, date('Y-m-d H:i:s'));
            if ($id < 1) {
                throw new RuntimeException($this->validation('save_failed'));
            }
            $this->valueWriter?->createDraft($id, $locale);
            $this->record($events, 'created', $id);

            return $id;
        });
    }

    /**
     * Saves content and domain values in one audited transaction
     *
     * @param T $domain
     */
    public function save(int $actorUserId, int $id, CmsContentInput $input, object $domain): void
    {
        $values = $this->mapping->values($domain);
        if (array_intersect_key($values['root'], $this->definition->rootScope()) !== []) {
            throw new InvalidArgumentException('CMS domain mapping cannot replace the root scope.');
        }
        if (array_key_exists($this->definition->getTranslationForeignKey(), $values['localized'])) {
            throw new InvalidArgumentException('CMS domain mapping cannot replace the translation owner.');
        }
        for ($attempt = 1; $attempt <= self::MAXIMUM_SAVE_ATTEMPTS; $attempt++) {
            try {
                $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $id, $input, $domain, $values): void {
                    $root = $this->model->rootForUpdate($id);
                    if ($root === null) {
                        throw new DomainException($this->validation('not_found'));
                    }
                    $translation = $this->model->translationForUpdate($id, $input->getLocale());
                    if ($translation === null && !$this->runtime->isEnabledLocale($input->getLocale())) {
                        throw new CmsContentValidationException(['locale' => $this->validation('locale_invalid')]);
                    }
                    $now = date('Y-m-d H:i:s');
                    try {
                        [$publicationState, $publishedAt, $publicationChanged] = CmsPublicationChange::resolve(
                            $input->hasPublication(),
                            $input->getPublicationState(),
                            $input->getPublishedAt(),
                            (int) ($translation['publication_state'] ?? CmsPublicationState::New->value),
                            $translation['published_at'] ?? null,
                            $now,
                        );
                    } catch (InvalidArgumentException $exception) {
                        $field = str_contains($exception->getMessage(), 'date') ? 'published_at' : 'publication_state';
                        throw new CmsContentValidationException([$field => $this->validation('publication_invalid')]);
                    }
                    CmsContentValidation::assert(CmsContentValidation::content($input, $publicationState, $this->definition->getValidationPrefix()));
                    $hasRoute = $this->routes->hasReservation($this->definition->getModuleCode(), $id, $input->getLocale());
                    $slug = $hasRoute || $input->getTitle() === ''
                        ? null
                        : $this->slugger->slug($input->getTitle(), 180, $input->getLocale());
                    if ($slug === '' || ($publicationState === CmsPublicationState::Published && !$hasRoute && $slug === null)) {
                        throw new CmsContentValidationException(['title' => 'admin.url_segment.invalid']);
                    }
                    try {
                        $tags = CmsTagNormalizer::normalize($input->getTags());
                    } catch (InvalidArgumentException $exception) {
                        throw new CmsContentValidationException(['tags' => $this->validation('tag_max_length')]);
                    }
                    $existingTags = $translation === null ? [] : $this->model->tags((int) $translation['id']);
                    $domainChanges = $this->mapping->changes($domain, $root, $translation);
                    $settingsChanged = false;
                    foreach ($input->getDisplaySettings() as $column => $value) {
                        if ((int) $root[$column] !== $value) {
                            $settingsChanged = true;
                        }
                    }
                    $content = [
                        'title' => $input->getTitle(),
                        'page_title' => $input->getPageTitle(),
                        'meta_description' => $input->getMetaDescription(),
                    ];
                    if ($this->definition->hasBodyColumns()) {
                        $content['summary'] = $input->getSummary();
                        $content['content'] = $input->getContent();
                    }
                    $translationChanged = $translation === null || $slug !== null || $domainChanges['localized'] || $existingTags !== $tags;
                    if ($translation !== null) {
                        foreach ($content as $column => $value) {
                            if ($translation[$column] !== $value) {
                                $translationChanged = true;
                            }
                        }
                    }
                    $valuesChanged = $this->valueWriter?->prepare($id, $input->getLocale(), $publicationState, $domain) === true;
                    if ($valuesChanged) {
                        $translationChanged = true;
                    }
                    if (!$domainChanges['root'] && !$settingsChanged && !$translationChanged && !$publicationChanged) {
                        $events->noOp();

                        return;
                    }
                    if ($domainChanges['root'] || $settingsChanged) {
                        $this->model->saveRoot($id, array_merge($values['root'], $input->getDisplaySettings()), $now);
                        $this->record($events, 'updated', $id);
                    }
                    if ($translationChanged) {
                        if ($slug !== null) {
                            $this->routes->reserveAutomatic(
                                moduleCode: $this->definition->getModuleCode(),
                                entityId: $id,
                                locale: $input->getLocale(),
                                baseSlug: $slug,
                                maximumSlugLength: 180,
                                pathPrefix: $this->definition->routePrefix($input->getLocale()),
                            );
                        }
                        $translationId = $this->model->saveTranslation(
                            $id,
                            $input->getLocale(),
                            $translation === null ? null : (int) $translation['id'],
                            array_merge($content, $values['localized']),
                            $now,
                        );
                        if ($existingTags !== $tags) {
                            $this->model->saveTags($translationId, $tags, $now);
                        }
                        $this->model->promoteDraft($id, $input->getLocale(), $now);
                        $this->record($events, $translation === null ? 'translation_created' : 'translation_updated', $id, ['locale' => $input->getLocale()]);
                    }
                    if ($publicationChanged) {
                        $this->model->savePublication($id, $input->getLocale(), $publicationState, $publishedAt, $now);
                        if ($publicationState === CmsPublicationState::Published
                            || (int) ($translation['publication_state'] ?? CmsPublicationState::New->value) === CmsPublicationState::Published->value
                        ) {
                            $this->record($events, $publicationState === CmsPublicationState::Published ? 'published' : 'unpublished', $id, ['locale' => $input->getLocale()]);
                        }
                    }
                    if ($valuesChanged) {
                        $this->valueWriter?->save($id, $input->getLocale());
                    }
                    $this->model->saveRoot($id, ['updated_by' => $actorUserId], $now);
                });

                return;
            } catch (DatabaseException $exception) {
                $message = strtolower($exception->getMessage());
                if ($attempt === self::MAXIMUM_SAVE_ATTEMPTS || (!str_contains($message, 'uq_cms_route_locale_path')
                    && !str_contains($message, 'uq_cms_route_target')
                    && !str_contains($message, $this->definition->getTranslationUniqueConstraint()))) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Changes the canonical segment of one translation
     */
    public function changeUrlSegment(int $actorUserId, int $id, string $locale, string $segment): string
    {
        $normalizedLocale = strtolower(trim($locale));
        $normalizedSegment = $this->slugger->slug($segment, 180, $normalizedLocale);
        if ($normalizedSegment === '') {
            throw new DomainException('admin.url_segment.invalid');
        }

        return $this->runtime->execute($actorUserId, function (CmsContentEvents $events) use ($actorUserId, $id, $normalizedLocale, $normalizedSegment): string {
            if ($this->model->rootForUpdate($id) === null) {
                throw new DomainException($this->validation('not_found'));
            }
            if ($this->model->translationForUpdate($id, $normalizedLocale) === null && !$this->runtime->isEnabledLocale($normalizedLocale)) {
                throw new DomainException($this->validation('locale_invalid'));
            }
            $currentPath = $this->routes->reservationPath($this->definition->getModuleCode(), $id, $normalizedLocale);
            if ($currentPath !== null && basename($currentPath) === $normalizedSegment) {
                $events->noOp();

                return $normalizedSegment;
            }
            try {
                $pathPrefix = $currentPath === null
                    ? $this->definition->routePrefix($normalizedLocale)
                    : $this->pathPrefix($currentPath);
                $path = $pathPrefix === '' ? $normalizedSegment : $pathPrefix . '/' . $normalizedSegment;
                $this->routes->reserveExplicit($this->definition->getModuleCode(), $id, $normalizedLocale, $path);
            } catch (CmsRouteValidationException $exception) {
                throw new DomainException(match ($exception->getMessage()) {
                    'cms.route.path_reserved' => 'admin.url_segment.reserved',
                    'cms.route.path_occupied' => 'admin.url_segment.occupied',
                    default => 'admin.url_segment.invalid',
                }, previous: $exception);
            }
            $this->model->saveRoot($id, ['updated_by' => $actorUserId], date('Y-m-d H:i:s'));
            $this->record($events, 'updated', $id, ['locale' => $normalizedLocale]);

            return $normalizedSegment;
        });
    }

    /**
     * Soft deletes a record while preserving reserved canonical routes
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
            $this->routes->softDeleteForTarget($this->definition->getModuleCode(), $id, $now);
            $this->record($events, 'deleted', $id);
        });
    }

    /**
     * Restores all translations as drafts and reactivates their routes
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
            $this->routes->restoreForTarget($this->definition->getModuleCode(), $id, $now);
            $this->record($events, 'restored', $id);
        });
    }

    /**
     * Transfers ownership to an existing local user
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
     * Returns a module-specific validation translation key
     */
    private function validation(string $key): string
    {
        return $this->definition->getValidationPrefix() . '.validation.' . $key;
    }

    /**
     * Returns the directory of an existing canonical path without its final segment
     */
    private function pathPrefix(string $path): string
    {
        $prefix = dirname($path);

        return $prefix === '.' ? '' : $prefix;
    }

    /**
     * Records an event under the module's canonical identity
     *
     * @param array<string,mixed> $payload
     */
    private function record(CmsContentEvents $events, string $suffix, int $id, array $payload = []): void
    {
        $events->record(
            $this->definition->getModuleCode() . '.' . $suffix,
            $this->definition->getModuleCode(),
            $this->definition->getEntityType(),
            $id,
            $payload,
        );
    }
}
