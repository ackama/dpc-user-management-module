<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ValidateEmailForm extends FormBase{
    /**
     * Returns a unique string identifying the form.
     *
     * The returned ID should be a unique string that can be a valid PHP function
     * name, since it's used in hook implementation names such as
     * hook_form_FORM_ID_alter().
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId() {
        return 'validate_email_form';
    }


    /**
     * @param array $form
     * @param FormStateInterface $form_state
     * @return array
     */
    public function buildForm(array $form, FormStateInterface $form_state, $parameter = NULL) {
//        $form = parent::buildForm($form, $form_state);
        $form['help'] = [
            '#type' => 'item',
            '#title' => t('Please click below to confirm email change'),
        ];
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Validate Email'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    /**
     * @param array $form
     * @param FormStateInterface $form_state
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $uri = \Drupal::request()->getRequestUri();
        $verify_uri = str_replace("verify-confirm", "verify", $uri);
        $response = new RedirectResponse($verify_uri);
        $response->send();
    }
}
