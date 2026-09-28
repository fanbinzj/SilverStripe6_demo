<?php

namespace App\Pages;

use Page;

/**
 * Upcoming earnings, FDA dates, lock-up expiries and shareholder meetings. Data is added in later stages.
 */
class CatalystCalendarPage extends Page
{
    private static string $table_name = 'CatalystCalendarPage';

    private static string $singular_name = 'Catalyst calendar';

    private static string $class_description = 'Upcoming earnings, FDA dates, lock-up expiries and shareholder meetings';

    private static string $cms_icon_class = 'font-icon-calendar';

    private static array $allowed_children = [];
}
