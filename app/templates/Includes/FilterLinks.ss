<%-- Usage: <% include FilterLinks Filters=$FormFilters, Label="Filing type" %> --%>
<nav class="filter-links" aria-label="$Label">
    <ul>
        <% loop $Filters %>
            <li><a href="$Link"<% if $IsCurrent %> aria-current="true"<% end_if %>>$Title</a></li>
        <% end_loop %>
    </ul>
</nav>
