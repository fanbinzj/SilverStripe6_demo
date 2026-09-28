# Stage 0: Setup and Project Structure

## 1. Environment

| Component | Version / notes |
|---|---|
| PHP | 8.5 (Homebrew); Silverstripe CMS 6 requires `^8.3` |
| Composer | 2.x |
| Silverstripe | `silverstripe/installer` 6.2.0 (recipe-cms 6.2) |
| Database | MariaDB (Homebrew), accessed with the `MySQLDatabase` driver |
| Web server | PHP built-in server + `dev/router.php` |

> The project started on SQLite to keep setup light, and moved to MariaDB in stage 4 once background jobs and the
> website needed to write at the same time (see [docs/04](04-data-import.md#7-performance-lessons-from-this-stage)).
> No application code changed: only `.env`. The ORM makes the database transparent to application code through
> the `DB` abstraction layer and database adapter modules.
>
> Local MariaDB setup:
>
> ```bash
> brew install mariadb && brew services start mariadb
> mariadb -e "CREATE DATABASE pennymirror CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
>   CREATE USER 'pennymirror'@'localhost' IDENTIFIED BY '<password>';
>   GRANT ALL PRIVILEGES ON pennymirror.* TO 'pennymirror'@'localhost';
>   GRANT ALL PRIVILEGES ON \`ss_tmpdb%\`.* TO 'pennymirror'@'localhost';  -- temporary test databases
>   GRANT CREATE ON *.* TO 'pennymirror'@'localhost';"
> ```

## 2. Everyday commands

```bash
composer serve                      # start http://localhost:8080
vendor/bin/sake db:build --flush    # sync DB schema + flush caches (after changing $db or adding classes)
vendor/bin/sake flush               # flush caches only (after changing YAML config or templates)
vendor/bin/sake config:dump         # show the final merged config (great for debugging config issues)
vendor/bin/sake list                # list all commands and tasks
```

Browser equivalents: `/dev/build?flush=1`, or append `?flush` to any URL.

CMS: http://localhost:8080/admin, login `admin` / `password` (from `SS_DEFAULT_ADMIN_*` in `.env`, dev only).

## 3. Directory layout

```
app/                  Project code (the "project" module, set in app/_config/mysite.yml)
  _config/*.yml       YAML config, merged by the Config system
  src/                PHP classes (PSR-4 autoloaded; Page/PageController live in the global namespace by convention)
  templates/          Project .ss templates (added in later stages)
themes/startup-theme/ Default theme (installed via Composer, git-ignored)
public/               Web root: only index.php and public assets
  _resources/         Static assets "exposed" from modules by vendor-plugin (generated)
  assets/             Files uploaded through the CMS
vendor/               Dependencies (modules are just Composer packages)
.env                  Environment variables (DB, environment type, default admin), not committed
silverstripe-cache/   Or the system temp dir: manifest / config / template caches
```

## 4. Request lifecycle

1. `public/index.php` builds the request via `HTTPRequestBuilder::createFromEnvironment()`.
2. `CoreKernel` boots: loads `.env` and builds the **class, config and template manifests**
   (all cached; `?flush` clears them).
3. `HTTPApplication` runs the middleware chain.
4. `Director` matches the URL against `Director.rules`. CMS pages go through `ModelAsController`,
   which finds the `SiteTree` record by URLSegment and then its matching `XxxPageController`.
5. The controller's `handleRequest()` dispatches to an action according to `$url_handlers` / `$allowed_actions`.
6. Rendering: `Page.ss` (outer shell) + `Layout/Page.ss` (injected as `$Layout`), resolved through the
   theme stack in `SSViewer.themes`.

## 5. Three core mechanisms

- **Config API**: `private static $xxx` declares configuration, not a plain static property. The final value is
  the class default merged with YAML from all modules, ordered by `Before`/`After`.
  Read it with `static::config()->get('xxx')` or `Config::inst()->get(Foo::class, 'xxx')`.
- **Injector (dependency injection)**: use `Foo::create()` instead of `new Foo()` so the implementation can be
  swapped via YAML. Example: `app/_config/mimevalidator.yml` replaces `Upload_Validator` with `MimeUploadValidator`.
- **Extensions**: add fields, methods and hooks to existing classes without editing their source (covered in stage 4).

## 6. Notable changes from CMS 5 to CMS 6 (visible in this project)

- Minimum PHP is 8.3.
- `sake` was rewritten on Symfony Console: `dev/build` → `sake db:build`, tasks run as `sake tasks:Xxx`.
- **GraphQL is no longer a CMS dependency** (`silverstripe/graphql` is not in `vendor/`).
- The template engine was extracted into its own module, `silverstripe/template-engine`
  (the framework talks to it through an interface).
- TinyMCE was extracted into `silverstripe/htmleditor-tinymce`.

Full list: https://docs.silverstripe.org/en/6/changelogs/6.0.0/

## 7. Self-check Q&A

1. **What does `?flush` do, and when is it needed?**
   It clears the class, config and template manifests and related caches. Needed after adding classes, changing
   YAML or `private static` config, or adding templates. Not needed when only changing method bodies.
2. **What does `sake db:build` (formerly `dev/build`) do?**
   It scans every `DataObject` subclass, creates or alters tables and columns from `$db`, `$has_one`,
   `$many_many` etc. (additive only, never drops), then calls `requireDefaultRecords()` to seed default data.
3. **Why is config declared as `private static`?**
   `private` stops subclasses from reading it directly through PHP inheritance and forces access through the
   Config API, so YAML and Extensions can override it.
4. **`Foo::create()` vs `new Foo()`?**
   `create()` goes through the Injector, so the class can be replaced or have dependencies injected via config;
   `new` hard-codes the implementation.
5. **What values can `SS_ENVIRONMENT_TYPE` take?**
   `dev`, `test`, `live`. `dev` shows detailed errors and allows `/dev` without login; `live` hides error details.
