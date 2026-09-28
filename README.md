# PennyMirror

Fact-based research pages for small-cap US stocks, built on **Silverstripe CMS 6**.

This is an early prototype. It shows publicly available information only (no ratings, opinions or advice).

## Scope

- US exchange-listed stocks (NASDAQ, NYSE, NYSE American); OTC is out of scope
- Market capitalisation up to USD 1 billion
- English only

## Planned sections

| Section | Description |
|---|---|
| Today's Movers | Biggest price moves, split into pre-market, market hours and after-hours |
| Movers Archive | Historical movers lists (may become members-only) |
| Stock Profile | One page per stock: fundamentals snapshot, dilution history, ownership, company background, key metrics |
| Dilution Tracker | Feed of new S-1, S-3 and 424B filings with the key numbers extracted |
| Catalyst Calendar | Earnings dates, FDA dates, lock-up expiries, shareholder meetings |
| Penny Stock 101 | Short guides: dilution, reverse splits, reading a 424B, pump-and-dump patterns |
| Ticker search | Search stocks by ticker or company name from the home page |

## Data sources (prototype)

- [SEC EDGAR](https://www.sec.gov/search-filings/edgar-application-programming-interfaces): company list, filings, XBRL financial data
- Market prices: a free public source behind a swappable provider interface

## Quick start

Requirements: PHP 8.3+, Composer 2 (SQLite is used for local development).

```bash
composer install
cp .env.example .env
vendor/bin/sake db:build --flush
composer serve        # http://localhost:8080, CMS at /admin
```

## Roadmap

Each stage focuses on one area of the framework and has accompanying notes in [`docs/`](docs/).

| Stage | Topic | Notes |
|---|---|---|
| 0 | Setup, project structure, request lifecycle | [docs/00](docs/00-setup-and-structure.md) |
| 1 | Site structure: page types, templates, navigation, SiteConfig | TODO |
| 2 | Data model: DataObjects, relations, ModelAdmin | TODO |
| 3 | Stock profiles and search: routing, controllers, forms | TODO |
| 4 | Data import: SEC EDGAR, Injector-based providers, build tasks, queued jobs | TODO |
| 5 | Members area: permissions and Extensions | TODO |
| 6 | Front-end: Vue 3 ticker search, accessibility | TODO |
| 7 | Testing and code quality | TODO |
| 8 | Performance and debugging: caching, query optimisation | TODO |
