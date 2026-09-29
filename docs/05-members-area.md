# Stage 5: Members Area (Permissions, Registration, Extensions, many_many)

## What was built

```
/movers-archive                       trading days with movers lists
/movers-archive/2026-09-25            that day's market-hours movers (+ /pre-market, /after-hours)
/register                             sign-up form; new members join the "Members" group and are logged in
/account                              the member's watchlist (logged-in only)
POST /watchlist/add, /watchlist/remove   change the watchlist (not a page; routed in YAML)
```

| Piece | File |
|---|---|
| Permission code + Members group | `app/src/Security/Membership.php` |
| Group created on db:build | `app/src/Extensions/GroupExtension.php` |
| Watchlist on Member (`many_many`) | `app/src/Extensions/MemberExtension.php` |
| Archive with "Members only" switch | `app/src/Pages/MoversArchivePage*.php` |
| Registration | `app/src/Pages/RegistrationPage*.php` |
| Account page | `app/src/Pages/AccountPage*.php` |
| Watchlist actions | `app/src/Controllers/WatchlistController.php` + `app/_config/routes.yml` |

The archive is free by default. Ticking **Members only** on the Movers Archive page in the CMS restricts it to
members (and anyone else with the permission) without a code change.

## 1. Members, groups and permissions

- A `Member` is any account, CMS user or website member alike. What they can do comes from the **groups** they
  belong to, and each group has **permission codes**.
- `Membership` implements `PermissionProvider`, which registers `VIEW_MOVERS_ARCHIVE` so it appears under
  Security > Groups > Permissions ("PennyMirror" category) and can be given to any group.
- The "Members" group (code `members`) is created on `db:build` by `GroupExtension::onRequireDefaultRecords()`, an
  extension hook every DataObject runs while building default records.
- `Permission::check('VIEW_MOVERS_ARCHIVE')` checks the current member. Administrators pass every check.

### Page visibility vs data visibility

`SiteTree::canView()` controls whether a page exists for a visitor (menus, search, 404). The archive page stays
visible so visitors can see what membership offers; `MoversArchivePage::canViewArchive()` controls access to its
data instead.

### Denying access

```php
protected function init()
{
    parent::init();
    if (!$this->data()->canViewArchive()) {
        Security::permissionFailure($this, 'The movers archive is available to members...');
    }
}
```

- Checking in `init()` covers every action of the controller, including `day()`.
- `Security::permissionFailure()` sends logged-out visitors to the login form with a `BackURL`, so they return to
  the page after logging in; logged-in visitors without permission get an error instead.

## 2. Registration

Silverstripe has login, logout and password reset (`/Security/...`) but no public sign-up, so
`RegistrationPageController` provides one:

- **Explicit field assignment** instead of `saveInto()`: the form contains a field that isn't on `Member`
  (the disclaimer checkbox), and nothing but first name, email and password should be settable.
- **Email** is trimmed and lower-cased, and checked for an existing account first.
- **Password strength**: `Member::validate()` runs the configured `PasswordValidator`. CMS 6 uses
  `EntropyPasswordValidator` by default, which scores how guessable a password is rather than applying
  "one digit, one symbol" rules. A weak password makes `write()` throw a `ValidationException`, which is passed back
  to the form with `$form->setSessionValidationResult()`.
- **Never store the password in the session**: on failure only first name and email are kept for re-display.
- **Logging in** goes through `IdentityStore::logIn()`, which handles the session properly (including issuing a new
  session ID, preventing session fixation), rather than setting session values by hand.
- **Form labels are HTML**: `FormField::setTitle()` expects escaped HTML, so the dynamic disclaimer link in the
  checkbox label is escaped with `Convert::raw2att()`.

Known gaps for a production site: no email verification, no CAPTCHA or rate limiting on sign-ups, and the
"account already exists" message reveals whether an email is registered.

## 3. Extending Member

```yaml
SilverStripe\Security\Member:
  extensions:
    - App\Extensions\MemberExtension
```

```php
class MemberExtension extends Extension
{
    private static array $many_many = ['Watchlist' => Stock::class];
    private static array $many_many_extraFields = ['Watchlist' => ['AddedAt' => 'Datetime']];
    private static int $watchlist_limit = 100;

    public function addToWatchlist(Stock $stock): bool { ... }
}
```

- Relations, fields and methods declared on an extension behave as if they were on `Member`:
  `$member->Watchlist()`, `$member->addToWatchlist($stock)`.
- **Config on an extension belongs to the owner.** `watchlist_limit` is read with
  `$member->config()->get('watchlist_limit')`. In CMS 6 an `Extension` has no `config()` of its own; calling
  `MemberExtension::config()` was a fatal error found while testing.
