<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use DomainException;

/**
 * Predava prekladove klice validacnich chyb pod jmeny obsahovych poli
 */
final class CmsContentValidationException extends DomainException
{
    /**
     * Zachova konkretni pole i puvodni klic prvni chyby
     *
     * @param array<string,string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(array_values($errors)[0] ?? 'admin.cms_validation.invalid');
    }

    /**
     * Vrati prekladove klice podle transportnich jmen poli
     *
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
