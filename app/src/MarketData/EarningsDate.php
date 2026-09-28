<?php

namespace App\MarketData;

/**
 * A scheduled earnings release, as returned by an EarningsCalendarProvider.
 */
final readonly class EarningsDate
{
    public function __construct(
        public string $ticker,
        public string $date,
        // 'pre-market', 'after-hours' or null if not stated
        public ?string $time,
        // e.g. "Aug/2026"
        public string $fiscalQuarterEnding,
    ) {
    }
}
