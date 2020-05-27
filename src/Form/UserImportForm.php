<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dpc_user_management\Controller\UserImportController;
use Drupal\file\Entity\File;

class UserImportForm extends ConfigFormBase
{
    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     */
    protected function getEditableConfigNames()
    {
        return [];
    }

    /**
     * Returns a unique string identifying the form.
     *
     * @return string
     */
    public function getFormId()
    {
        return 'dpc_user_import_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildForm($form, $form_state);
        $form['csv_file'] = [
            '#type'        => 'managed_file',
            '#title'       => $this->t('Upload a CSV file with users records'),
            '#description' => $this->t('The file has 4 columns: "FIRST NAME","SURNAME","EMAIL" and "REGISTRATION DATE". Use these exact headers. Fields needs to be quoted and the date needs to be in the format "YYYY-MM-DD". Max file size 8MB.'),
            '#upload_validators'  => array(
                'file_validate_extensions' => array('csv'),
                'file_validate_size' => array(8*1024*1024),
            ),
            '#required'    => true
        ];
        $form['whitelist'] = [
            '#type'        => 'textarea',
            '#title'       => $this->t('Allowed Domains'),
            '#description' => $this->t('A list of domains that white lists users in the CSV import file. The rest of the users will be ignored')
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    /**
     * @param FormStateInterface $form_state
     * @return EntityInterface|File|null
     */
    public function getCSVfile(FormStateInterface $form_state) {
        $file_array = $form_state->getValue('csv_file');

        if (!is_array($file_array) || !isset($file_array[0])) {
            return null;
        }

        return File::load($file_array[0]);
    }

    /**
     * @param FormStateInterface $form_state
     * @return array
     */
    public function getWhitelistDomains(FormStateInterface $form_state)
    {
        $pattern = '/^(?!\-)(?:(?:[a-zA-Z\d][a-zA-Z\d\-]{0,61})?[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/';

        return array_filter(
            array_map(
                function ($domain) {
                    return trim($domain);
                },
                // This ensure we get a properly exploded array. Remove first \r characters
                // and then explode by \n to support different linefeed formats
                explode("\n", str_replace("\r", '', $form_state->getValue('whitelist')))
            ),
            function ($domain) use ($pattern){
                return !!preg_match($pattern, $domain);
            }
        );
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // Initialise Controller
        $controller = new UserImportController();

        // Get File Entity from upload and whitelist domains
        $csv = $this->getCSVfile($form_state);
        $whitelist = $this->getWhitelistDomains($form_state);

        // Import Raw records and validates simple thigns like formats,
        // columns and whitelist domain
        $controller->processImport($csv, $whitelist);

        // Deletes uploaded file
        $csv->delete();

        // Redirects to Import Users
        return $this->redirect('dpc_user_management.user_import');
    }

    /**
     * @param array $users
     * @param string $message
     * @return TranslatableMarkup
     */
    private function buildHTMLList(array $users, $message = '')
    {
        $output = $message;

        // Stub, create markup for output

        $rendered_output = Markup::create($output);

        return new TranslatableMarkup ('@message', ['@message' => $rendered_output]);
    }
}
