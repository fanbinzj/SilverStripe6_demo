<?php

namespace App\MarketData;

use App\Model\MarketSession;
use DateTimeImmutable;
use DateTimeZone;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * US equity market hours in Eastern Time.
 *
 * Uses DBDatetime::now() so tests can fix the time with DBDatetime::set_mock_now().
 * Market holidays are not handled yet: on a holiday the last trading date is wrong.
 */
class MarketClock
{
    public static function now(): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . DBDatetime::now()->getTimestamp()))
            ->setTimezone(new DateTimeZone('America/New_York'));
    }

    /**
     * The session trading right now, or null outside trading hours and at weekends.
     */
    public static function currentSession(?DateTimeImmutable $at = null): ?MarketSession
    {
        $at ??= static::now();
        if ((int) $at->format('N') >= 6) {
            return null;
        }
        $time = $at->format('H:i');
        return match (true) {
            $time >= '04:00' && $time < '09:30' => MarketSession::PreMarket,
            $time >= '09:30' && $time < '16:00' => MarketSession::Regular,
            $time >= '16:00' && $time < '20:00' => MarketSession::AfterHours,
            default => null,
        };
    }

    /**
     * When a regular-session price fetched now was set: now during market hours,
     * otherwise the close (4pm ET) of the last trading day.
     */
    public static function regularSessionPriceTime(?DateTimeImmutable $at = null): string
    {
        $at ??= static::now();
        if (static::currentSession($at) === MarketSession::Regular) {
            return $at->format('Y-m-d H:i:s');
        }
        $date = static::lastTradingDate($at);
        // Before the open, the last close was the previous trading day
        if ($date === $at->format('Y-m-d') && $at->format('H:i') < '16:00') {
            $date = static::lastTradingDate($at->modify('-1 day')->setTime(23, 0));
        }
        return "{$date} 16:00:00";
    }

    /**
     * The most recent weekday on which the market has opened (today once it is 4am ET).
     */
    public static function lastTradingDate(?DateTimeImmutable $at = null): string
    {
        $at ??= static::now();
        if ($at->format('H:i') < '04:00') {
            $at = $at->modify('-1 day');
        }
        while ((int) $at->format('N') >= 6) {
            $at = $at->modify('-1 day');
        }
        return $at->format('Y-m-d');
    }
}
