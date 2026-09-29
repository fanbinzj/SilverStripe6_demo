<%-- Used inside <% with $Stock %>; $Top reaches the page controller for links outside the stock's scope --%>
<% if $CurrentMember %>
    <form class="watchlist-form" method="post" action="<% if $IsOnCurrentWatchlist %>watchlist/remove<% else %>watchlist/add<% end_if %>">
        <input type="hidden" name="StockID" value="$ID">
        <input type="hidden" name="SecurityID" value="$SecurityID">
        <input type="hidden" name="BackURL" value="$Link">
        <% if $IsOnCurrentWatchlist %>
            <button type="submit" class="button button--secondary">Remove from watchlist</button>
        <% else %>
            <button type="submit" class="button">Add to watchlist</button>
        <% end_if %>
    </form>
<% else %>
    <p class="watchlist-login">
        <a href="Security/login?BackURL={$Link.URLATT}">Log in</a>
        <% if $Top.RegistrationPage %>or <a href="$Top.RegistrationPage.Link">register</a><% end_if %>
        to add $Ticker to a watchlist.
    </p>
<% end_if %>
