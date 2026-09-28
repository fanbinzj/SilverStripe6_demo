<div class="page__content">
    <h1 class="page__title">$Title</h1>
    $Content

    <% include FilterLinks Filters=$TypeFilters, Label="Event type" %>

    <% if $EventsByDate.GroupedBy('EventDate') %>
        <% loop $EventsByDate.GroupedBy('EventDate') %>
            <section class="calendar-day" aria-labelledby="day-$Pos">
                <h2 id="day-$Pos" class="calendar-day__date">$Children.First.EventDate.Format('EEEE d MMMM')</h2>
                <ul class="calendar-day__events">
                    <% loop $Children %>
                        <li>
                            <a href="$Stock.Link" class="calendar-day__ticker">$Stock.Ticker</a>
                            <span class="calendar-day__type">$TypeLabel</span>
                            $Title<% if not $IsDateConfirmed %> <span class="fact__asof">(unconfirmed date)</span><% end_if %>
                        </li>
                    <% end_loop %>
                </ul>
            </section>
        <% end_loop %>
        <p class="stock-section__note">Next $DaysAhead days, for common stocks with a market cap up to $1 billion.
            Earnings dates from the Nasdaq.com earnings calendar; dates can change.</p>
    <% else %>
        <p class="fact__missing">No events in the next $DaysAhead days.</p>
    <% end_if %>
</div>
