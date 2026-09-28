<?php

namespace App\Model;

/**
 * US equity trading sessions (Eastern Time).
 */
enum MarketSession: string
{
    case PreMarket = 'pre-market';
    case Regular = 'market-hours';
    case AfterHours = 'after-hours';

    public function label(): string
    {
        return match ($this) {
            self::PreMarket => 'Pre-market',
            self::Regular => 'Market hours',
            self::AfterHours => 'After-hours',
        };
    }

    public function hours(): string
    {
        return match ($this) {
            self::PreMarket => '4:00 am - 9:30 am ET',
            self::Regular => '9:30 am - 4:00 pm ET',
            self::AfterHours => '4:00 pm - 8:00 pm ET',
        };
    }
}
