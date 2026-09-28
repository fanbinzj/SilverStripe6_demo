<?php

namespace {

    use App\Pages\StockDirectoryPage;
    use SilverStripe\CMS\Controllers\ContentController;
    use SilverStripe\View\Requirements;

    /**
     * @template T of Page
     * @extends ContentController<T>
     */
    class PageController extends ContentController
    {
        /**
         * An array of actions that can be accessed via a request. Each array element should be an action name, and the
         * permissions or conditions required to allow the user to access it.
         *
         * <code>
         * [
         *     'action', // anyone can access this action
         *     'action' => true, // same as above
         *     'action' => 'ADMIN', // you must have ADMIN permissions to access this action
         *     'action' => '->checkAction' // you can only access this action if $this->checkAction() returns true
         * ];
         * </code>
         *
         * @var array
         */
        private static $allowed_actions = [];

        protected function init()
        {
            parent::init();
            // You can include any CSS or JS required by your project here.
            // See: https://docs.silverstripe.org/en/developer_guides/templates/requirements/
            Requirements::css('app/client/css/app.css');
        }

        /**
         * Target page for the ticker search form (templates/Includes/TickerSearchForm.ss).
         */
        public function getSearchPage(): ?StockDirectoryPage
        {
            return StockDirectoryPage::get()->first();
        }

        /**
         * Current search term, so the search box keeps its value on the results page.
         */
        public function getSearchQuery(): string
        {
            return trim((string) $this->getRequest()->getVar('q'));
        }
    }
}
