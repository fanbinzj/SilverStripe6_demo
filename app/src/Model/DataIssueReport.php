<?php

namespace App\Model;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\DataObject;

/**
 * A visitor's report that something on a stock profile looks wrong.
 * Created from the form on the profile page; reviewed in Market data admin.
 *
 * @property string $Message
 * @property string $Email
 * @property string $Status
 * @property int $StockID
 * @method Stock Stock()
 */
class DataIssueReport extends DataObject
{
    use MarketDataPermissions;

    private static string $table_name = 'DataIssueReport';

    private static string $singular_name = 'Data issue report';

    private static array $db = [
        'Message' => 'Text',
        'Email' => 'Varchar(255)',
        'Status' => "Enum('New,Resolved,Rejected', 'New')",
    ];

    private static array $has_one = [
        'Stock' => Stock::class,
    ];

    private static string $default_sort = '"Created" DESC';

    private static array $summary_fields = [
        'Created' => 'Received',
        'Stock.Ticker' => 'Ticker',
        'Status',
        'Message.Summary' => 'Message',
    ];

    private static array $searchable_fields = [
        'Status',
        'Stock.Ticker' => [
            'title' => 'Ticker',
            'filter' => 'ExactMatchFilter',
        ],
    ];

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if (!trim((string) $this->Message)) {
            $result->addFieldError('Message', 'Please describe the issue');
        } elseif (mb_strlen($this->Message) > 2000) {
            $result->addFieldError('Message', 'Please keep the message under 2,000 characters');
        }
        if (!$this->StockID) {
            $result->addError('A report must be linked to a stock');
        }
        return $result;
    }

    /**
     * Reports contain visitors' email addresses, so unlike other market data they are not public.
     */
    public function canView($member = null)
    {
        return $this->canEdit($member);
    }
}
