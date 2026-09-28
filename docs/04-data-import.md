# Stage 4: Data Import (Injector, Providers, Build Tasks, Queued Jobs)

## What was built

```
                    ┌─────────────────────────┐
QuoteProvider  ───▶ │ QuoteImporter           │ ──▶ Stock price, market cap, InScope; MoverEntry
(interface)         └─────────────────────────┘
  └ NasdaqScreenerQuoteProvider

SecClient ────────▶ SecCompanyImporter        ──▶ Stock background + XBRL figures, NameChange, Filing
  └ XbrlFacts (parsing)

EarningsCalendarProvider ─▶ EarningsImporter  ──▶ CatalystEvent (Earnings)
  └ NasdaqEarningsCalendarProvider

Tasks (run by hand):   sake tasks:update-quotes | import-sec-company-data | import-earnings-calendar
Jobs (run on a queue): UpdateQuotesJob (15 min) | ImportSecCompanyDataJob (nightly) | ImportEarningsCalendarJob (daily)
```

| Piece | File |
|---|---|
| Data layer | `app/src/MarketData/` |
| Service wiring | `app/_config/marketdata.yml` |
| Tasks | `app/src/Tasks/{UpdateQuotes,ImportSecCompanyData,ImportEarningsCalendar}Task.php` |
| Jobs | `app/src/Jobs/` + `app/_config/queuedjobs.yml` |
| Pages now showing data | Today's Movers, Dilution Tracker, Catalyst Calendar, stock profiles |

```bash
vendor/bin/sake tasks:update-quotes                         # ~7s; marks ~2,750 stocks in scope
vendor/bin/sake tasks:import-sec-company-data --limit=20    # full run: ~45 min
vendor/bin/sake tasks:import-sec-company-data --ticker=ABEO
vendor/bin/sake tasks:import-earnings-calendar
vendor/bin/sake tasks:ProcessJobQueueTask                   # run due queued jobs (normally from cron)
```

## 1. Scope rules

A stock is **in scope** when it is listed on NASDAQ/NYSE (SEC list), is common equity, and has a market cap up to
`QuoteImporter.max_market_cap` (USD 1 billion). Common equity is decided from the security name: warrants, rights,
units, preferred shares, notes and funds are excluded; American Depositary Shares are kept. Without this, warrants
trading at $0.002 filled the top of the movers list.

Known gaps (prototype): closed-end funds whose names don't include "fund" still get through, and market holidays
are treated as trading days.

## 2. The Injector: interfaces bound in YAML

```yaml
SilverStripe\Core\Injector\Injector:
  GuzzleHttp\ClientInterface:
    class: GuzzleHttp\Client
  App\MarketData\QuoteProvider:
    class: App\MarketData\NasdaqScreenerQuoteProvider
    constructor:
      - '%$GuzzleHttp\ClientInterface'
  App\MarketData\QuoteImporter:
    constructor:
      - '%$App\MarketData\QuoteProvider'
```

- A service can be named after an **interface**; `class:` says which implementation to build.
- `constructor:` lists constructor arguments; `%$Name` means "the Injector service called Name". The Injector
  does not autowire constructors, so dependencies are listed explicitly.
- `QuoteImporter::singleton()` asks the Injector for the shared instance, fully wired.
- Two styles appear in this project: **constructor injection** for plain services (above), and **`$dependencies`
  + setters** for controllers (`MoversPageController`), since controllers are created by the framework with fixed
  constructor arguments.

Why it matters here:

- **Swapping providers**: the free source has no pre-market or after-hours data. A paid provider (e.g. one with an
  API key) is one new class plus one YAML line; the importers, jobs and pages don't change. The movers page already
  asks `QuoteProvider::supportsSession()` and shows "not available from our current data source" otherwise.
- **Testing**: every HTTP call goes through `GuzzleHttp\ClientInterface`, so tests can bind it to a client with a
  Guzzle `MockHandler` and feed in saved JSON responses (stage 7).

## 3. Working with SEC EDGAR

- **Fair access**: `SecClient` sends `SEC_USER_AGENT` and waits at least 125 ms between requests (under the SEC's
  10 per second limit).
- **Submissions API** (`/submissions/CIK##########.json`): state of incorporation, industry, former names and the
  last ~1,000 filings as parallel arrays (`form[i]`, `filingDate[i]`, ...). Only offering-related forms from the last
  5 years are stored.
