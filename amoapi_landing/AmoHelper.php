<?php
namespace Amolib;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\BaseApiCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\Unsorted\FormsUnsortedCollection;
use AmoCRM\Collections\NotesCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Models\Unsorted\FormsMetadata;
use AmoCRM\Models\Unsorted\FormUnsortedModel;
use Illuminate\Support\Carbon;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
//use M3kat\Amointegration\Models\AmoAuthCredit;
//use M3kat\Amointegration\Models\Settings;
//use October\Rain\Argon\Argon;

class AmoHelper
{
    /**
     * @var AmoCRMApiClient
     */
    protected $apiClient;
    protected $unsortedService;

    /**
     * AmoHelper constructor.
     * @param $clientId
     * @param $clientSecret
     * @param $clientRedirectUri
     * @throws AmoCRMoAuthApiException
     */
    public function __construct($clientId, $clientSecret, $clientRedirectUri)
    {
        $json_auth = json_decode(file_get_contents('token_auth.json'));
        $settings = json_decode(file_get_contents('settings.json'));
        $authCredit = $json_auth;
        $this->apiClient = new AmoCRMApiClient($clientId, $clientSecret, $clientRedirectUri);
        $this->apiClient->setAccountBaseDomain($settings->base_domain);
        if ($authCredit->client_auth_code !== $settings->client_auth_code) {
            $code = $settings->client_auth_code;
            $options = $this->getTokensArrayByCode($code);
            $this->saveTokens($options);
        } else {
            $options = $this->getTokensArrayToSettings();
        }
        $token = new AccessToken($options);
        if ($token->hasExpired()) {
            $this->refreshTokens();
        } else {
            $this->setTokens($options);
        }
    }

    /**
     * @param array $options
     * @return bool
     */
    public function setTokens(array $options = [])
    {
        $accessToken = new AccessToken($options);
        $this->apiClient
            ->setAccessToken($accessToken)
            ->onAccessTokenRefresh(
                function () use ($accessToken) {
                    $this->refreshTokens();
                });
        // echo "\nSetted!";
        return true;
    }

    /**
     * @param $code
     * @return AccessTokenInterface
     * @throws AmoCRMoAuthApiException
     */
    public function getTokensByCode($code)
    {
        return $this->apiClient->getOAuthClient()->getAccessTokenByCode($code);
    }

    /**
     * @param array $options
     * @return bool
     */
    public function saveTokens(array $options = [])
    {
        $authCredit = json_decode(file_get_contents('token_auth.json'));
        $settings = json_decode(file_get_contents('settings.json'));
            $authCredit->client_auth_code = $settings->client_auth_code;
            $authCredit->access_token = $options['access_token'];
            $authCredit->refresh_token = $options['refresh_token'];
            $authCredit->expires = $options['expires'];
            $authCredit_json = json_encode($authCredit);
            file_put_contents('token_auth.json', $authCredit_json);
            // echo 'Saved!';
        return true;
    }

    /**
     * @param array $options
     * @return bool
     * @throws AmoCRMoAuthApiException
     */
    public function refreshTokens()
    {
        $options = $this->getTokensArrayToSettings();
        $oldTokens = new AccessToken($options);
        $tokens = $this->apiClient->getOAuthClient()->getAccessTokenByRefreshToken($oldTokens);
        $options = [
            'access_token' => $tokens->getToken(),
            'refresh_token' => $tokens->getRefreshToken(),
            'expires' => $tokens->getExpires()
        ];
        $this->saveTokens($options);
        $this->setTokens($options);
        return true;
    }

    /**
     * @param $code
     * @return array
     * @throws AmoCRMoAuthApiException
     */
    public function getTokensArrayByCode($code)
    {
        $tokens = $this->getTokensByCode($code);
        return [
            'access_token' => $tokens->getToken(),
            'refresh_token' => $tokens->getRefreshToken(),
            'expires' => $tokens->getExpires()
        ];
    }

    /**
     * @return array
     */
    public function getTokensArrayToSettings()
    {
        $authCredit = json_decode(file_get_contents('token_auth.json'));
        return [
            'access_token' => $authCredit->access_token,
            'refresh_token' => $authCredit->refresh_token,
            'expires' => $authCredit->expires
        ];
    }

    public function isRefreshTokenExpiring() : Bool
    {
        $currentDate = new Carbon();
        $authCredit = json_decode(file_get_contents('token_auth.json'));
        $updated = new Carbon($authCredit->updated_at);
        $fmonth = $updated->addMonths(2);
        $updated = new Carbon($authCredit->updated_at);
        $smonth = $updated->addMonths(3);
        if ($currentDate->between($fmonth, $smonth)) {
            return true;
        } else {
            return false;
        }
    }
    function printError(AmoCRMApiException $e)
    {
        $errorTitle = $e->getTitle();
        $code = $e->getCode();
        $debugInfo = var_export($e->getLastRequestInfo(), true);

        $error = <<<EOF
        Error: $errorTitle
        Code: $code
        Debug: $debugInfo
EOF;

        echo '<pre>' . $error . '</pre>';
    }
    public function setLead(array $leadParams = []): LeadModel
    {
        return (new LeadModel())
            ->setName($leadParams['leadName']);
//            ->setPrice($leadParams['leadPrice']);
    }

