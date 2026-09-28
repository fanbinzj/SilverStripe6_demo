<div class="page__content home">
    <section class="hero">
        <h1 class="page__title"><% if $HeroTitle %>$HeroTitle<% else %>$Title<% end_if %></h1>
        <% if $HeroIntro %><p class="hero__intro">$HeroIntro</p><% end_if %>
        <% include TickerSearchForm %>
    </section>

    $Content

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
