<div class="page__content">
    <h1 class="page__title">$Title</h1>
    $Content

    <% if $Guides %>
        <ul class="guide-list">
            <% loop $Guides %>
                <li class="guide-list__item">
                    <h2 class="guide-list__title"><a href="$Link">$Title</a></h2>
                    <% if $Summary %><p>$Summary</p><% end_if %>
                </li>
            <% end_loop %>
        </ul>
    <% end_if %>
</div>
