<?php

namespace App\Search;

use App\Model\Stock;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Model\List\ArrayList;

/**
 * Finds stocks by ticker or company name.
 *
 * Ranking: ticker prefix matches first (shortest ticker first), then company
 * name matches. Done as two ORM queries rather than one raw-SQL ORDER BY with
 * CASE, so user input never has to be written into SQL by hand.
 *
 * Used by the stock directory page, and later by the JSON autocomplete endpoint.
 */
class StockSearch
{
    use Configurable;
    use Injectable;

    private static int $max_results = 50;

    private static int $max_query_length = 60;

    /**
     * Clean up user input: trim, drop a leading "$" (as in "$ABCD"), cap the length.
     */
    public function normalise(string $query): string
    {
        $query = ltrim(trim($query), '$');
        return mb_substr($query, 0, static::config()->get('max_query_length'));
    }

    /**
     * The stock whose ticker matches the query exactly, if any.
     */
    public function findExactTicker(string $query): ?Stock
    {
        $query = $this->normalise($query);
        if ($query === '') {
            return null;
        }
        return Stock::get()->filter(['Ticker' => strtoupper($query), 'IsListed' => true])->first();
    }

    /**
     * @return ArrayList<Stock>
     */
    public function search(string $query, ?int $limit = null): ArrayList
    {
        $query = $this->normalise($query);
        $limit ??= static::config()->get('max_results');
        $results = ArrayList::create();
        if ($query === '') {
            return $results;
        }

        $listed = Stock::get()->filter('IsListed', true);

        $byTicker = $listed
            ->filter('Ticker:StartsWith', strtoupper($query))
            ->limit($limit)
            ->toArray();
        // Shorter tickers first: "SND" should list SND before SNDL
        usort($byTicker, fn(Stock $a, Stock $b) => [strlen($a->Ticker), $a->Ticker] <=> [strlen($b->Ticker), $b->Ticker]);
        $results->merge($byTicker);

        $remaining = $limit - $results->count();
        if ($remaining > 0) {
            $byName = $listed->filter('Name:PartialMatch', $query);
            if ($results->count()) {
                $byName = $byName->exclude('ID', $results->column('ID'));
            }
            $results->merge($byName->sort('Name')->limit($remaining)->toArray());
        }

        return $results;
    }
}
