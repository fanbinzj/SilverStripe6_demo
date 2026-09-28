<?php

namespace App\MarketData;

use App\Model\MarketSession;
use App\Model\MoverEntry;
use App\Model\Stock;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;

/**
 * Applies quotes to stocks and builds the movers lists.
 *
 * For the regular session it also updates each stock's price and market cap,
 * and decides which stocks are in scope: listed common equity (not warrants, units etc.)
 * with a market cap up to max_market_cap.
 */
class QuoteImporter
{
    use Configurable;
    use Injectable;

    private static int $max_market_cap = 1_000_000_000;

    // Stocks trading fewer shares than this are left out of movers lists
    private static int $movers_min_volume = 10_000;

    // Gainers and losers kept per session per day
    private static int $movers_per_direction = 50;

    public function __construct(
        private readonly QuoteProvider $provider,
    ) {
    }

    public function getProvider(): QuoteProvider
    {
        return $this->provider;
    }

    /**
     * @return array{quotes: int, updated: int, inScope: int, movers: int}|null null if the provider has no data for the session
     */
    public function import(MarketSession $session): ?array
    {
        if (!$this->provider->supportsSession($session)) {
            return null;
        }

        $quotes = [];
        foreach ($this->provider->fetchQuotes($session) as $quote) {
            $quotes[$quote->ticker] = $quote;
        }

        $stats = ['quotes' => count($quotes), 'updated' => 0, 'inScope' => 0, 'movers' => 0];

        DB::get_conn()->withTransaction(function () use ($session, $quotes, &$stats) {
            $stocks = Stock::get()->filter('IsListed', true);
            if ($session === MarketSession::Regular) {
                $stats['updated'] = $this->updateStocks($stocks, $quotes);
            }
            $stats['inScope'] = Stock::get()->filter('InScope', true)->count();
            $stats['movers'] = $this->updateMovers($session, $quotes);
        });

        return $stats;
    }

    /**
     * @param Quote[] $quotes keyed by ticker
     */
    private function updateStocks(iterable $stocks, array $quotes): int
    {
        $maxMarketCap = static::config()->get('max_market_cap');
        // The provider doesn't timestamp quotes, so work out which close (or live price) they are
        $priceTime = MarketClock::regularSessionPriceTime();
        $updated = 0;

        foreach ($stocks as $stock) {
            $quote = $quotes[$stock->Ticker] ?? null;
            if (!$quote) {
                // Keep the last known price; no quote means we can't confirm it's in scope
                if ($stock->InScope && !$stock->PriceAsOf) {
                    $stock->InScope = false;
                    $stock->write();
                }
                continue;
            }

            $stock->LastPrice = $quote->price;
            $stock->PriceAsOf = $priceTime;
            $stock->MarketCap = $quote->marketCap ?? 0;
            $stock->InScope = $quote->isCommonEquity
                && $quote->marketCap > 0
                && $quote->marketCap <= $maxMarketCap;

            // write() runs validation and extension hooks even when nothing changed,
            // which adds up over thousands of stocks; skip it when the values are the same
            if ($stock->isChanged(null, DataObject::CHANGE_VALUE)) {
                $stock->write();
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Replace today's movers for the session with the biggest gainers and losers among in-scope stocks.
     *
     * @param Quote[] $quotes keyed by ticker
     */
    private function updateMovers(MarketSession $session, array $quotes): int
    {
        // Regular-session data fetched before today's open still belongs to the previous trading day
        $tradingDate = $session === MarketSession::Regular
            ? substr(MarketClock::regularSessionPriceTime(), 0, 10)
            : MarketClock::lastTradingDate();
        $minVolume = static::config()->get('movers_min_volume');
        $perDirection = static::config()->get('movers_per_direction');

        $inScope = Stock::get()->filter('InScope', true)->map('Ticker', 'ID')->toArray();
        $candidates = array_filter(
            $quotes,
            fn(Quote $q) => isset($inScope[$q->ticker]) && $q->volume >= $minVolume && $q->changePercent != 0
        );

        usort($candidates, fn(Quote $a, Quote $b) => $b->changePercent <=> $a->changePercent);
        $gainers = array_slice(array_filter($candidates, fn(Quote $q) => $q->changePercent > 0), 0, $perDirection);
        $losers = array_slice(array_reverse(array_filter($candidates, fn(Quote $q) => $q->changePercent < 0)), 0, $perDirection);

        MoverEntry::get()->filter(['TradingDate' => $tradingDate, 'Session' => $session->value])->removeAll();

        $count = 0;
        foreach ([$gainers, $losers] as $list) {
            foreach (array_values($list) as $i => $quote) {
                MoverEntry::create([
                    'StockID' => $inScope[$quote->ticker],
                    'TradingDate' => $tradingDate,
                    'Session' => $session->value,
                    'Rank' => $i + 1,
                    'Price' => $quote->price,
                    'ChangePercent' => $quote->changePercent,
                    'Volume' => $quote->volume,
                ])->write();
                $count++;
            }
        }
        return $count;
    }
}
