<?php

namespace App\Tasks;

use App\Pages\CatalystCalendarPage;
use App\Pages\DilutionTrackerPage;
use App\Pages\GuideHolder;
use App\Pages\GuidePage;
use App\Pages\HomePage;
use App\Pages\MoversArchivePage;
use App\Pages\MoversPage;
use App\Pages\StockDirectoryPage;
use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\SiteConfig\SiteConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Creates the site's pages and settings. Safe to run more than once:
 * existing pages (matched by URL segment) are left alone.
 *
 * CLI:     vendor/bin/sake tasks:setup-site-structure
 * Browser: /dev/tasks/setup-site-structure
 */
class SetupSiteStructureTask extends BuildTask
{
    protected static string $commandName = 'setup-site-structure';

    protected string $title = 'Set up site structure';

    protected static string $description = 'Creates the PennyMirror pages, guides and site settings.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->setUpHomePage($output);
        $this->removeDefaultPages($output);

        $sort = 1;
        $this->page(MoversPage::class, 'movers', 'Today\'s Movers', ++$sort, $output, [
            'MetaDescription' => 'Biggest price moves today: pre-market, market hours and after-hours.',
            'Content' => '<p>The largest percentage price moves among US exchange-listed stocks with a market '
                . 'capitalisation under $1 billion, by trading session.</p>',
        ]);
        $this->page(DilutionTrackerPage::class, 'dilution-tracker', 'Dilution Tracker', ++$sort, $output, [
            'MetaDescription' => 'New S-1, S-3 and 424B filings with the key numbers.',
            'Content' => '<p>Recent S-1, S-3 and 424B filings, with the number of shares and offering price '
                . 'taken from each filing.</p>',
        ]);
        $this->page(CatalystCalendarPage::class, 'catalyst-calendar', 'Catalyst Calendar', ++$sort, $output, [
            'MetaDescription' => 'Earnings, FDA dates, lock-up expiries and shareholder meetings.',
            'Content' => '<p>Scheduled earnings releases, FDA dates, lock-up expiries and shareholder meetings.</p>',
        ]);
        $this->page(MoversArchivePage::class, 'movers-archive', 'Movers Archive', ++$sort, $output, [
            'MetaDescription' => 'Movers lists from previous trading days.',
            'Content' => '<p>Previous days\' movers lists.</p>',
        ]);

        $guides = $this->page(GuideHolder::class, 'penny-stock-101', 'Penny Stock 101', ++$sort, $output, [
            'MetaDescription' => 'Short guides to dilution, reverse splits, 424B filings and more.',
            'Content' => '<p>Short guides to terms and documents that come up often with small-cap stocks.</p>',
        ]);
        $this->setUpGuides($guides, $output);

        $this->page(StockDirectoryPage::class, 'stocks', 'Stocks', ++$sort, $output, [
            'ShowInMenus' => false,
        ]);
        $disclaimer = $this->page(Page::class, 'disclaimer', 'Disclaimer', ++$sort, $output, [
            'ShowInMenus' => false,
            'Content' => $this->disclaimerContent(),
        ]);

        $this->setUpSiteConfig($disclaimer, $output);
        $this->moveErrorPagesToEnd(++$sort, $output);

