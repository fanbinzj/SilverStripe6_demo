<?php

namespace App\MarketData;

use App\Model\MarketSession;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;

/**
 * Regular-session quotes for all NASDAQ/NYSE stocks from the public Nasdaq.com stock screener.
 *
 * Unofficial endpoint, used for the prototype only. It has no pre-market or after-hours data.
 */
class NasdaqScreenerQuoteProvider implements QuoteProvider
{
    use Configurable;

    /**
     * Security names matching this are not common equity. American Depositary Shares
     * are kept: they are how many foreign companies list their ordinary shares in the US.
     */
    private static string $non_common_equity_pattern = '/\\b(warrants?|rights?|units?|preferred|notes|debentures?|fund)\\b/i';

    private const string URL = 'https://api.nasdaq.com/api/screener/stocks?tableonly=true&download=true';

    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    public function supportsSession(MarketSession $session): bool
    {
        return $session === MarketSession::Regular;
    }

    public function fetchQuotes(MarketSession $session): array
    {
        if (!$this->supportsSession($session)) {
            throw new InvalidArgumentException("Nasdaq screener has no {$session->label()} data");
        }

        $response = $this->client->request('GET', self::URL, [
            // The endpoint rejects requests without a browser-like user agent
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; PennyMirror prototype)', 'Accept' => 'application/json'],
            'timeout' => 60,
        ]);
        $json = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        $quotes = [];
        foreach ($json['data']['rows'] ?? [] as $row) {
            $price = self::parseNumber($row['lastsale'] ?? '');
            if ($price === null || $price <= 0) {
                continue;
            }
            $marketCap = self::parseNumber($row['marketCap'] ?? '');
            $quotes[] = new Quote(
                ticker: self::normaliseTicker($row['symbol']),
                price: $price,
                changePercent: self::parseNumber($row['pctchange'] ?? '') ?? 0.0,
                volume: (int) self::parseNumber($row['volume'] ?? ''),
                marketCap: $marketCap ? (int) round($marketCap) : null,
                isCommonEquity: !preg_match(static::config()->get('non_common_equity_pattern'), $row['name'] ?? ''),
            );
        }
        return $quotes;
    }

    public function getSourceName(): string
    {
        return 'Nasdaq.com';
    }

    /**
     * "$1,234.56", "-4.549%", "48728583125.00" -> float; "" or "NA" -> null
     */
    public static function parseNumber(string $value): ?float
    {
        $clean = str_replace(['$', ',', '%', '+'], '', trim($value));
        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Nasdaq writes share classes as "BRK/B"; the SEC and this site use "BRK-B".
     */
    public static function normaliseTicker(string $symbol): string
    {
        return strtoupper(str_replace(['/', '^'], ['-', '-P'], trim($symbol)));
    }
}
