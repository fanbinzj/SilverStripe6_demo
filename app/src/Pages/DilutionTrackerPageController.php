<?php

namespace App\Pages;

use App\Model\Filing;
use PageController;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\PaginatedList;

/**
 * /dilution-tracker              all offering-related filings, newest first
 * /dilution-tracker?form=424B    only 424B prospectuses (see FORM_GROUPS)
 *
 * @extends PageController<DilutionTrackerPage>
 */
class DilutionTrackerPageController extends PageController
{
    /**
     * Filter options: URL value => [label, form types]
     */
    private const array FORM_GROUPS = [
        'S-1' => ['S-1 / F-1 registrations', ['S-1', 'S-1/A', 'F-1', 'F-1/A']],
        'S-3' => ['S-3 / F-3 shelf registrations', ['S-3', 'S-3/A', 'F-3', 'F-3/A']],
        '424B' => ['424B prospectuses', ['424B1', '424B2', '424B3', '424B4', '424B5', '424B7']],
    ];

    public function index()
    {
        $group = $this->currentGroup();
        $forms = $group ? self::FORM_GROUPS[$group][1] : Filing::DILUTION_FORMS;

        $filings = Filing::get()
            ->filter(['FormType' => $forms, 'Stock.InScope' => true])
            ->sort(['FiledDate' => 'DESC', 'ID' => 'DESC'])
            ->eagerLoad('Stock');

        return [
            'Filings' => PaginatedList::create($filings, $this->getRequest())->setPageLength(25),
            'FormFilters' => $this->formFilters($group),
        ];
    }

    /**
     * The ?form= value if it's one we know, otherwise null. Never pass raw input to a query.
     */
    private function currentGroup(): ?string
    {
        $group = (string) $this->getRequest()->getVar('form');
        return array_key_exists($group, self::FORM_GROUPS) ? $group : null;
    }

    private function formFilters(?string $current): ArrayList
    {
        $filters = ArrayList::create([
            ArrayData::create(['Title' => 'All', 'Link' => $this->Link(), 'IsCurrent' => $current === null]),
        ]);
        foreach (self::FORM_GROUPS as $key => [$label]) {
            $filters->push(ArrayData::create([
                'Title' => $label,
                'Link' => $this->Link() . '?form=' . rawurlencode($key),
                'IsCurrent' => $current === $key,
            ]));
        }
        return $filters;
    }
}
