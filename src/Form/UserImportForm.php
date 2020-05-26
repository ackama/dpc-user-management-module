<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dpc_user_management\Controller\UserImportController;

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
        $form['invalid_domains'] = [
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
     * {@inheritdoc}
     * @throws \Exception
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $controller = new UserImportController();

        $controller->processImport($form_state);
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
