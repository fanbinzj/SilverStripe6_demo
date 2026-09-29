<?php

namespace App\Pages;

use Page;

/**
 * The logged-in member's account: details and watchlist. Create only one.
 */
class AccountPage extends Page
{
    private static string $table_name = 'AccountPage';

    private static string $singular_name = 'Account page';

    private static string $class_description = 'A member\'s account and watchlist';

    private static string $cms_icon_class = 'font-icon-torsos-all';

    private static array $allowed_children = [];

    public function canCreate($member = null, $context = [])
    {
        return static::get()->exists() ? false : parent::canCreate($member, $context);
    }
}
