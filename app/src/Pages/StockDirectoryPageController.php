<?php

namespace App\Pages;

use App\Model\DataIssueReport;
use App\Model\Stock;
use App\Search\StockSearch;
use PageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;

/**
 * /stocks?q=term          -> index(): search results (exact ticker match redirects to the profile)
 * /stocks/SNDL            -> stock(): stock profile
 * /stocks/ReportForm      -> "report a data issue" form submissions
 *
 * @extends PageController<StockDirectoryPage>
 */
class StockDirectoryPageController extends PageController
{
    private static $allowed_actions = [
        'stock',
        'ReportForm',
    ];

    // Order matters: the first matching rule wins, so specific rules go before the catch-all
    private static $url_handlers = [
        'ReportForm' => 'ReportForm',
        '$Ticker!' => 'stock',
    ];

    // Injected by the Injector when the controller is created (via setSearch()),
    // so the search implementation can be swapped in YAML or replaced in tests
    private static $dependencies = [
        'search' => '%$' . StockSearch::class,
    ];

    private StockSearch $search;

    /**
     * Stock being displayed, so ReportForm() can pre-fill it.
     */
    private ?Stock $stock = null;

    public function setSearch(StockSearch $search): static
    {
        $this->search = $search;
        return $this;
    }

    public function index(HTTPRequest $request): HTTPResponse|array
    {
        $query = $this->getSearchQuery();
        if ($query === '') {
            return [];
        }

        // Typing an exact ticker goes straight to its profile
        $exact = $this->search->findExactTicker($query);
        if ($exact) {
            return $this->redirect($exact->Link());
        }

        $results = $this->search->search($query);
        return [
            'Results' => $results,
            'ResultLimitReached' => $results->count() >= StockSearch::config()->get('max_results'),
        ];
    }

    public function stock(HTTPRequest $request): HTTPResponse|array
    {
        $ticker = (string) $request->param('Ticker');
        $stock = Stock::get()->find('Ticker', strtoupper($ticker));
        if (!$stock) {
            return $this->httpError(404, 'Stock not found');
        }

        // One canonical URL per stock: /stocks/sndl -> /stocks/SNDL
        if ($ticker !== $stock->Ticker) {
            return $this->redirect($stock->Link(), 301);
        }

        $this->stock = $stock;

        // $MetaTags reads from the page record rather than the template scope, so set it
        // on the in-memory record (never written to the database)
        $this->data()->MetaDescription = "Share structure, SEC filings and upcoming dates for {$stock->Name} ({$stock->Ticker}).";

        return [
            'Stock' => $stock,
            'Title' => $stock->getTitle(),
        ];
    }

    public function ReportForm(): Form
    {
        $fields = FieldList::create(
            HiddenField::create('StockID', null, $this->stock?->ID),
            TextareaField::create('Message', 'What looks wrong?')
                ->setRows(4)
                ->setDescription('For example, which number is incorrect and where you found the right one.'),
            EmailField::create('Email', 'Your email (optional)')
                ->setDescription('Only used if we need to ask about your report.')
        );

        $actions = FieldList::create(
            FormAction::create('doReport', 'Send report')
        );

        $form = Form::create($this, 'ReportForm', $fields, $actions, RequiredFieldsValidator::create(['Message']));
        $form->addExtraClass('report-form');

        return $form;
    }

    /**
     * Form action handler. Not listed in $allowed_actions: Form only allows the actions it contains.
     */
    public function doReport(array $data, Form $form): HTTPResponse
    {
        $stock = Stock::get()->byID((int) ($data['StockID'] ?? 0));
        if (!$stock) {
            return $this->httpError(400, 'Unknown stock');
        }

        $report = DataIssueReport::create();
        // saveInto() only copies fields that exist on the form, so a visitor
        // cannot set other fields (like Status) by adding them to the POST data
        $form->saveInto($report);
        $report->StockID = $stock->ID;
        $report->write();

        $form->sessionMessage('Thanks, your report has been sent.', 'good');
        return $this->redirect($stock->Link() . '#report');
    }
}