    public function setContact(array $contactParams = []): ContactModel
    {
        return new ContactModel();
//            ->setName($contactParams['name']);
    }

    public function setCustomFields(array $customFieldsParams = []): CustomFieldsValuesCollection
    {
        $customFieldsValuesCollection = new CustomFieldsValuesCollection();
        if ($customFieldsParams['phone']) {
            $customFieldsValuesCollection
                ->add((new MultitextCustomFieldValuesModel())->setFieldCode('PHONE')
                    ->setValues((new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())
                            ->setEnum('WORK')
                            ->setValue($customFieldsParams['phone']))));
        }
        if ($customFieldsParams['email']) {
            $customFieldsValuesCollection
                ->add((new MultitextCustomFieldValuesModel())->setFieldCode('EMAIL')
                    ->setValues((new MultitextCustomFieldValueCollection())
                        ->add((new MultitextCustomFieldValueModel())
                            ->setEnum('WORK')
                            ->setValue($customFieldsParams['email']))));
        }
        return $customFieldsValuesCollection;
    }

    public function setUnsortedForm(array $unsortedFormParams = []): BaseApiCollection
    {
        $formsUnsortedCollection = new FormsUnsortedCollection();
        $formUnsorted = new FormUnsortedModel();
        $formsMetadata = new FormsMetadata();
        $UnsortedLead = $this->setLead($unsortedFormParams['leadParams']);
        $contactCustomFields = $this->setCustomFields($unsortedFormParams['customFieldsParams']);
        $unsortedContact = $this->setContact();
        $unsortedContact->setCustomFieldsValues($contactCustomFields);
        $unsortedContactsCollection = (new ContactsCollection())->add($unsortedContact);
        $formsMetadata->setFormId(bcrypt($unsortedFormParams['formName']))
            ->setFormName($unsortedFormParams['formName'])
            ->setFormPage($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])
            ->setFormSentAt(time())
            ->setReferer('https://google.com/search')
            ->setIp($_SERVER['REMOTE_ADDR']);
        $formUnsorted->setSourceName($unsortedFormParams['sourceName'])
            ->setSourceUid(bcrypt($unsortedFormParams['sourceName']))
            ->setCreatedAt(time())
            ->setMetadata($formsMetadata)
            ->setLead($UnsortedLead)
            ->setContacts($unsortedContactsCollection)
            ->setPipelineId(3913081);
        $formsUnsortedCollection->add($formUnsorted);
        $unsortedService = $this->apiClient->unsorted();

        try {
            $returnedFormsUnsortedCollection = $unsortedService->add($formsUnsortedCollection);
        } catch (AmoCRMApiException $e) {
            throw new AmoCRMApiException($e);
        }
        return $returnedFormsUnsortedCollection;
    }
    public function setUnsortedFormTest(): BaseApiCollection
    {
        $formsUnsortedCollection = new FormsUnsortedCollection();
        $formUnsorted = new FormUnsortedModel();
        $formsMetadata = new FormsMetadata();
        $UnsortedLead = $this->setLead( [
            'leadName' => 'uMarkit',
        ]);
        $contactCustomFields = $this->setCustomFields([
            'phone' => '+7 495 777 07 91',
            'email' => 'clients@umarkit.pro',
        ]);
        $unsortedContact = $this->setContact();
        $unsortedContact->setCustomFieldsValues($contactCustomFields);
        $unsortedContactsCollection = (new ContactsCollection())->add($unsortedContact);
        $formsMetadata->setFormId('my_form')
            ->setFormName('my_form')
            ->setFormPage($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])
            ->setFormSentAt(time())
            ->setReferer('https://google.com/search')
            ->setIp($_SERVER['REMOTE_ADDR']);
        $formUnsorted->setSourceName('umarkit')
            ->setSourceUid('uMarkit')
            ->setCreatedAt(time())
            ->setMetadata($formsMetadata)
            ->setLead($UnsortedLead)
            ->setContacts($unsortedContactsCollection)
            ->setPipelineId(3922405);
        $formsUnsortedCollection->add($formUnsorted);
        $unsortedService = $this->apiClient->unsorted();

        try {
            $returnedFormsUnsortedCollection = $unsortedService->add($formsUnsortedCollection);
        } catch (AmoCRMApiException $e) {
            throw new AmoCRMApiException($e);
        }
        return $returnedFormsUnsortedCollection;
    }

    public function setNotes(array $notesParams = [])
    {
        $notesCollection = new NotesCollection();
        $commonNote = new CommonNote();
        try {
            $entityId = $this->setUnsortedForm($notesParams['unsortedFormParams']);
        } catch (AmoCRMApiException $e) {
            throw new AmoCRMApiException($e);
        }
        $commonNote->setEntityId($entityId->first()->lead->id)
            ->setText($notesParams['textNote']);
        $notesCollection->add($commonNote);
        try {
            $leadNotesService = $this->apiClient->notes(EntityTypesInterface::LEADS);
            $notesCollection = $leadNotesService->add($notesCollection);
        } catch (AmoCRMApiException $e) {
            throw new AmoCRMApiException($e);
        }
        return true;
    }

    public function addLead($description, $tel, $name) : bool {
        $formsUnsortedCollection = new FormsUnsortedCollection();
        $formUnsorted = new FormUnsortedModel();
        $formsMetadata = new FormsMetadata();
        $UnsortedLead = $this->setLead( [
            'leadName' => 'uMarkit',
        ]);
        $contactCustomFields = $this->setCustomFields([
            'phone' => $tel
        ]);
        $unsortedContact = $this->setContact();
        $unsortedContact->setCustomFieldsValues($contactCustomFields);
        $unsortedContactsCollection = (new ContactsCollection())->add($unsortedContact);
        $formsMetadata->setFormId('my_form')
            ->setFormName('cool')
            ->setFormPage('Заявка с uMarkit!')
            ->setFormSentAt(time())
            ->setReferer('https://umarkt.it/')
            ->setIp($_SERVER['REMOTE_ADDR']);
        $formUnsorted->setSourceName('Заявка с Umarkit!')
            ->setSourceUid('uMarkit')
            ->setCreatedAt(time())
            ->setMetadata($formsMetadata)
            ->setLead($UnsortedLead)
            ->setContacts($unsortedContactsCollection)
            ->setPipelineId(3921853);
        $formsUnsortedCollection->add($formUnsorted);
        $unsortedService = $this->apiClient->unsorted();
        try {
            $addedLead = $unsortedService->add($formsUnsortedCollection);
        } catch (AmoCRMApiException $e) {
            var_dump($unsortedService->getLastRequestInfo());
        }

        $notesCollection = new NotesCollection();
        $commonNote = new CommonNote();

        $commonNote->setEntityId($addedLead->first()->lead->id);
        $commonNoteText = "";
        $commonNoteText= "Имя: \n" . $name . "\n\nОписание: \n" . $description . "\n\nТелефон: \n" . $tel;

        $commonNote->setText($commonNoteText);
        $notesCollection->add($commonNote);
        try {
            $leadNotesService = $this->apiClient->notes(EntityTypesInterface::LEADS);
            $notesCollection = $leadNotesService->add($notesCollection);
        } catch (AmoCRMApiException $e) {
            throw new AmoCRMApiException($e);
        }
        return True;
    }

    public function miniAdd($tel) : bool {
        $formsUnsortedCollection = new FormsUnsortedCollection();
        $formUnsorted = new FormUnsortedModel();
        $formsMetadata = new FormsMetadata();
        $UnsortedLead = $this->setLead( [
            'leadName' => 'uMarkit - minilanding',
        ]);
        $contactCustomFields = $this->setCustomFields([
            'phone' => $tel
        ]);
        $unsortedContact = $this->setContact();
        $unsortedContact->setCustomFieldsValues($contactCustomFields);
        $unsortedContactsCollection = (new ContactsCollection())->add($unsortedContact);
        $formsMetadata->setFormId('my_form')
            ->setFormName('cool')
            ->setFormPage('uMarkit - minilanding заявка')
            ->setFormSentAt(time())
            ->setReferer('https://umarkt.it/land')
            ->setIp($_SERVER['REMOTE_ADDR']);
        $formUnsorted->setSourceName('Заявка с Umarkit!')
            ->setSourceUid('uMarkit')
            ->setCreatedAt(time())
            ->setMetadata($formsMetadata)
            ->setLead($UnsortedLead)
            ->setContacts($unsortedContactsCollection)
            ->setPipelineId(3921853);
        $formsUnsortedCollection->add($formUnsorted);
        $unsortedService = $this->apiClient->unsorted();
        try {
            $addedLead = $unsortedService->add($formsUnsortedCollection);
        } catch (AmoCRMApiException $e) {
            var_dump($unsortedService->getLastRequestInfo());
        }

        // $notesCollection = new NotesCollection();
        // $commonNote = new CommonNote();

        // $commonNote->setEntityId($addedLead->first()->lead->id);
        // $commonNoteText = "";
        // $commonNoteText= "Имя: \n" . $name . "\n\nОписание: \n" . $description . "\n\nТелефон: \n" . $tel;

        // $commonNote->setText($commonNoteText);
        // $notesCollection->add($commonNote);
        // try {
        //     $leadNotesService = $this->apiClient->notes(EntityTypesInterface::LEADS);
        //     $notesCollection = $leadNotesService->add($notesCollection);
        // } catch (AmoCRMApiException $e) {
        //     throw new AmoCRMApiException($e);
        // }
        // return True;
    }

    public function getLeads() {
        $lead = $this->apiClient->leads()->get();
        return $lead->toArray();
    }
}
