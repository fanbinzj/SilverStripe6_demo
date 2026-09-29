<div class="page__content movers">
    <p><a href="$Link">&larr; All trading days</a></p>
    <h1 class="page__title">Movers: {$TradingDate.Format('EEEE d MMMM y')}</h1>

    <nav class="tabs" aria-label="Trading session">
        <ul class="tabs__list">
            <% loop $SessionTabs %>
                <li><a class="tabs__link" href="$Link"<% if $IsCurrent %> aria-current="page"<% end_if %>>$Title</a></li>
            <% end_loop %>
        </ul>
    </nav>

    <h2>$SessionTitle</h2>
    <div class="movers__tables">
        <% include MoversTable Movers=$Gainers, Caption="Top gainers" %>
        <% include MoversTable Movers=$Losers, Caption="Top losers" %>
    </div>
    <p class="stock-section__note">Prices as recorded that day; they may have been delayed. Common stock with a market
        cap up to $1 billion at the time.</p>
</div>
