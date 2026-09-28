<?php

namespace App\Pages;

use Page;

/**
 * Parent URL for stock profiles (/stocks/ABCD) and ticker search results (/stocks?q=).
 *
 * Stocks are DataObjects rather than pages: there are thousands of them and their
 * content comes from data imports, not editors. See stage 3.
 */
class StockDirectoryPage extends Page
{
    private static string $table_name = 'StockDirectoryPage';

    private static string $singular_name = 'Stock directory';

    private static string $class_description = 'Ticker search results and stock profiles. Create only one.';

    private static string $cms_icon_class = 'font-icon-search';

    private static array $allowed_children = [];

    /**
     * The single stock directory page, cached for the request since Stock::Link() calls this
     * for every stock in a list.
     */
    public static function get_instance(): ?static
    {
        static $instance = false;
        if ($instance === false) {
            $instance = static::get()->first();
        }
        return $instance;
    }

    public function canCreate($member = null, $context = [])
    {
        // Only one stock directory makes sense
        if (static::get()->exists()) {
            return false;
        }
        return parent::canCreate($member, $context);
    }
}
