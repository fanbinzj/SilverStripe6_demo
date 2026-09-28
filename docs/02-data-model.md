# Stage 2: Data Model (DataObjects, Relations, ModelAdmin)

## What was built

```
Stock ──< Filing           S-1 / S-3 / 424B etc., with extracted offering numbers
      ──< ReverseSplit     1-for-N splits
      ──< NameChange       former company names
      ──< CatalystEvent    earnings, FDA dates, lock-up expiries, shareholder meetings
      ──< MoverEntry       one row per stock per session per trading day
```

| Piece | File |
|---|---|
| Models | `app/src/Model/*.php` |
| Shared permissions | `app/src/Model/MarketDataPermissions.php` (trait) |
| CMS section | `app/src/Admin/MarketDataAdmin.php` → `/admin/market-data` |
| SEC import | `app/src/Tasks/ImportSecCompanyListTask.php` |

```bash
vendor/bin/sake db:build --flush
vendor/bin/sake tasks:import-sec-company-list   # ~7,700 NASDAQ/NYSE stocks, about 12 seconds
```

`SEC_USER_AGENT` must be set in `.env`: the SEC asks automated clients to identify themselves with contact details.

## 1. DataObject basics

```php
class Filing extends DataObject
{
    private static string $table_name = 'Filing';
    private static array $db = ['FormType' => 'Varchar(20)', 'FiledDate' => 'Date', /* ... */];
    private static array $has_one = ['Stock' => Stock::class];     // adds a StockID column
    private static array $indexes = ['AccessionNumber' => ['type' => 'unique']];
    private static string $default_sort = '"FiledDate" DESC';
}
```

Every DataObject table also has `ID`, `ClassName`, `Created` and `LastEdited`.

### Field types used

| Type | Used for | Note |
|---|---|---|
| `Varchar(n)` | tickers, names, URLs | |
| `Text` | longer plain text | |
| `Int` / `BigInt` | share counts, dollar amounts | `BigInt` because share counts and market caps exceed 2^31 |
| `Decimal(14,4)` | prices | penny stocks need 4 decimal places |
| `Date` / `Datetime` | filing dates, price timestamps | |
| `Boolean(1)` | flags | the argument is the default value |
| `Enum('a,b,c', 'b')` | fixed value sets | second argument is the default |

### Unknown vs zero

Silverstripe's numeric fields are `NOT NULL DEFAULT 0`, so an `Int` cannot say "unknown". Rather than store a
misleading `0`, each fact on `Stock` has an "as of" date (`Cash` + `CashAsOf`). An empty date means the value is
unknown. Methods like `getCashRunwayMonths()` return `null` when their inputs are unknown.

## 2. Relations

| Relation | Declared on | Database effect |
|---|---|---|
| `has_one` | `Filing`: `'Stock' => Stock::class` | `StockID` column on `Filing` |
| `has_many` | `Stock`: `'Filings' => Filing::class` | none; uses the `has_one` on the other side |
| `many_many` | (not used yet) | a join table |
| `belongs_to` / `belongs_many_many` | (not used yet) | reverse side, no columns |

Usage:

```php
$stock->Filings();                         // HasManyList, lazy: no query until iterated
$stock->Filings()->filter('FormType', '424B5')->count();
$filing->Stock()->Ticker;                   // has_one returns the object (an empty one if not set)
$filing->Stock()->exists();                 // how to check a has_one is set
```

`$cascade_deletes` on `Stock` deletes its filings, splits, events and movers when the stock is deleted.

## 3. Querying with the ORM

```php
Stock::get()->filter('Ticker:StartsWith', 'SND');          // SearchFilter modifiers
Stock::get()->filter(['Exchange' => 'NYSE', 'InScope' => true]);
Stock::get()->filter('MarketCap:LessThanOrEqual', 1_000_000_000);
Stock::get()->exclude('Exchange', 'NYSE');
Filing::get()->filter('Stock.Ticker', 'SNDL');             // filter through a relation (adds a JOIN)
Stock::get()->sort('MarketCap', 'DESC')->limit(10);
Stock::get()->column('Ticker');                            // array of one column
Stock::get()->map('Ticker', 'Name');                       // key => value map
Stock::get()->byID(5);  Stock::get()->find('Ticker', 'SNDL');
```

A `DataList` is lazy and immutable: each `filter()` returns a new list, and the query only runs when you iterate,
`count()`, `first()` and so on.

## 4. Indexes, and a CMS 6 detail

```php
private static array $indexes = [
    'Ticker' => ['type' => 'unique'],
    'CIK' => true,
];
```

