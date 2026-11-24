<?php

declare(strict_types=1);

namespace Lemonade\Cms\Routing\Cms;

use RuntimeException;

/**
 * Reports a rejected canonical CMS path
 */
final class CmsRouteValidationException extends RuntimeException {}
