<?php

namespace App\MarketData;

use App\Model\CatalystEvent;
use App\Model\Stock;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;

/**
 * Imports upcoming earnings dates for in-scope stocks as CatalystEvents.
 */
class EarningsImporter
{
    use Configurable;
    use Injectable;

    private static int $days_ahead = 21;

    public function __construct(
        private readonly EarningsCalendarProvider $provider,
    ) {
    }

    /**
     * @return int number of events imported
     */
    public function import(?int $days = null): int
    {
        $days ??= static::config()->get('days_ahead');
        $today = MarketClock::now();
        $inScope = Stock::get()->filter('InScope', true)->map('Ticker', 'ID')->toArray();

        // Fetch everything before touching the database, so a failed request leaves existing data alone
        $byDate = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $today->modify("+{$i} days");
            if ((int) $date->format('N') >= 6) {
                continue;
            }
            $byDate[$date->format('Y-m-d')] = $this->provider->fetchDate($date->format('Y-m-d'));
        }

        $count = 0;
        DB::get_conn()->withTransaction(function () use ($byDate, $inScope, &$count) {
            // Dates move, so replace this source's events in the window rather than merging
            CatalystEvent::get()->filter([
                'Type' => 'Earnings',
                'EventDate' => array_keys($byDate),
                'SourceName' => $this->provider->getSourceName(),
            ])->removeAll();

            foreach ($byDate as $date => $earnings) {
                foreach ($earnings as $earning) {
                    if (!isset($inScope[$earning->ticker])) {
                        continue;
                    }
                    CatalystEvent::create([
                        'StockID' => $inScope[$earning->ticker],
                        'Type' => 'Earnings',
                        'EventDate' => $date,
                        'IsDateConfirmed' => false,
                        'Title' => $this->title($earning),
                        'SourceName' => $this->provider->getSourceName(),
                        'SourceUrl' => $this->provider->getSourceUrl($date),
                    ])->write();
                    $count++;
                }
            }
        });

        return $count;
    }

    private function title(EarningsDate $earning): string
    {
        $parts = ['Earnings release'];
        if ($earning->fiscalQuarterEnding) {
            $parts[] = "for the quarter ending {$earning->fiscalQuarterEnding}";
        }
        if ($earning->time) {
            $parts[] = $earning->time === 'pre-market' ? '(before market open)' : '(after market close)';
        }
        return implode(' ', $parts);
    }
}
