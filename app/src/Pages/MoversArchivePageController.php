<?php

namespace App\Pages;

use App\Model\MarketSession;
use App\Model\MoverEntry;
use PageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\Security;

/**
 * /movers-archive                          list of trading days
 * /movers-archive/2026-09-25               market-hours movers for that day
 * /movers-archive/2026-09-25/pre-market    another session
 *
 * @extends PageController<MoversArchivePage>
 */
class MoversArchivePageController extends PageController
{
    private static $allowed_actions = [
        'day',
    ];

    private static $url_handlers = [
        '$Date!/$Session' => 'day',
    ];

    private static int $days_per_page = 30;

    private static int $rows_per_list = 50;

    protected function init()
    {
        parent::init();

        // Checked in init() so it covers every action. permissionFailure() redirects
        // logged-out visitors to the login form and shows logged-in ones an error.
        if (!$this->data()->canViewArchive()) {
            Security::permissionFailure(
                $this,
                'The movers archive is available to members. Log in, or register for free.'
            );
        }
    }

    public function index(HTTPRequest $request)
    {
        $dates = MoverEntry::get()->sort('TradingDate', 'DESC')->columnUnique('TradingDate');
        $page = PaginatedList::create(ArrayList::create($dates), $request)
            ->setPageLength(static::config()->get('days_per_page'));

        $pageDates = $page->toArray();
        $sessionsByDate = $this->sessionsByDate($pageDates);

        $days = ArrayList::create();
        foreach ($pageDates as $date) {
            $days->push(ArrayData::create([
                'Date' => DBField::create_field('Date', $date),
                'Sessions' => $this->sessionLinks($date, $sessionsByDate[$date] ?? []),
            ]));
        }

        return [
            'Days' => $days,
            'Pagination' => $page,
        ];
    }

    public function day(HTTPRequest $request): HTTPResponse|array
    {
        $date = (string) $request->param('Date');
        $session = MarketSession::tryFrom((string) ($request->param('Session') ?: MarketSession::Regular->value));

        if (!$session || !self::isValidDate($date)) {
            return $this->httpError(404);
        }

        $limit = static::config()->get('rows_per_list');
        $gainers = MoverEntry::gainers($session, $date, $limit);
        $losers = MoverEntry::losers($session, $date, $limit);
        if (!$gainers->exists() && !$losers->exists()) {
            return $this->httpError(404, 'No movers recorded for that day and session');
        }

        return [
            'Title' => "Movers for {$date}",
            'TradingDate' => DBField::create_field('Date', $date),
            'SessionTitle' => $session->label(),
            'SessionTabs' => $this->sessionLinks($date, $this->sessionsByDate([$date])[$date] ?? [], $session),
            'Gainers' => $gainers,
            'Losers' => $losers,
        ];
    }

    /**
     * Which sessions have movers on each date, in one query.
     *
     * The ORM has no "distinct pairs" helper, so this drops down to SQLSelect,
     * still with parameterised values rather than values written into the SQL.
     *
     * @param string[] $dates
     * @return array<string, string[]> date => session values
     */
    private function sessionsByDate(array $dates): array
    {
        if (!$dates) {
            return [];
        }
        $query = SQLSelect::create(['"TradingDate"', '"Session"'], '"MoverEntry"')
            ->setDistinct(true)
            ->addWhere(['"TradingDate" IN (' . DB::placeholders($dates) . ')' => $dates]);

        $result = [];
        foreach ($query->execute() as $row) {
            $result[$row['TradingDate']][] = $row['Session'];
        }
        return $result;
    }

    /**
     * @param string[] $available session values with data
     */
    private function sessionLinks(string $date, array $available, ?MarketSession $current = null): ArrayList
    {
        $links = ArrayList::create();
        foreach (MarketSession::cases() as $session) {
            if (!in_array($session->value, $available, true)) {
                continue;
            }
            $links->push(ArrayData::create([
                'Title' => $session->label(),
                'Link' => $session === MarketSession::Regular
                    ? $this->Link($date)
                    : $this->Link("{$date}/{$session->value}"),
                'IsCurrent' => $session === $current,
            ]));
        }
        return $links;
    }

    private static function isValidDate(string $date): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
