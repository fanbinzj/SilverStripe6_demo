<%-- Template for the "stock" action: StockDirectoryPage_stock.ss is used before StockDirectoryPage.ss --%>
<% with $Stock %>
<article class="page__content stock-profile">
    <header class="stock-profile__header">
        <p class="stock-profile__exchange">$Exchange</p>
        <h1 class="page__title">$Ticker <span class="stock-profile__name">$Name</span></h1>
        <% if not $IsListed %>
            <p class="notice" role="note">This company no longer appears in the SEC's list of NASDAQ and NYSE listings.</p>
        <% end_if %>
        <p><a href="$SecFilingsUrl" rel="external noopener">All filings on SEC EDGAR</a></p>
    </header>

    <nav class="stock-profile__toc" aria-label="On this page">
        <ul>
            <li><a href="#fundamentals">Fundamentals</a></li>
            <li><a href="#dilution">Dilution history</a></li>
            <li><a href="#ownership">Ownership</a></li>
            <li><a href="#background">Company background</a></li>
            <li><a href="#dates">Upcoming dates</a></li>
        </ul>
    </nav>

    <section id="fundamentals" class="stock-section" aria-labelledby="fundamentals-heading">
        <h2 id="fundamentals-heading">Fundamentals snapshot</h2>
        <dl class="facts">
            <% include Fact Label="Price", Value=$LastPriceNice, AsOf=$PriceAsOf %>
            <% include Fact Label="Market cap", Value=$MarketCapNice, AsOf=$PriceAsOf %>
            <% include Fact Label="Shares outstanding", Value=$SharesOutstanding.Formatted, AsOf=$SharesOutstandingAsOf %>
            <% include Fact Label="Public float (USD)", Value=$PublicFloatNice, AsOf=$PublicFloatAsOf %>
            <% include Fact Label="Cash", Value=$CashNice, AsOf=$CashAsOf %>
            <% include Fact Label="Operating cash flow (latest quarter)", Value=$OperatingCashFlowNice, AsOf=$OperatingCashFlowAsOf %>
            <div class="fact">
                <dt class="fact__label">Cash runway</dt>
                <dd class="fact__value">
                    <% if $CashRunwayMonths %>
                        $CashRunwayMonths months
                    <% else %>
                        <span class="fact__missing">Not available</span>
                    <% end_if %>
                </dd>
            </div>
        </dl>
        <p class="stock-section__note">Cash runway = cash &divide; average monthly operating cash outflow in the latest
            quarter. A simple calculation from reported figures, not a forecast.</p>
    </section>

    <section id="dilution" class="stock-section" aria-labelledby="dilution-heading">
        <h2 id="dilution-heading">Dilution history</h2>

        <h3>Offering-related filings</h3>
        <% if $DilutionFilings %>
            <table class="data-table">
                <caption class="visually-hidden">S-1, S-3 and 424B filings for $Ticker</caption>
                <thead>
                    <tr>
                        <th scope="col">Filed</th>
                        <th scope="col">Form</th>
                        <th scope="col">Shares offered</th>
                        <th scope="col">Price</th>
                        <th scope="col"><span class="visually-hidden">Document</span></th>
                    </tr>
                </thead>
                <tbody>
                    <% loop $DilutionFilings %>
                        <tr>
                            <td>$FiledDate.Nice</td>
                            <td>$FormType</td>
                            <td><% if $ExtractedAt %>$SharesOffered.Formatted<% else %>&ndash;<% end_if %></td>
                            <td><% if $OfferingPriceNice %>$OfferingPriceNice<% else %>&ndash;<% end_if %></td>
                            <td><a href="$Url" rel="external noopener">View filing<span class="visually-hidden"> $FormType $FiledDate.Nice</span></a></td>
                        </tr>
                    <% end_loop %>
                </tbody>
            </table>
        <% else %>
            <p class="fact__missing">No offering-related filings recorded.</p>
        <% end_if %>

        <h3>Reverse splits</h3>
        <% if $ReverseSplits %>
            <ul>
                <% loop $ReverseSplits %>
                    <li>$Ratio, effective $EffectiveDate.Nice</li>
                <% end_loop %>
            </ul>
        <% else %>
            <p class="fact__missing">No reverse splits recorded.</p>
        <% end_if %>
    </section>

    <section id="ownership" class="stock-section" aria-labelledby="ownership-heading">
        <h2 id="ownership-heading">Ownership</h2>
        <dl class="facts">
            <% include Fact Label="Insiders", Value="{$InsiderPercent}%", AsOf=$OwnershipAsOf %>
            <% include Fact Label="Institutions", Value="{$InstitutionalPercent}%", AsOf=$OwnershipAsOf %>
        </dl>
        <h3>Upcoming lock-up expiries</h3>
        <% if $UpcomingLockupExpiries %>
            <ul>
                <% loop $UpcomingLockupExpiries %>
                    <li>$EventDate.Nice: $Title</li>
                <% end_loop %>
            </ul>
        <% else %>
            <p class="fact__missing">None recorded.</p>
        <% end_if %>
    </section>

    <section id="background" class="stock-section" aria-labelledby="background-heading">
        <h2 id="background-heading">Company background</h2>
        <dl class="facts">
            <div class="fact"><dt class="fact__label">Incorporated in</dt><dd class="fact__value"><% if $StateOfIncorporation %>$StateOfIncorporation<% else %><span class="fact__missing">Not available</span><% end_if %></dd></div>
            <div class="fact"><dt class="fact__label">Auditor</dt><dd class="fact__value"><% if $Auditor %>$Auditor<% else %><span class="fact__missing">Not available</span><% end_if %></dd></div>
            <div class="fact"><dt class="fact__label">Industry</dt><dd class="fact__value"><% if $Industry %>$Industry<% else %><span class="fact__missing">Not available</span><% end_if %></dd></div>
            <div class="fact"><dt class="fact__label">SEC CIK</dt><dd class="fact__value">$PaddedCIK</dd></div>
        </dl>
        <h3>Former names</h3>
        <% if $NameChanges %>
            <ul>
                <% loop $NameChanges %>
                    <li>$FormerName <span class="fact__asof">($UsedFrom.Year&ndash;$UsedTo.Year)</span></li>
                <% end_loop %>
            </ul>
        <% else %>
            <p class="fact__missing">No former names recorded.</p>
        <% end_if %>
    </section>

    <section id="dates" class="stock-section" aria-labelledby="dates-heading">
        <h2 id="dates-heading">Upcoming dates</h2>
        <% if $UpcomingCatalysts %>
            <ul>
                <% loop $UpcomingCatalysts %>
                    <li>
                        <strong>$EventDate.Nice</strong><% if not $IsDateConfirmed %> (unconfirmed)<% end_if %>:
                        $TypeLabel<% if $Title %>, $Title<% end_if %>
                    </li>
                <% end_loop %>
            </ul>
        <% else %>
            <p class="fact__missing">No upcoming dates recorded.</p>
        <% end_if %>
    </section>

    <p class="stock-profile__source">Company data: SEC EDGAR<% if $SecDataImportedAt %> (updated $SecDataImportedAt.Date)<% end_if %>.
        Prices: Nasdaq.com, may be delayed. Information only, not financial advice. Always check the original filings.</p>
</article>
<% end_with %>

<section id="report" class="page__content stock-section" aria-labelledby="report-heading">
    <h2 id="report-heading">Report a data issue</h2>
    $ReportForm
</section>
