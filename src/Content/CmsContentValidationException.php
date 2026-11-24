<?php

declare(strict_types=1);

namespace Lemonade\Cms\Content;

use DomainException;

/**
 * Carries validation translation keys indexed by content field
 */
final class CmsContentValidationException extends DomainException
{
    /**
     * Preserves field errors and the first error as exception message
     *
     * @param array<string,string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(array_values($errors)[0] ?? 'admin.cms_validation.invalid');
    }

    /**
     * Returns translation keys indexed by transport field names
     *
     * @return array<string,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
