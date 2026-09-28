<?php

namespace App\Admin;

use App\Model\CatalystEvent;
use App\Model\Filing;
use App\Model\MoverEntry;
use App\Model\Stock;
use SilverStripe\Admin\ModelAdmin;

/**
 * CMS section for stocks and their related data: /admin/market-data
 */
class MarketDataAdmin extends ModelAdmin
{
    private static $url_segment = 'market-data';

    private static $menu_title = 'Market data';

    private static $menu_icon_class = 'font-icon-chart-line';

    private static $managed_models = [
        Stock::class,
        Filing::class,
        CatalystEvent::class,
        MoverEntry::class,
    ];

    private static $page_length = 50;
}
