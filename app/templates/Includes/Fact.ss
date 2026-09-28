<%-- Usage: <% include Fact Label="Cash", Value=$CashNice, AsOf=$CashAsOf %> --%>
<div class="fact">
    <dt class="fact__label">$Label</dt>
    <dd class="fact__value">
        <% if $AsOf %>
            $Value <span class="fact__asof">as of $AsOf.Nice</span>
        <% else %>
            <span class="fact__missing">Not available</span>
        <% end_if %>
    </dd>
</div>
