<?php

namespace App\Pages;

use Page;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;

/**
 * @property string $HeroTitle
 * @property string $HeroIntro
 */
class HomePage extends Page
{
    private static string $table_name = 'HomePage';

    private static string $class_description = 'The site home page, with ticker search';

    private static string $cms_icon_class = 'font-icon-p-home';

    private static array $db = [
        'HeroTitle' => 'Varchar(255)',
        'HeroIntro' => 'Text',
    ];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldsToTab('Root.Hero', [
                TextField::create('HeroTitle', 'Hero title'),
                TextareaField::create('HeroIntro', 'Hero introduction'),
            ]);
        });

        return parent::getCMSFields();
    }
}
