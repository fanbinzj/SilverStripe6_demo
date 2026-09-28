<?php

namespace App\MarketData;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;

/**
 * Minimal client for SEC EDGAR's JSON APIs.
 *
 * Follows the SEC's fair access rules: identifies itself with SEC_USER_AGENT
 * and stays under 10 requests per second.
 * https://www.sec.gov/os/accessing-edgar-data
 */
class SecClient
{
    use Configurable;

    private static int $min_interval_ms = 125; // at most 8 requests per second

    private float $lastRequestAt = 0;

    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    /**
     * https://data.sec.gov/submissions/CIK##########.json
     */
    public function getSubmissions(int $cik): ?array
    {
        return $this->getJson(sprintf('https://data.sec.gov/submissions/CIK%010d.json', $cik));
    }

    /**
     * https://data.sec.gov/api/xbrl/companyfacts/CIK##########.json
     * Returns null for companies with no XBRL financial data.
     */
    public function getCompanyFacts(int $cik): ?array
    {
        return $this->getJson(sprintf('https://data.sec.gov/api/xbrl/companyfacts/CIK%010d.json', $cik));
    }

    private function getJson(string $url): ?array
    {
        $this->throttle();

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => ['User-Agent' => $this->getUserAgent(), 'Accept' => 'application/json'],
                'timeout' => 30,
            ]);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function throttle(): void
    {
        $minInterval = static::config()->get('min_interval_ms') / 1000;
        $wait = $this->lastRequestAt + $minInterval - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);
    }

    private function getUserAgent(): string
    {
        $userAgent = Environment::getEnv('SEC_USER_AGENT');
        if (!$userAgent) {
            throw new RuntimeException('Set SEC_USER_AGENT in .env (see .env.example)');
        }
        return $userAgent;
    }
}
