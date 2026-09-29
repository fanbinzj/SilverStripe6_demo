<?php

namespace App\Pages;

use App\Security\Membership;
use Page;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Permission;

/**
 * Previous days' movers lists. Free by default; editors can restrict it to members.
 *
 * @property bool $MembersOnly
 */
class MoversArchivePage extends Page
{
    private static string $table_name = 'MoversArchivePage';

    private static string $singular_name = 'Movers archive';

    private static string $class_description = 'Historical movers lists';

    private static string $cms_icon_class = 'font-icon-back-in-time';

    private static array $allowed_children = [];

    private static array $db = [
        'MembersOnly' => 'Boolean',
    ];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldToTab(
                'Root.Main',
                CheckboxField::create('MembersOnly', 'Members only')
                    ->setDescription('Only logged-in members can see the archive. The page itself stays in the menu.'),
                'Content'
            );
        });

        return parent::getCMSFields();
    }

    /**
     * Whether the current visitor may see the archive's data.
     * Separate from canView(): the page stays visible so visitors can see what membership offers.
     */
    public function canViewArchive(): bool
    {
        return !$this->MembersOnly || Permission::check(Membership::VIEW_ARCHIVE);
    }
}
