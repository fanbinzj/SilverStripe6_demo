<?php

namespace App\Pages;

use Page;

/**
 * Historical movers lists. Data is added in later stages.
 */
class MoversArchivePage extends Page
{
    private static string $table_name = 'MoversArchivePage';

    private static string $singular_name = 'Movers archive';

    private static string $class_description = 'Historical movers lists';

    private static string $cms_icon_class = 'font-icon-back-in-time';

    private static array $allowed_children = [];
}
