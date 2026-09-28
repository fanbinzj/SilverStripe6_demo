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

    <% if $Movers %>
        <%-- Table added in stage 4 --%>
    <% else %>
        <% include DataPending %>
    <% end_if %>
</div>
