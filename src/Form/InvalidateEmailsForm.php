<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Entity\User;

class InvalidateEmailsForm extends ConfigFormBase
{
    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames()
    {
        return [
            'dpc_user_management.adminsettings',
        ];
    }

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
    public function getFormId()
    {
        return 'admin_user_management_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildForm($form, $form_state);
        $form['bulk_invalidate_emails'] = [
            '#type'        => 'textarea',
            '#title'       => $this->t('List the emails you want to invalidate (one per line)'),
            '#description' => $this->t('The users will still have access to their account and will be prompted to remove or re-verify the email address.')
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
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $emails           = array_filter(preg_split("/\r\n|\n|\r| /", $form_state->getValue('bulk_invalidate_emails')));
        $users_updated    = [];
        $emails_not_found = [];

        foreach ($emails as $email) {
            $query = \Drupal::entityQuery('user');
            $query->Condition('field_email_addresses', $email, '=');
            $user_id = $query->execute();
            if (empty($user_id)) {
                $emails_not_found[] = $email;
                continue;
            }

            $user      = User::load(current($user_id));
            $addresses = $user->field_email_addresses->getValue();

            if (!empty($addresses)) {
                foreach ($addresses as $key => $address) {
                    if ($address['value'] == $email) {
                        $addresses[$key]['status']             = 'unverified';
                        $addresses[$key]['verification_token'] = null;
                    }
                }
                $users_updated[] = $user->getDisplayName();
                $user->field_email_addresses->setValue($addresses);
                $user->save();
            }
        }

        $users_updated = array_unique($users_updated);
        if (!empty($emails_not_found)) {
            $this->messenger()->addWarning($this->buildHTMLList($emails_not_found, 'No users were found associated with the following addresses:'));
        }
        if (empty($users_updated)) {
            parent::submitForm($form, $form_state);

            return;
        }
        $this->messenger()->addStatus($this->buildHTMLList($users_updated, 'The following users were updated:'));
    }

    /**
     * @param array $users
     *
     * @return TranslatableMarkup
     */
    private function buildHTMLList(array $users, $message = '')
    {
        $output = $message;
        $output .= '<ul>';

        foreach ($users as $user) {
            $output .= "<li>$user</li>";
        }

        $output .= '</ul>';
        $rendered_output = Markup::create($output);

        return new TranslatableMarkup ('@message', ['@message' => $rendered_output]);
    }
}