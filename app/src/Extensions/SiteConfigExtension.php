<?php

namespace App\Extensions;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Site-wide footer settings (Settings > Footer in the CMS).
 *
 * @property string $FooterNote
 * @property int $DisclaimerPageID
 * @method SiteTree DisclaimerPage()
 * @extends Extension<SiteConfig>
 */
class SiteConfigExtension extends Extension
{
    private static array $db = [
        'FooterNote' => 'Text',
    ];

    private static array $has_one = [
        'DisclaimerPage' => SiteTree::class,
    ];

    protected function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldsToTab('Root.Footer', [
            TextareaField::create('FooterNote', 'Footer note')
                ->setDescription('Short notice shown on every page, e.g. "Information only. Not financial advice."'),
            TreeDropdownField::create('DisclaimerPageID', 'Disclaimer page', SiteTree::class),
        ]);
    }
}
