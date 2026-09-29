<?php

namespace App\Pages;

use Page;

/**
 * Public sign-up form for website members. Create only one.
 */
class RegistrationPage extends Page
{
    private static string $table_name = 'RegistrationPage';

    private static string $singular_name = 'Registration page';

    private static string $class_description = 'Sign-up form for website members';

    private static string $cms_icon_class = 'font-icon-torso';

    private static array $allowed_children = [];

    public function canCreate($member = null, $context = [])
    {
        return static::get()->exists() ? false : parent::canCreate($member, $context);
    }
}
