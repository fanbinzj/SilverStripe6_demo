<?php

namespace App\Pages;

use App\Model\MarketSession;
use App\Model\MoverEntry;
use Page;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBField;

/**
 * @property string $HeroTitle
 * @property string $HeroIntro
 */
class HomePage extends Page
{
    private static string $table_name = 'HomePage';

    private static string $class_description = 'The site home page, with ticker search';

    private static string $cms_icon_class = 'font-icon-p-home';

    private static array $db = [
        'HeroTitle' => 'Varchar(255)',
        'HeroIntro' => 'Text',
    ];

    // Gainers and losers shown on the home page
    private static int $movers_limit = 5;

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldsToTab('Root.Hero', [
                TextField::create('HeroTitle', 'Hero title'),
                TextareaField::create('HeroIntro', 'Hero introduction'),
            ]);
        });

        return parent::getCMSFields();
    }

    /**
     * Top market-hours gainers and losers for the latest trading day, or null if there are none yet.
     */
    public function getTodaysMovers(): ?ArrayData
    {
        $session = MarketSession::Regular;
        $tradingDate = MoverEntry::latestTradingDate($session);
        if (!$tradingDate) {
            return null;
        }

        $limit = static::config()->get('movers_limit');
        return ArrayData::create([
            'SessionTitle' => $session->label(),
            'TradingDate' => DBField::create_field('Date', $tradingDate),
            'Gainers' => MoverEntry::gainers($session, $tradingDate, $limit),
            'Losers' => MoverEntry::losers($session, $tradingDate, $limit),
            'MoversPage' => MoversPage::get()->first(),
        ]);
    }
}
