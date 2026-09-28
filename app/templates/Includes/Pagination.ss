<%-- Usage: <% include Pagination List=$Filings %> --%>
<% if $List.MoreThanOnePage %>
    <nav class="pagination" aria-label="Pagination">
        <% if $List.NotFirstPage %><a href="$List.PrevLink">Previous</a><% end_if %>
        <span>Page $List.CurrentPage of $List.TotalPages</span>
        <% if $List.NotLastPage %><a href="$List.NextLink">Next</a><% end_if %>
    </nav>
<% end_if %>
