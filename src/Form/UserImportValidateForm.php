<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dpc_user_management\Controller\UserImportController;

class UserImportValidateForm extends ConfigFormBase
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
        return 'dpc_user_import_validate_form';
    }

    /**
     * Provides an instance of the UserImportController
     */
    public function getController() {
        return new UserImportController();
    }

    /**
     * Saves current record count to save queries and enable
     * serialisation of the class used by drupal's batch api
     *
     * @var int
     */
    private $record_count = 0;

    /**
     * Returns the amount of pending records for this process
     *
     * @return int
     */
    public function getPendingRecordsCount() {
        if(!$this->record_count) {
            $this->record_count = $this->getController()->getCountByStatus(UserImportController::ST_RAW);
        }

        return $this->record_count;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildForm($form, $form_state);

        $records_raw_current = $this->getController()->getCountByStatus(UserImportController::ST_RAW);

        $form['#prefix'] = sprintf('<p>There are %s records pending processing and validation</p>', $records_raw_current);

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

        $message = ($records_raw_current == $records_raw_start)
            ? $this->t('Start Validating')
            : $this->t('Continue Validating');

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $message,
            '#button_type' => 'primary'
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // @see batch_set()
        $batch = array(
            'title' => t('Validating and Processing Records...'),
            'operations' => [],
            'init_message'     => t('Validating and Processing Records...'),
            'progress_message' => t('Processing...'),
            'error_message'    => t('An error occurred during processing'),
            'progressive'      => true,
            'finished' => '\Drupal\dpc_user_management\Controller\UserImportController::processAndValidateRecordsFinishedCallback',
        );

        $batch['operations'][] = [
            '\Drupal\dpc_user_management\Controller\UserImportController::processAndValidateRecords',
            [$this->getPendingRecordsCount()]
        ];

        batch_set($batch);
    }
}
