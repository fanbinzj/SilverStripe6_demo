<div class="page__content movers">
    <h1 class="page__title">$Title</h1>
    $Content

    <nav class="tabs" aria-label="Trading session">
        <ul class="tabs__list">
            <% loop $SessionTabs %>
                <li>
                    <a class="tabs__link" href="$Link"<% if $IsCurrent %> aria-current="page"<% end_if %>>$Title</a>
                </li>
            <% end_loop %>
        </ul>
    </nav>

    <h2>$SessionTitle <span class="movers__hours">($SessionHours)</span></h2>

    <% if not $HasDataSource %>
        <div class="data-pending" role="status">
            <p>$SessionTitle data is not available from our current data source ($SourceName).</p>
        </div>
    <% else_if $Gainers || $Losers %>
        <p class="movers__meta">
            Trading day {$TradingDate.Nice}. Updated {$UpdatedAt.Nice} ET. Source: $SourceName (may be delayed).
            Common stock with a market cap up to $1 billion and at least 10,000 shares traded.
        </p>
        <div class="movers__tables">
            <% include MoversTable Movers=$Gainers, Caption="Top gainers" %>
            <% include MoversTable Movers=$Losers, Caption="Top losers" %>
        </div>
    <% else %>
        <% include DataPending %>
    <% end_if %>
</div>