- **Company facts API** (`/api/xbrl/companyfacts/...`): every XBRL value the company has reported. `XbrlFacts`
  handles two traps:
  - The same period is reported several times (original filing, next year's comparative, amendments). For each
    period, the **latest filed** value wins.
  - Cash flow is reported **year to date**. A Q2 10-Q reports January to June, so the latest quarter is worked out
    as (Jan–Jun) − (Jan–Mar). For ABEO: −37.3M − (−19.8M) = −17.5M.
- Anything missing is stored as unknown (empty "as of" date), never as zero.

## 4. Timestamps must describe the data, not the fetch

The price feed has no timestamp. Fetching on Sunday returned Friday's closing prices, but the first version saved
them "as of Sunday 1:15 AM". `MarketClock::regularSessionPriceTime()` now works out what the price is: the live
price during market hours, otherwise the close (4pm ET) of the last trading day. Movers use the same date.
`MarketClock` uses `DBDatetime::now()`, so tests can pin the time with `DBDatetime::set_mock_now()`.

## 5. Build tasks with options

```php
public function getOptions(): array
{
    return [
        new InputOption('ticker', null, InputOption::VALUE_REQUIRED, 'Import a single stock'),
        new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of stocks', 0),
    ];
}
// in execute(): $input->getOption('ticker')
```

CMS 6 tasks use Symfony Console options, so `sake tasks:import-sec-company-data --help` documents them. Tasks exit
with `Command::SUCCESS`, `FAILURE` or `INVALID`, which matters when they run from cron or CI.

## 6. Queued jobs

Long or repeating work runs through `symbiote/silverstripe-queuedjobs` rather than inside a web request or a
single cron command:

- **Jobs are records** (`QueuedJobDescriptor`) with a status, start time, progress and messages, visible in the CMS
  under "Jobs".
- **A worker** runs due jobs: `sake tasks:ProcessJobQueueTask` from cron every minute.
- **Queues**: `QUEUED` for normal jobs, `LARGE` for long ones (the SEC import) so they don't delay quick jobs.
- **Steps and resuming**: `ImportSecCompanyDataJob::setup()` stores the list of stock IDs and `totalSteps`; each
  `process()` call handles one stock and increments `currentStep`. Job properties (`$this->stockIDs`) are saved to
  the database between steps. When the job crashed at step 139 during development, it resumed from step 139.
- **Recurring jobs**: each job queues its next run in `afterComplete()` (`SchedulesNextRun` trait).
  `defaultJobs` in `app/_config/queuedjobs.yml` is the safety net: if a job disappears, the queue recreates it.
- **Deduplication**: `queueJob()` won't add a job whose signature (class + data) is already waiting.
- **Locking**: a job's `Worker` column acts as a mutex so two workers can't run it at once; stalled jobs are
  detected by the health check once their lock expires.

Cron in production:

```
* * * * * cd /var/www/pennymirror && vendor/bin/sake tasks:ProcessJobQueueTask
* * * * * cd /var/www/pennymirror && vendor/bin/sake tasks:ProcessJobQueueTask --queue=large
```

## 7. Performance lessons from this stage

- **Skip no-op writes**: `DataObject::write()` runs validation and extension hooks even when nothing changed
  (~1.6 ms each). Checking `$stock->isChanged(null, DataObject::CHANGE_VALUE)` first took the quote import from
  12 s of CPU to 0.6 s.
- **Eager loading**: movers, filings and calendar lists call `->eagerLoad('Stock')`, so rows share one query for
  their stocks instead of one query each (N+1).
- **Short transactions**: SQLite allows one writer at a time. During development a 16-second import transaction
  made the SEC job fail with `database is locked`. MySQL locks rows rather than the whole database, but long
  transactions block other writers there too. Keep them short, and don't run two writing processes against SQLite.
- **Fetch before you delete**: `EarningsImporter` fetches every date first and only then replaces events inside a
  transaction, so a failed request leaves the existing data intact.

## 8. Template details found while building the pages

- `DataList::max()` returns a plain string. Wrap it with `DBField::create_field('Date', $value)` to use `.Nice`
  in a template.
- `Trading day $TradingDate.Nice.` does not work: the trailing `.` confuses the parser. Use braces:
  `{$TradingDate.Nice}.`
- `GroupedList::create($events)` + `<% loop $EventsByDate.GroupedBy('EventDate') %>` groups calendar events by day;
  each group's items are in `$Children`.
- Filter links read `?form=` / `?type=` and compare them against a whitelist; raw input never reaches a query.

## 9. Try it yourself

1. Run `sake tasks:import-sec-company-data --ticker=<a ticker you know>` and compare the profile with its 10-Q.
2. Change `App\MarketData\QuoteImporter.max_market_cap` to `300000000` in YAML, flush, re-run `update-quotes`, and
   check how many stocks are in scope.
3. Queue a job by hand from the "Jobs" CMS section and run `sake tasks:ProcessJobQueueTask`.
4. Write a `NullQuoteProvider` that supports no sessions, bind it in YAML, and see how the movers page responds.

## 10. Self-check Q&A

1. **How do you swap an implementation without changing the code that uses it?**
   Depend on an interface and bind the interface to a class in Injector YAML.
2. **Why queued jobs instead of a long cron script?**
   Progress is saved so work resumes after a crash, jobs are visible and retryable in the CMS, long jobs get their
   own queue, and duplicate runs are prevented.
3. **Why does `write()` on an unchanged object still cost time?**
   It still runs validation, `onBeforeWrite`/`onAfterWrite` and extension hooks before deciding there is nothing
   to save.
4. **What causes "database is locked" on SQLite, and what is the general lesson?**
   Two connections writing at once; long transactions block other writers on any database.
5. **What is the N+1 query problem and how does `eagerLoad()` fix it?**
   One query for the list plus one per row for a relation; `eagerLoad()` fetches the related records in one query.
6. **Why store an "as of" time that describes the data rather than when it was fetched?**
   Because users read it as the time the price applied; a fetch time can be days later than the data.
