<%-- Plain GET form so it works without JavaScript; enhanced with autocomplete in stage 6 --%>
<% if $SearchPage %>
    <form class="ticker-search" action="$SearchPage.Link" method="get" role="search">
        <label for="ticker-search-input" class="ticker-search__label">Search by ticker or company name</label>
        <div class="ticker-search__row">
            <input id="ticker-search-input" class="ticker-search__input" type="search" name="q"
                   value="$SearchQuery" placeholder="e.g. ABCD" autocomplete="off" required>
            <button class="button ticker-search__button" type="submit">Search</button>
        </div>
    </form>
<% end_if %>
