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

    private $record_ids = [];

    public function getPendingRecords() {

        if(empty($this->record_ids)) {
            $this->record_ids = array_map(
                function($r) { return $r->id; },
                $this->getController()->getRecordsIDsByStatus(UserImportController::ST_RAW)
            );
        }

        return $this->record_ids;
    }

    public function getController() {
        return new UserImportController();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildForm($form, $form_state);

        $records_raw_current = $this->getController()->getCountByStatus($this->getController()::ST_RAW);

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
    public function validateForm(array &$form, FormStateInterface $form_state) {

    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $batch = array(
            'title' => t('Validating and Processing Records...'),
            'operations' => [],
            'init_message'     => t('Commencing'),
            'progress_message' => t('Processed @current out of @total.'),
            'error_message'    => t('An error occurred during processing'),
            'finished' => '\Drupal\dpc_user_management\UserImportController::processAndValidateRecordsFinishedCallback',
        );

        $batch['operations'] = array_map(
            function($record){
                return [
                    '\Drupal\dpc_user_management\UserImportController::processAndValidateRecords',
                    [$record->id]
                ];
            },
            $this->getPendingRecords()
        );

        batch_set($batch);

        \Drupal::messenger()->addMessage('Processed!');

        $form_state->setRebuild(TRUE);
    }


    public function validateChunk() {

        sleep(5);

//        $controller = new UserImportController();
//        $results = $controller->validateChunk();

        return [100];
    }

}
