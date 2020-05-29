<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dpc_user_management\Controller\UserImportController;

class UserImportCommitForm extends ConfigFormBase
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
        return 'dpc_user_import_commit_form';
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
            $this->record_count = $this->getController()->getCountByStatus(UserImportController::ST_NEW);
        }

        return $this->record_count;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildForm($form, $form_state);

        $records_current = $this->getPendingRecordsCount();

        $form['#prefix'] = sprintf('<p>There are %s records to be imported as users</p>', $records_current);

        $form['records_current'] = [
            '#type' => 'value',
            '#value' => $records_current,
        ];

        $message = $this->t('Start Importing');

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
            'title' => t('Importing Users...'),
            'operations' => [],
            'init_message'     => t('Validating Records and Creating Users...'),
            'progress_message' => t('Processing...'),
            'error_message'    => t('An error occurred during processing'),
            'progressive'      => true,
            'finished' => '\Drupal\dpc_user_management\Controller\UserImportController::processAndImportUsersFinishedCallback',
        );

        $batch['operations'][] = [
            '\Drupal\dpc_user_management\Controller\UserImportController::processAndImportUsers',
            [$this->getPendingRecordsCount()]
        ];

        batch_set($batch);
    }
}
