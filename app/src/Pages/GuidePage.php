<?php

namespace App\Pages;

use Page;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;

/**
 * A single educational guide, e.g. "What is a reverse stock split?".
 *
 * @property string $Summary
 */
class GuidePage extends Page
{
    private static string $table_name = 'GuidePage';

    private static string $singular_name = 'Guide';

    private static string $class_description = 'A short educational article';

    private static string $cms_icon_class = 'font-icon-p-article';

    private static bool $can_be_root = false;

    private static array $allowed_children = [];

    private static array $db = [
        'Summary' => 'Text',
    ];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldToTab(
                'Root.Main',
                TextareaField::create('Summary')->setDescription('One or two sentences shown on the guide listing'),
                'Content'
            );
        });

        return parent::getCMSFields();
    }
}