- Core classes are never edited; everything project-specific lives in the extension.

## 4. many_many

| Declaration | Where | Effect |
|---|---|---|
| `many_many` `'Watchlist' => Stock::class` | Member (via extension) | Creates the join table `Member_Watchlist` (MemberID, StockID) |
| `many_many_extraFields` | same | Adds `AddedAt` to the join table |
| `belongs_many_many` `'Watchers' => Member::class . '.Watchlist'` | Stock | The other side of the same relation; no table |

Usage:

```php
$member->Watchlist()->add($stock, ['AddedAt' => $now]);   // extra fields as the second argument
$member->Watchlist()->remove($stock);                      // deletes the join row only
$member->Watchlist()->sort('AddedAt', 'DESC');             // extra fields can be sorted and filtered on
$stock->Watchers()->count();
```

**Deleting records doesn't clean up join rows.** Deleting a member left a row behind in `Member_Watchlist`.
`$cascade_deletes` is the wrong tool (it would delete the stocks themselves), so both sides clear their join rows in
`onBeforeDelete()`: `MemberExtension` removes the member's watchlist, `Stock` removes itself from watchlists.

## 5. A controller that isn't a page

```yaml
# app/_config/routes.yml
SilverStripe\Control\Director:
  rules:
    'watchlist//$Action': 'App\Controllers\WatchlistController'
```

`Director.rules` maps URL patterns to controllers directly, without a CMS page. The `//` splits the fixed prefix
from the parameters passed on to the controller. Useful for actions and endpoints that have no content of their own.

`WatchlistController` changes data, so each action:

1. accepts **POST only** (a GET returns 405), so links, prefetching or crawlers can't change anything;
2. checks the **CSRF token** with `SecurityToken::inst()->checkRequest()`; the templates add `$SecurityID` as a
   hidden field;
3. requires a **logged-in member**;
4. re-loads the stock by ID rather than trusting anything else from the request;
5. returns with `redirectBack()`, which only follows a `BackURL` if it points to this site (an off-site
   `BackURL` is ignored).

The logout link is built with `Security::logout_url()`, which adds a CSRF token so another site can't log members
out with a plain link.

## 6. Archive routing and SQLSelect

- `/movers-archive/$Date!/$Session` routes to `day()`. The date is checked with a regular expression **and**
  `checkdate()` (so `2026-02-30` is a 404), the session with `MarketSession::tryFrom()`, and a day with no data is
  also a 404.
- The day list needs "which sessions exist for each date", i.e. distinct (date, session) pairs. The ORM has no
  helper for that, so the controller uses `SQLSelect` with `setDistinct(true)` and parameterised values
  (`DB::placeholders()`), still avoiding hand-written values in SQL.
- The movers list queries are shared with the home and movers pages through `MoverEntry::gainers()/losers()`.

## 7. Templates

- `$CurrentMember` and `$SecurityID` are template globals available everywhere.
- Inside `<% with $Stock %>`, `$Top` reaches the page controller (`$Top.RegistrationPage`).
- `<% with $List.First %>` still renders its content when the list is empty (with empty values); wrap it in
  `<% if $List %>`.
- `{$Link.URLATT}` URL-encodes a value for use inside a query string (`BackURL=...`).

## 8. Try it yourself

1. Register an account with a weak password, then a strong one, and look at the new member under Security.
2. Tick "Members only" on the Movers Archive page, publish, and visit the archive logged out, then logged in.
3. Give the `VIEW_MOVERS_ARCHIVE` permission to a different group in the CMS and test with a user in it.
4. Lower `SilverStripe\Security\Member.watchlist_limit` to 2 in YAML and try adding a third stock.

## 9. Self-check Q&A

1. **How do you add your own permission and check it?**
   Implement `PermissionProvider::providePermissions()`, assign the code to a group, and use `Permission::check()`.
2. **What does `Security::permissionFailure()` do?**
   Redirects logged-out users to login (with a `BackURL`) and shows logged-in users an access-denied response.
3. **Where do you put config declared on an extension, and how do you read it?**
   It is merged into the owner class's config: `Member::config()->get(...)` or `$member->config()->get(...)`.
4. **What's the difference between `many_many` and `belongs_many_many`?**
   `many_many` owns the join table; `belongs_many_many` is the other side and creates nothing.
5. **Why not `$cascade_deletes` for the watchlist?**
   It deletes the related objects, not just the link; the stocks must survive a member being deleted.
6. **Why must actions that change data be POST with a CSRF token?**
   GET requests can be triggered by links, images or prefetching; the token proves the request came from our form.
7. **How do you route a URL to a controller that isn't a CMS page?**
   Add a rule to `SilverStripe\Control\Director.rules` in YAML.
