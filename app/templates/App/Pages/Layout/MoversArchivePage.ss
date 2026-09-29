<div class="page__content">
    <h1 class="page__title">$Title</h1>
    $Content

    <% if $Days %>
        <table class="data-table archive-table">
            <caption class="visually-hidden">Trading days with movers lists</caption>
            <thead>
                <tr>
                    <th scope="col">Trading day</th>
                    <th scope="col">Sessions</th>
                </tr>
            </thead>
            <tbody>
                <% loop $Days %>
                    <tr>
                        <th scope="row">$Date.Format('EEE d MMM y')</th>
                        <td>
                            <% loop $Sessions %>
                                <a href="$Link">$Title<span class="visually-hidden">, $Up.Date.Nice</span></a><% if not $IsLast %> &middot; <% end_if %>
                            <% end_loop %>
                        </td>
                    </tr>
                <% end_loop %>
            </tbody>
        </table>
        <% include Pagination List=$Pagination %>
    <% else %>
        <% include DataPending %>
    <% end_if %>
</div>
