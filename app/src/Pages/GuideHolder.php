<?php

namespace App\Pages;

use Page;
use SilverStripe\ORM\DataList;

/**
 * "Penny Stock 101": a list of short educational guides.
 */
class GuideHolder extends Page
{
    private static string $table_name = 'GuideHolder';

    private static string $singular_name = 'Guide listing';

    private static string $class_description = 'Lists educational guides';

    private static string $cms_icon_class = 'font-icon-book';

    private static array $allowed_children = [
        GuidePage::class,
    ];

    private static $default_child = GuidePage::class;

    /**
     * @return DataList<GuidePage>
     */
    public function getGuides(): DataList
    {
        return GuidePage::get()->filter('ParentID', $this->ID)->sort('Sort');
    }
}
