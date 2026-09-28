<?php

namespace App\Pages;

use Page;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

/**
 * Today's biggest price moves, one tab per market session.
 *
 * @property int $RowLimit
 */
class MoversPage extends Page
{
    private static string $table_name = 'MoversPage';

    private static string $singular_name = 'Movers page';

    private static string $class_description = 'Today\'s movers by market session';

    private static string $cms_icon_class = 'font-icon-chart-line';

    private static array $allowed_children = [];

    private static array $db = [
        'RowLimit' => 'Int',
    ];

    private static array $defaults = [
        'RowLimit' => 25,
    ];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldToTab(
                'Root.Main',
                NumericField::create('RowLimit', 'Rows per session'),
                'Content'
            );
        });

        return parent::getCMSFields();
    }
}
