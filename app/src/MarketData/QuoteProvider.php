<?php

namespace App\MarketData;

use App\Model\MarketSession;

/**
 * Source of price data. The implementation is chosen in app/_config/marketdata.yml,
 * so a paid data provider can replace the free one without touching the importers.
 */
interface QuoteProvider
{
    /**
     * Whether this provider has data for the given trading session.
     */
    public function supportsSession(MarketSession $session): bool;

    /**
     * Latest quotes for every stock the provider covers in that session.
     *
     * @return Quote[]
     */
    public function fetchQuotes(MarketSession $session): array;

    /**
     * Short name of the data source, shown on the site next to the data.
     */
    public function getSourceName(): string;
}
