<%-- Usage: <% include MoversTable Movers=$Gainers, Caption="Top gainers" %> --%>
<table class="data-table movers-table">
    <caption>$Caption</caption>
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Ticker</th>
            <th scope="col">Company</th>
            <th scope="col" class="num">Price</th>
            <th scope="col" class="num">Change</th>
            <th scope="col" class="num">Volume</th>
        </tr>
    </thead>
    <tbody>
        <% loop $Movers %>
            <tr>
                <td>$Rank</td>
                <th scope="row"><a href="$Stock.Link">$Stock.Ticker</a></th>
                <td>$Stock.Name</td>
                <td class="num">$PriceNice</td>
                <td class="num <% if $ChangePercent > 0 %>up<% else %>down<% end_if %>">$ChangePercentNice</td>
                <td class="num">$Volume.Formatted</td>
            </tr>
        <% end_loop %>
    </tbody>
</table>
