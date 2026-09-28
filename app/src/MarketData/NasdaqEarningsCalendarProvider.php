<?php

namespace App\MarketData;

use GuzzleHttp\ClientInterface;

/**
 * Earnings dates from the public Nasdaq.com earnings calendar (unofficial endpoint, prototype only).
 * Analyst estimates in the feed are deliberately not imported.
 */
class NasdaqEarningsCalendarProvider implements EarningsCalendarProvider
{
    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    public function fetchDate(string $date): array
    {
        $response = $this->client->request('GET', 'https://api.nasdaq.com/api/calendar/earnings', [
            'query' => ['date' => $date],
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; PennyMirror prototype)', 'Accept' => 'application/json'],
            'timeout' => 30,
        ]);
        $json = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        $dates = [];
        foreach ($json['data']['rows'] ?? [] as $row) {
            $dates[] = new EarningsDate(
                ticker: NasdaqScreenerQuoteProvider::normaliseTicker($row['symbol']),
                date: $date,
                time: match ($row['time'] ?? '') {
                    'time-pre-market' => 'pre-market',
                    'time-after-hours' => 'after-hours',
                    default => null,
                },
                fiscalQuarterEnding: (string) ($row['fiscalQuarterEnding'] ?? ''),
            );
        }
        return $dates;
    }

    public function getSourceName(): string
    {
        return 'Nasdaq.com earnings calendar';
    }

    public function getSourceUrl(string $date): string
    {
        return 'https://www.nasdaq.com/market-activity/earnings';
    }
}