        return Command::SUCCESS;
    }

    /**
     * Find a page by URL segment under a parent, or create and publish it.
     *
     * @template T of SiteTree
     * @param class-string<T> $class
     * @return T
     */
    private function page(
        string $class,
        string $urlSegment,
        string $title,
        int $sort,
        PolyOutput $output,
        array $fields = [],
        int $parentID = 0
    ): SiteTree {
        $page = SiteTree::get()->filter(['URLSegment' => $urlSegment, 'ParentID' => $parentID])->first();
        if ($page) {
            return $page;
        }

        $page = $class::create(array_merge([
            'Title' => $title,
            'URLSegment' => $urlSegment,
            'ParentID' => $parentID,
            'Sort' => $sort,
        ], $fields));
        $page->write();
        $page->publishRecursive();
        $output->writeln("Created page: {$title} (/{$urlSegment})");

        return $page;
    }

    private function setUpHomePage(PolyOutput $output): void
    {
        $home = SiteTree::get_by_link(null) ?? Page::create(['Title' => 'Home', 'URLSegment' => 'home']);
        if ($home instanceof HomePage) {
            return;
        }

        // Change the page type of the existing home page, keeping its ID and URL
        $home = $home->newClassInstance(HomePage::class);
        $home->HeroTitle = 'Small-cap stocks, just the facts';
        $home->HeroIntro = 'Look up a US-listed stock to see its share structure, filings and upcoming dates, '
            . 'all taken from public sources.';
        $home->Content = '';
        $home->write();
        $home->publishRecursive();
        $output->writeln('Set up home page');
    }

    /**
     * Archive the "About Us" and "Contact Us" pages created by a fresh install.
     */
    private function removeDefaultPages(PolyOutput $output): void
    {
        $defaults = SiteTree::get()->filter([
            'ClassName' => Page::class,
            'URLSegment' => ['about-us', 'contact-us'],
            'ParentID' => 0,
        ]);
        foreach ($defaults as $page) {
            $page->doArchive();
            $output->writeln("Archived default page: {$page->Title}");
        }
    }

    /**
     * Error pages are created on install with low sort values; keep them below our pages in the site tree.
     */
    private function moveErrorPagesToEnd(int $sort, PolyOutput $output): void
    {
        foreach (ErrorPage::get()->sort('ErrorCode') as $page) {
            if ($page->Sort >= $sort) {
                continue;
            }
            $page->Sort = $sort++;
            $page->write();
            $page->publishSingle();
            $output->writeln("Moved error page to the end: {$page->Title}");
        }
    }

    private function setUpGuides(GuideHolder $holder, PolyOutput $output): void
    {
        foreach ($this->guides() as $i => [$urlSegment, $title, $summary, $content]) {
            $this->page(GuidePage::class, $urlSegment, $title, $i + 1, $output, [
                'Summary' => $summary,
                'Content' => $content,
            ], $holder->ID);
        }
    }

    private function setUpSiteConfig(SiteTree $disclaimer, PolyOutput $output): void
    {
        $config = SiteConfig::current_site_config();
        $config->Title = 'PennyMirror';
        $config->Tagline = 'Small-cap stocks, just the facts';
        $config->FooterNote = 'Information only, from public sources. Not financial advice.';
        $config->DisclaimerPageID = $disclaimer->ID;
        $config->write();
        $output->writeln('Updated site settings');
    }

    private function disclaimerContent(): string
    {
        return <<<HTML
<p>PennyMirror provides information for general educational purposes only. It is not financial, investment,
legal or tax advice, and it is not a recommendation to buy, sell or hold any security.</p>
<p>Data is collected from public sources such as SEC EDGAR. It may be delayed, incomplete or inaccurate.
Always check the original filings before relying on any information shown here.</p>
<p>Trading in small-cap stocks carries a high risk of loss. Consider getting advice from a licensed
professional before making investment decisions.</p>
HTML;
    }

    /**
     * @return array<array{string, string, string, string}> [url segment, title, summary, content]
     */
    private function guides(): array
    {
        return [
            [
                'what-is-dilution',
                'What is dilution?',
                'When a company issues new shares, each existing share represents a smaller part of the company.',
                <<<HTML
<p>Dilution happens when a company issues new shares. The company is split into more pieces, so each existing
share represents a smaller percentage of it.</p>
<h2>Common sources of new shares</h2>
<ul>
<li><strong>Public offerings</strong>: shares sold to investors, often at a discount to the market price.</li>
<li><strong>At-the-market (ATM) programs</strong>: shares sold gradually into the market over time.</li>
<li><strong>Warrants and convertible notes</strong>: rights that can later turn into new shares.</li>
<li><strong>Employee equity</strong>: stock options and share awards.</li>
</ul>
<h2>Where to find it</h2>
<p>Registration statements (S-1, S-3), prospectus supplements (424B) and the share count on the cover of
quarterly (10-Q) and annual (10-K) reports.</p>
HTML,
            ],
            [
                'what-is-a-reverse-stock-split',
                'What is a reverse stock split?',
                'A reverse split combines existing shares into fewer shares, raising the price per share.',
                <<<HTML
<p>A reverse stock split combines existing shares into a smaller number of shares. In a 1-for-10 reverse
split, every 10 shares become 1 share and the price per share is multiplied by 10. At the moment of the split,
the company's total market value does not change.</p>
<h2>Why companies do it</h2>
<p>A common reason is to meet an exchange's minimum bid price requirement. For example, Nasdaq generally
requires a minimum bid price of $1 per share for continued listing.</p>
<h2>Where to find it</h2>
<p>Depending on where the company is incorporated and its charter, a reverse split may need shareholder
approval. The proposal usually appears in a proxy statement (DEF 14A), and the effective date is usually
announced in an 8-K.</p>
HTML,
            ],
            [
                'how-to-read-a-424b',
                'How to read a 424B prospectus',
                'A 424B prospectus describes the securities being offered, the price and the costs of the offering.',
                <<<HTML
<p>A 424B is a prospectus filed with the SEC when securities are offered under a registration statement.
There are several variants (424B1 to 424B8). A 424B5, for example, is commonly used for an offering made
under an existing shelf registration (S-3).</p>
<h2>Sections to look at</h2>
<ul>
<li><strong>Cover page</strong>: the number of shares or units offered, the price and the underwriter or placement agent.</li>
<li><strong>The Offering</strong>: shares outstanding before and after the offering.</li>
<li><strong>Use of Proceeds</strong>: what the company says it will use the money for.</li>
<li><strong>Dilution</strong>: the effect on net tangible book value per share.</li>
<li><strong>Plan of Distribution / Underwriting</strong>: fees, discounts and any warrants issued to the agent.</li>
</ul>
HTML,
            ],
            [
                'pump-and-dump-patterns',
                'Pump-and-dump patterns',
                'Pump-and-dump schemes promote a stock to push its price up, then sell into the rise.',
                <<<HTML
<p>In a pump-and-dump scheme, promoters push up a stock's price with misleading or exaggerated claims, then
sell their own shares to people buying in. When the promotion stops, the price often falls sharply.</p>
<h2>Common warning signs</h2>
<ul>
<li>Unsolicited tips through social media, messaging apps or email.</li>
<li>Pressure to buy quickly before the price "takes off".</li>
<li>Large price and volume increases without matching news from the company.</li>
<li>Thinly traded stocks with limited public information.</li>
</ul>
<p>More information: <a href="https://www.investor.gov/introduction-investing/investing-basics/glossary/pump-and-dump-schemes">Pump and dump schemes (Investor.gov)</a>.</p>
HTML,
            ],
        ];
    }
}
