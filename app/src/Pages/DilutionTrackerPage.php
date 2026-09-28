<?php

namespace App\Pages;

use Page;

/**
 * Feed of new S-1, S-3 and 424B filings. Data is added in later stages.
 */
class DilutionTrackerPage extends Page
{
    private static string $table_name = 'DilutionTrackerPage';

    private static string $singular_name = 'Dilution tracker';

    private static string $class_description = 'Feed of new S-1, S-3 and 424B filings';

    private static string $cms_icon_class = 'font-icon-rocket';

    private static array $allowed_children = [];
}
