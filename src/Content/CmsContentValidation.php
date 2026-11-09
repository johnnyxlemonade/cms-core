<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use DateTimeImmutable;
use Lemonade\Cms\CmsPublicationState;

/**
 * Sdili typovou kontrolu CMS vstupu a povinnost obsahu podle vysledne publikace
 */
final class CmsContentValidation
{
    /**
     * Brani vytvareni instance stateless validace
     */
    private function __construct() {}

    /**
     * Odmitne strukturovane hodnoty pred normalizaci textovych poli
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
     * Overi skutecne datum ve formatu editoru a rozsahu databaze
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
     * Vrati vsechny chyby spolecneho obsahu pro vysledny stav
     *
     * @return array<string,string>
     */
    public static function content(CmsContentInput $input, CmsPublicationState $state, string $prefix): array
    {
        $errors = [];
        if ($state === CmsPublicationState::Published && trim($input->title) === '') {
            $errors['title'] = $prefix . '.validation.title_required';
        }
        foreach (['title' => $input->title, 'page_title' => $input->pageTitle, 'meta_description' => $input->metaDescription] as $field => $value) {
            if ($value !== null && mb_strlen($value) > ($field === 'meta_description' ? 255 : 180)) {
                $errors[$field] = $prefix . '.validation.' . $field . '_max_length';
            }
        }
        if ($input->locale === '') {
            $errors['locale'] = 'admin.cms_validation.locale_invalid';
        }

        return $errors;
    }

    /**
     * Odmitne neplatna pole jedinou strukturovanou vyjimkou
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