`has_one` ID columns are indexed automatically. In CMS 6 the columns in `$default_sort` are indexed too
(`default_sort_index_mode`), including a composite index. `MoverEntry` sorts by `TradingDate, Session, Rank`, so
the composite index already covers "movers for this date and session" and no extra index is declared. Look for
`default_sort_composite` in the `db:build` output.

## 5. CMS configuration on the model

| Config | Effect in ModelAdmin |
|---|---|
| `$summary_fields` | Columns in the list. Can use relations (`Stock.Ticker`), methods (`MarketCapNice`) and field formatters (`InScope.Nice`). |
| `$searchable_fields` | Search form fields and their filters (`StartsWithFilter`, `ExactMatchFilter`, ...) |
| `$field_labels` | Human-friendly labels everywhere |
| `getCMSFields()` | Edit form. `Stock` moves scaffolded fields into Fundamentals / Ownership / Background tabs. |

`has_many` relations get a GridField tab on the edit form automatically (Filings, ReverseSplits, ...).

## 6. ModelAdmin

```php
class MarketDataAdmin extends ModelAdmin
{
    private static $url_segment = 'market-data';
    private static $menu_title = 'Market data';
    private static $managed_models = [Stock::class, Filing::class, CatalystEvent::class, MoverEntry::class];
}
```

Each managed model gets a tab with a searchable, paginated GridField plus add/edit/delete and CSV export and
import, with no extra code. GridField is the component behind these lists; it is built from components
(`GridFieldConfig`) that can be added or removed.

## 7. Permissions

DataObjects default to admin-only for view, edit, delete and create. `MarketDataPermissions` is a trait used by
every market data model:

- `canView()` returns `true`: the data is public.
- `canEdit()` / `canDelete()` / `canCreate()` require `CMS_ACCESS_App\Admin\MarketDataAdmin`, the permission
  code every ModelAdmin provides automatically. Assign it to a group under Security to let editors manage data.

Why a trait instead of a shared parent class? A common `MarketRecord extends DataObject` parent would become the
base table for every model (class table inheritance), putting stocks, filings and movers in one table.

## 8. The SEC import task

`ImportSecCompanyListTask` downloads `company_tickers_exchange.json` (about 10,000 companies) and keeps NASDAQ and
NYSE listings. Techniques worth noting:

- **Load once, then look up in memory**: one query loads all existing stocks into an array keyed by ticker, instead
  of one `SELECT` per row (the N+1 query problem).
- **One transaction**: `DB::get_conn()->withTransaction()` wraps ~7,700 writes. On SQLite this is the difference
  between seconds and minutes, since each write would otherwise be committed to disk separately.
- **Config**: the exchanges are `private static array $exchanges`, so they can be changed in YAML without editing
  the class. They are keyed (`'NYSE' => true`) rather than a plain list because **YAML config merges numeric
  arrays by appending**: setting `exchanges: [Nasdaq]` in YAML gives `['Nasdaq', 'NYSE', 'Nasdaq']`, so an entry
  can never be removed. With keys, YAML can switch one off with `NYSE: false`.
- **Environment variables**: `Environment::getEnv('SEC_USER_AGENT')` reads from `.env`; secrets and
  per-environment settings never go in code or YAML.
- **Idempotent**: running again updates existing stocks and marks ones that disappeared as no longer listed.

## 9. Try it yourself

1. In `/admin/market-data`, search stocks by ticker prefix, then open one and look at the tabs.
2. Add a reverse split with more new shares than old shares and check that validation blocks it.
3. Create a CMS user in a group that only has "Access to 'Market data' section" and check what they can do.
4. In a YAML file, set `App\Tasks\ImportSecCompanyListTask.exchanges.NYSE` to `false`, flush, and re-run the task.

## 10. Self-check Q&A

1. **Where is the foreign key for a `has_many`?**
   On the other side: `Stock` `has_many` `Filings` works because `Filing` `has_one` `Stock` (`StockID`).
2. **Why is a `DataList` described as lazy?**
   Building it with `filter()`/`sort()` runs no SQL; the query runs when results are needed.
3. **What is the N+1 query problem, and how does the import avoid it?**
   Running one query per item in a loop. The import loads all stocks once and looks them up in an array.
4. **How do you let non-admin CMS users edit a DataObject?**
   Override `canEdit()` (and friends) to check a permission code, then give that permission to a group.
5. **Why not use a shared base class for all models?**
   With class table inheritance, the base class becomes a shared table for all subclasses.
6. **You set a list config value in YAML, but the old values are still there. Why?**
   YAML merges numeric arrays by appending. Use keyed arrays for config that needs entries removed, or
   `Config::modify()->set()` in PHP to replace the value.
7. **What do you get for free from ModelAdmin?**
   A CMS section with list, search, pagination, add/edit/delete forms and CSV import/export per managed model.
