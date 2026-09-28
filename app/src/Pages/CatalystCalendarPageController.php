<?php

namespace App\Pages;

use App\MarketData\MarketClock;
use App\Model\CatalystEvent;
use PageController;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\GroupedList;

/**
 * /catalyst-calendar                 upcoming events for in-scope stocks, grouped by date
 * /catalyst-calendar?type=Earnings   one event type
 *
 * @extends PageController<CatalystCalendarPage>
 */
class CatalystCalendarPageController extends PageController
{
    private static int $days_ahead = 30;

    public function index()
    {
        $type = $this->currentType();
        $today = MarketClock::now();

        $events = CatalystEvent::get()
            ->filter([
                'Stock.InScope' => true,
                'EventDate:GreaterThanOrEqual' => $today->format('Y-m-d'),
                'EventDate:LessThanOrEqual' => $today->modify('+' . static::config()->get('days_ahead') . ' days')->format('Y-m-d'),
            ])
            ->sort(['EventDate' => 'ASC', 'ID' => 'ASC'])
            ->eagerLoad('Stock');
        if ($type) {
            $events = $events->filter('Type', $type);
        }

        return [
            // GroupedList lets the template loop over dates, then events on each date
            'EventsByDate' => GroupedList::create($events),
            'TypeFilters' => $this->typeFilters($type),
            'DaysAhead' => static::config()->get('days_ahead'),
        ];
    }

    private function currentType(): ?string
    {
        $type = (string) $this->getRequest()->getVar('type');
        return array_key_exists($type, CatalystEvent::TYPE_LABELS) ? $type : null;
    }

    private function typeFilters(?string $current): ArrayList
    {
        $filters = ArrayList::create([
            ArrayData::create(['Title' => 'All', 'Link' => $this->Link(), 'IsCurrent' => $current === null]),
        ]);
        foreach (CatalystEvent::TYPE_LABELS as $key => $label) {
            if ($key === 'Other') {
                continue;
            }
            $filters->push(ArrayData::create([
                'Title' => $label,
                'Link' => $this->Link() . '?type=' . rawurlencode($key),
                'IsCurrent' => $current === $key,
            ]));
        }
        return $filters;
    }
}
