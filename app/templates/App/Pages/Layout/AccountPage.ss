<div class="page__content">
    <h1 class="page__title">$Title</h1>
    <% with $CurrentMember %>
        <p>Logged in as <strong>$FirstName</strong> ($Email). <a href="$Top.LogoutURL">Log out</a></p>
    <% end_with %>

    <section aria-labelledby="watchlist-heading">
        <h2 id="watchlist-heading">Your watchlist</h2>
        <% if $Watchlist %>
            <table class="data-table">
                <caption class="visually-hidden">Stocks on your watchlist</caption>
                <thead>
                    <tr>
                        <th scope="col">Ticker</th>
                        <th scope="col">Company</th>
                        <th scope="col" class="num">Price</th>
                        <th scope="col" class="num">Market cap</th>
                        <th scope="col">Next date</th>
                        <th scope="col"><span class="visually-hidden">Remove</span></th>
                    </tr>
                </thead>
                <tbody>
                    <% loop $Watchlist %>
                        <tr>
                            <th scope="row"><a href="$Link">$Ticker</a></th>
                            <td>$Name</td>
                            <td class="num"><% if $LastPriceNice %>$LastPriceNice<% else %>&ndash;<% end_if %></td>
                            <td class="num"><% if $MarketCapNice %>$MarketCapNice<% else %>&ndash;<% end_if %></td>
                            <td>
                                <% if $UpcomingCatalysts %>
                                    <% with $UpcomingCatalysts.First %>$EventDate.Nice: $TypeLabel<% end_with %>
                                <% else %>
                                    &ndash;
                                <% end_if %>
                            </td>
                            <td>
                                <form method="post" action="watchlist/remove">
                                    <input type="hidden" name="StockID" value="$ID">
                                    <input type="hidden" name="SecurityID" value="$SecurityID">
                                    <button type="submit" class="button button--secondary">Remove<span class="visually-hidden"> $Ticker</span></button>
                                </form>
                            </td>
                        </tr>
                    <% end_loop %>
                </tbody>
            </table>
        <% else %>
            <p>Your watchlist is empty. Open any stock's profile and choose "Add to watchlist".</p>
        <% end_if %>
    </section>
</div>
