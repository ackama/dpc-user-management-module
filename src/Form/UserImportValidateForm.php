<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dpc_user_management\Controller\UserImportController;
use Symfony\Component\Console\Helper\ProgressBar;

class UserImportValidateForm extends ConfigFormBase
{
    /**
     * @var UserImportController
     */
    public $_controller;
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
        return 'dpc_user_import_commit_form';
    }

    public function getController() {
        if (!$this->_controller) {
            $this->_controller = new UserImportController();
        }

        return $this->_controller;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildForm($form, $form_state);

        $records_raw_current = $this->getController()->getCountByStatus($this->getController()::ST_RAW);

        $form['records_raw_current'] = [
            '#type' => 'value',
            '#value' => $records_raw_current,
        ];

        $records_raw_start = $form_state->getValue(
            'records_raw_start',
            $records_raw_current
        );

        $form['records_raw_start'] = [
            '#type' => 'value',
            '#value' => $records_raw_start,
        ];

//
//        $sadf = '<div id="ajax-progress-edit-button" class="progress ajax-progress ajax-progress-bar" aria-live="polite">
//                    <div class="progress__label">&nbsp;</div>
//                    <div class="progress__track">
//                        <div class="progress__bar"></div>
//                    </div>
//                    <div class="progress__percentage"></div>
//                    <div class="progress__description">Validating...</div>
//                </div>';

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Start Validating'),
            '#button_type' => 'primary'
        ];

        return $form;
    }

    public function validateChunk() {

        sleep(5);

//        $controller = new UserImportController();
//        $results = $controller->validateChunk();

        return [100];
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        dpm($form_state->getValue('available_records'));
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
