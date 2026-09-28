<?php

namespace App\MarketData;

/**
 * A price snapshot for one stock, as returned by a QuoteProvider.
 */
final readonly class Quote
{
    public function __construct(
        public string $ticker,
        public float $price,
        public float $changePercent,
        public int $volume,
        public ?int $marketCap,
        // False for warrants, rights, units, preferred shares, notes and funds
        public bool $isCommonEquity = true,
    ) {
    }
}
