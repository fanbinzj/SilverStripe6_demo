# Stage 1: Site Structure (Page Types, Templates, SiteConfig)

## What was built

```
/                                   HomePage          hero + ticker search + section cards
/movers                             MoversPage        tabs: market hours (default)
/movers/pre-market                                    pre-market
/movers/after-hours                                   after-hours
/dilution-tracker                   DilutionTrackerPage
/catalyst-calendar                  CatalystCalendarPage
/movers-archive                     MoversArchivePage
/penny-stock-101                    GuideHolder
/penny-stock-101/what-is-dilution   GuidePage (x4)
/stocks?q=...                       StockDirectoryPage  search results, and /stocks/TICKER in stage 3
/disclaimer                         Page (hidden from menus, linked from the footer)
```

| Piece | File |
|---|---|
| Page types | `app/src/Pages/*.php` |
| Trading session enum | `app/src/Model/MarketSession.php` |
| Movers tabs (routing) | `app/src/Pages/MoversPageController.php` |
| Search helpers for every page | `app/src/PageController.php` |
| Footer settings | `app/src/Extensions/SiteConfigExtension.php` + `app/_config/extensions.yml` |
| Site setup task | `app/src/Tasks/SetupSiteStructureTask.php` |
| Templates | `app/templates/` |
| Styles | `app/client/css/app.css` |

```bash
vendor/bin/sake db:build --flush
vendor/bin/sake tasks:setup-site-structure
```

Data sections show a "not available yet" placeholder until data is added in later stages.

## 1. Page types are classes

A page type is a subclass of `Page` (which extends `SiteTree`). Adding the class and running `db:build` is all
it takes for it to appear in the CMS "Add page" dialog.

```php
class GuidePage extends Page
{
    private static string $table_name = 'GuidePage';
    private static bool $can_be_root = false;     // must sit under a parent
    private static array $allowed_children = [];  // leaf page
    private static array $db = ['Summary' => 'Text'];
}
```

- **`$table_name`** is needed for namespaced classes, otherwise the table is named after the full class name
  (`App\Pages\GuidePage`).
- **Class table inheritance**: each class gets a table holding only its own fields, joined on `ID`. A class with no
  `$db` fields gets no table at all, which is why `db:build` created tables for `HomePage`, `MoversPage` and
  `GuidePage` but not for `DilutionTrackerPage`.
- **Versioned**: `SiteTree` is versioned, so each page table also has `_Live` and `_Versions` copies.
  Drafts live in the base table, published content in `_Live`.
- **Site tree rules**: `$allowed_children`, `$default_child` and `$can_be_root` control where pages can be created.
  In CMS 6 these are declared on the `Hierarchy` extension, which moved into the framework
  (`SilverStripe\ORM\Hierarchy\Hierarchy`).
- **`canCreate()`**: `StockDirectoryPage` overrides it so only one can exist. Permission methods (`canView`,
  `canEdit`, `canDelete`, `canCreate`) are the standard way to restrict records.

### Why stocks are not pages

A page per stock would put thousands of records in the CMS site tree, all versioned, with content that comes from
data imports rather than editors. Stocks will be plain `DataObject`s (stage 2), rendered by the controller of a
single `StockDirectoryPage` at `/stocks/TICKER` (stage 3). Pages are for editorial content; DataObjects are for data.

## 2. CMS fields

```php
public function getCMSFields()
{
    $this->beforeUpdateCMSFields(function (FieldList $fields) {
        $fields->addFieldToTab('Root.Main', NumericField::create('RowLimit', 'Rows per session'), 'Content');
    });
    return parent::getCMSFields();
}
```

`parent::getCMSFields()` ends by calling the `updateCMSFields` extension hook. `beforeUpdateCMSFields()` queues our
changes to run just before that hook, so extensions can still see and change the fields we add. The last argument
(`'Content'`) inserts the field before the Content editor.

## 3. Controllers, actions and URL handlers

Each page type can have a `{ClassName}Controller`; if there is none, the nearest ancestor's controller is used
(e.g. `GuidePage` uses `PageController`).

`MoversPageController` maps hyphenated URL segments onto one action:

```php
private static $allowed_actions = ['session'];
private static $url_handlers = ['$Session!' => 'session'];   // /movers/pre-market -> session()
```

- `$Session` is a URL parameter, read with `$this->getRequest()->param('Session')`; `!` means it is required,
  so `/movers` still goes to `index()`.
- Every action must be listed in `$allowed_actions`, otherwise it returns 404. This is a security feature: public
  controller methods are not callable from a URL by default.
- Unknown sessions return `$this->httpError(404)`. `/movers/market-hours` returns a 301 redirect to `/movers` so
  each view has one canonical URL.
- Returning an array from an action adds those values to the template scope (`$SessionTabs`, `$SessionTitle`).
- `ArrayList` and `ArrayData` build lists for templates from plain PHP data. In CMS 6 they live in
  `SilverStripe\Model\List\ArrayList` and `SilverStripe\Model\ArrayData` (`ViewableData` was renamed `ModelData`).

