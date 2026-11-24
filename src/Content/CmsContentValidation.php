<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use DateTimeImmutable;
use Lemonade\Cms\CmsPublicationState;

/**
 * Validates CMS transport values and content required for publication
 */
final class CmsContentValidation
{
    /**
     * Prevents construction of a stateless validator
     */
    private function __construct() {}

    /**
     * Rejects structured values before normalizing text fields
     *
     * @param array<string,mixed> $data
     * @param list<string> $fields
     * @return array<string,string|null>
     */
    public static function texts(array $data, array $fields): array
    {
        $values = [];
        $errors = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if ($value !== null && (!is_string($value) || str_contains($value, "\0"))) {
                $errors[$field] = 'admin.cms_validation.invalid';
                continue;
            }
            $value = trim($value ?? '');
            $values[$field] = $value === '' ? null : $value;
        }
        self::assert($errors);

        return $values;
    }

    /**
     * Checks a real date in editor format and supported database range
     */
    public static function validDate(string $value, bool $withTime = true): bool
    {
        $format = $withTime ? 'Y-m-d\TH:i' : 'Y-m-d';
        if (preg_match($withTime ? '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D' : '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value && $date->format('Y') >= '1000';
    }

    /**
     * Returns shared content errors for the resulting publication state
     *
     * @return array<string,string>
     */
    public static function content(CmsContentInput $input, CmsPublicationState $state, string $prefix): array
    {
        $errors = [];
        if ($state === CmsPublicationState::Published && trim($input->getTitle()) === '') {
            $errors['title'] = $prefix . '.validation.title_required';
        }
        foreach (['title' => $input->getTitle(), 'page_title' => $input->getPageTitle(), 'meta_description' => $input->getMetaDescription()] as $field => $value) {
            if ($value !== null && mb_strlen($value) > ($field === 'meta_description' ? 255 : 180)) {
                $errors[$field] = $prefix . '.validation.' . $field . '_max_length';
            }
        }
        if ($input->getLocale() === '') {
            $errors['locale'] = 'admin.cms_validation.locale_invalid';
        }

        return $errors;
    }

    /**
     * Rejects invalid fields with one structured exception
     *
     * @param array<string,string> $errors
     */
    public static function assert(array $errors): void
    {
        if ($errors !== []) {
            throw new CmsContentValidationException($errors);
        }
    }
}
