<?php

declare(strict_types=1);

namespace Lemonade\Cms;

/**
 * Rozlisuje novou, rozpracovanou a verejne publikovanou content mutaci
 */
enum CmsPublicationState: int
{
    case New = 0;
    case Draft = 1;
    case Published = 2;
}