`MarketSession` is a PHP 8.1 backed enum. `MarketSession::tryFrom('pre-market')` turns the URL segment into a
case, or `null` for anything invalid.

Methods on `PageController` (`getSearchPage()`, `getSearchQuery()`) are available in every page's templates.

## 4. Templates

### Lookup and overriding the theme

- Templates are found by class name. Namespaced classes use matching folders:
  `App\Pages\MoversPage` → `templates/App/Pages/Layout/MoversPage.ss`.
- `Page.ss` is the HTML shell; its `$Layout` is replaced by the `Layout/` template of the most specific class.
- The search order is `SSViewer.themes` in `app/_config/theme.yml`: `'$public'` → `'/app'` → `'startup-theme'` →
  `'$default'`. Because `/app` comes before the theme, `app/templates/Includes/Header.ss` and `Footer.ss`
  **override** the theme's versions without editing the theme.

### Syntax used in this stage

```html
$Title                                   <!-- escaped by default -->
$Content                                 <!-- HTMLText fields output as HTML -->
$SiteConfig.DisclaimerPage.Link          <!-- follow a has_one relation -->
<% if $SiteConfig.DisclaimerPage.exists %>   <!-- has_one returns an empty object, not null -->
<% loop $Menu(1) %> ... <% end_loop %>   <!-- top-level pages with ShowInMenus -->
<% if not $isHomePage %>
<% include TickerSearchForm %>           <!-- templates/Includes/TickerSearchForm.ss, shares the current scope -->
<%-- template comment, not rendered --%>
```

Variables resolve to a method, a `get`-prefixed method or a field: `$SearchPage` calls `getSearchPage()`.

### Escaping

`$SearchQuery` echoes user input back into the search box. It returns a plain string, which templates cast to
`Text` and HTML-escape, so `/stocks?q=<script>` renders as `&lt;script&gt;`. Only fields cast as `HTMLText`
(like `Content`) are output raw.

### Accessibility

- The search form uses `role="search"`, a visible `<label>` tied to the input, and works without JavaScript.
- Session tabs are links in a `<nav>` with `aria-current="page"` on the active one (they are separate URLs, so
  links are more appropriate than an ARIA tab widget).
- The "data pending" placeholder uses `role="status"`.

### CSS via Requirements

`PageController::init()` calls `Requirements::css('app/client/css/app.css')`. `app/client` is listed under
`extra.expose` in `composer.json`; `composer vendor-expose` links it into `public/_resources/`, since `app/` is
outside the web root.

## 5. SiteConfig extension with a has_one

```yaml
# app/_config/extensions.yml
SilverStripe\SiteConfig\SiteConfig:
  extensions:
    - App\Extensions\SiteConfigExtension
```

The extension adds a `FooterNote` field and a `DisclaimerPage` `has_one` (a `DisclaimerPageID` column on
`SiteConfig`), edited with a `TreeDropdownField`. Editors can change the footer text and which page it links to
without a code change.

In CMS 6, `DataExtension` was removed: extensions extend `SilverStripe\Core\Extension`, and hook methods such as
`updateCMSFields()` are usually `protected`.

## 6. The setup task

`SetupSiteStructureTask` is a CMS 6 `BuildTask` (Symfony Console based) that is safe to run repeatedly: it looks
pages up by URL segment and only creates missing ones. ORM calls worth knowing:

- `$page->write()` saves the draft; `$page->publishRecursive()` publishes it along with anything in `$owns`.
- `$page->newClassInstance(HomePage::class)` changes a record's page type while keeping its ID.
- `$page->doArchive()` removes a page from draft and live but keeps its version history.
- `SiteTree::get_by_link(null)` returns the home page.

## 7. Try it yourself

1. In the CMS, try to add a second "Stock directory" page; it should not be offered.
2. Change the footer note and disclaimer page under Settings > Footer.
3. Create `app/templates/App/Pages/Layout/GuidePage.ss` to give guides their own layout; remember `?flush`.
4. Add a `PreMarketNote` field to `MoversPage` and show it only on the `/movers/pre-market` tab.

## 8. Self-check Q&A

1. **Why aren't stocks modelled as pages?**
   There are thousands, their content comes from imports, and the site tree plus versioning would be overhead
   with no benefit. DataObjects with a controller route are the better fit.
2. **What does `$url_handlers` do, and how does it relate to `$allowed_actions`?**
   It maps URL patterns to action names. The target action must still be in `$allowed_actions`.
3. **Why do some page types have no database table?**
   Tables are only created for classes that declare fields; the class name itself is stored in
   `SiteTree.ClassName`.
4. **How do you override a theme template without editing the theme?**
   Put a template with the same path somewhere earlier in `SSViewer.themes` (here, `/app`).
5. **Why use `beforeUpdateCMSFields()`?**
   So extensions' `updateCMSFields()` run after our fields exist and can modify them.
6. **Is `$SearchQuery` safe to print in a template?**
   Yes: plain strings are cast to `Text` and HTML-escaped. Returning a `DBHTMLText` or casting a field as
   `HTMLText` would print it raw.
