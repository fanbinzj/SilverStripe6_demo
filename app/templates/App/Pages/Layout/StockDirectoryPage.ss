<div class="page__content">
    <h1 class="page__title">$Title</h1>
    $Content
    <% include TickerSearchForm %>

    <% if $SearchQuery %>
        <section class="search-results" aria-labelledby="results-heading">
            <h2 id="results-heading">Results for &ldquo;$SearchQuery&rdquo;</h2>
            <% if $Results %>
                <% if $ResultLimitReached %>
                    <p>Showing the first $Results.Count matches. Try a more specific search.</p>
                <% else %>
                    <p>$Results.Count <% if $Results.Count == 1 %>stock<% else %>stocks<% end_if %> found.</p>
                <% end_if %>
                <ul class="search-results__list">
                    <% loop $Results %>
                        <li class="search-results__item">
                            <a href="$Link" class="search-results__ticker">$Ticker</a>
                            <span class="search-results__name">$Name</span>
                            <span class="search-results__exchange">$Exchange</span>
                        </li>
                    <% end_loop %>
                </ul>
            <% else %>
                <p>No stocks match your search. Try a ticker (e.g. ABCD) or part of the company name.</p>
            <% end_if %>
        </section>
    <% end_if %>
</div>
