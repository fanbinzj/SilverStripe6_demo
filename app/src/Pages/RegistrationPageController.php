<?php

namespace App\Pages;

use App\Security\Membership;
use PageController;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\ConfirmedPasswordField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * @extends PageController<RegistrationPage>
 */
class RegistrationPageController extends PageController
{
    private static $allowed_actions = [
        'RegistrationForm',
    ];

    public function index()
    {
        // Already logged in: nothing to register
        if (Security::getCurrentUser()) {
            return $this->redirect(AccountPage::get()->first()?->Link() ?? '/');
        }
        return [];
    }

    public function RegistrationForm(): Form
    {
        $disclaimer = $this->SiteConfig()->DisclaimerPage();
        // Form field titles are output as HTML (FormField::setTitle() expects escaped HTML),
        // so anything dynamic in the label must be escaped here
        $termsLabel = $disclaimer->exists()
            ? 'I have read the <a href="' . Convert::raw2att($disclaimer->Link()) . '" target="_blank">disclaimer</a>'
            : 'I understand this site provides information only, not financial advice';

        $fields = FieldList::create(
            TextField::create('FirstName', 'First name')->setAttribute('autocomplete', 'given-name'),
            EmailField::create('Email', 'Email')->setAttribute('autocomplete', 'email'),
            ConfirmedPasswordField::create('Password', 'Password'),
            CheckboxField::create('AgreeDisclaimer', $termsLabel)
        );

        $actions = FieldList::create(FormAction::create('doRegister', 'Create account'));
        $validator = RequiredFieldsValidator::create(['FirstName', 'Email', 'Password', 'AgreeDisclaimer']);

        return Form::create($this, 'RegistrationForm', $fields, $actions, $validator);
    }

    public function doRegister(array $data, Form $form): HTTPResponse
    {
        $email = strtolower(trim($data['Email']));

        if (Member::get()->filter('Email', $email)->exists()) {
            $form->sessionMessage('An account with this email address already exists. Try logging in instead.', 'bad');
            $form->setSessionData(['FirstName' => $data['FirstName'], 'Email' => $email]);
            return $this->redirectBack();
        }

        // Set fields explicitly instead of saveInto(): the form has fields (the disclaimer
        // checkbox) that don't belong on Member, and nothing else should be settable
        $member = Member::create();
        $member->FirstName = trim($data['FirstName']);
        $member->Email = $email;
        $member->Password = $form->Fields()->dataFieldByName('Password')->dataValue();

        try {
            // Member::validate() checks password strength with the configured PasswordValidator
            $member->write();
        } catch (ValidationException $e) {
            $form->setSessionValidationResult($e->getResult());
            // Keep what they typed, but never put the password in the session
            $form->setSessionData(['FirstName' => $data['FirstName'], 'Email' => $email]);
            return $this->redirectBack();
        }

        $member->addToGroupByCode(Membership::group()->Code);

        // Log them in straight away. IdentityStore handles the session (including a new session ID)
        Injector::inst()->get(IdentityStore::class)->logIn($member, false, $this->getRequest());

        return $this->redirect(AccountPage::get()->first()?->Link() ?? '/');
    }
}
