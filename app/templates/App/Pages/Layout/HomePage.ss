<div class="page__content home">
    <section class="hero">
        <h1 class="page__title"><% if $HeroTitle %>$HeroTitle<% else %>$Title<% end_if %></h1>
        <% if $HeroIntro %><p class="hero__intro">$HeroIntro</p><% end_if %>
        <% include TickerSearchForm %>
    </section>

    $Content

    <% with $TodaysMovers %>
        <section class="home-movers" aria-labelledby="home-movers-heading">
            <div class="home-movers__header">
                <h2 id="home-movers-heading">Today's movers</h2>
                <p class="movers__meta">$SessionTitle, {$TradingDate.Nice}. Prices may be delayed.</p>
            </div>
            <div class="movers__tables">
                <% include MoversTable Movers=$Gainers, Caption="Top gainers" %>
                <% include MoversTable Movers=$Losers, Caption="Top losers" %>
            </div>
            <% if $MoversPage %>
                <p><a href="$MoversPage.Link" class="home-movers__more">See the full movers list</a></p>
            <% end_if %>
        </section>
    <% end_with %>

    <section aria-labelledby="sections-heading">
        <h2 id="sections-heading">Explore</h2>
        <ul class="section-grid">
            <% loop $Menu(1) %>
                <% if not $isHomePage %>
                    <li class="section-card">
                        <h3 class="section-card__title"><a href="$Link">$MenuTitle</a></h3>
                        <% if $MetaDescription %><p>$MetaDescription</p><% end_if %>
                    </li>
                <% end_if %>
            <% end_loop %>
        </ul>
    </section>
</div>
