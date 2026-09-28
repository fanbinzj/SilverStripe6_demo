<div class="page__content">
    <h1 class="page__title">$Title</h1>
    $Content

    <% include FilterLinks Filters=$FormFilters, Label="Filing type" %>

    <% if $Filings %>
        <table class="data-table">
            <caption class="visually-hidden">Offering-related SEC filings, newest first</caption>
            <thead>
                <tr>
                    <th scope="col">Filed</th>
                    <th scope="col">Ticker</th>
                    <th scope="col">Company</th>
                    <th scope="col">Form</th>
                    <th scope="col"><span class="visually-hidden">Document</span></th>
                </tr>
            </thead>
            <tbody>
                <% loop $Filings %>
                    <tr>
                        <td>$FiledDate.Nice</td>
                        <th scope="row"><a href="$Stock.Link">$Stock.Ticker</a></th>
                        <td>$Stock.Name</td>
                        <td>$FormType</td>
                        <td><a href="$Url" rel="external noopener">View filing<span class="visually-hidden"> $FormType, $Stock.Ticker, $FiledDate.Nice</span></a></td>
                    </tr>
                <% end_loop %>
            </tbody>
        </table>
        <% include Pagination List=$Filings %>
        <p class="stock-section__note">Source: SEC EDGAR. Filings for common stocks with a market cap up to $1 billion.</p>
    <% else %>
        <% include DataPending %>
    <% end_if %>
</div>
