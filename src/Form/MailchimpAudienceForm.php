<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use DrewM\MailChimp\MailChimp;

/**
 * Configure Mailchimp settings for this site.
 */
class MailchimpAudienceForm extends ConfigFormBase
{
    protected $mailchimp = null;

    public function __construct(ConfigFactoryInterface $config_factory)
    {
        parent::__construct($config_factory);

        try {
            $this->mailchimp = $this->mailchimpConfig()->get('api_key') ? new MailChimp($this->mailchimpConfig()->get('api_key')) : null;
        } catch (\Exception $exception) {
            \Drupal::messenger()->addError('Mailchimp API connection error: ' . $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFormID()
    {
        return 'mailchimp_audience_settings';
    }

    /**
     * @return array
     */
    protected function getEditableConfigNames()
    {
        return ['dpc_mailchimp.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['audience_id'] = [
            '#title'         => $this->t('Mailchimp Audience'),
            '#type'          => 'select',
            '#options'       => $this->getMailchimpListOptions(),
            '#required'      => true,
            '#disabled'      => !$this->mailchimp,
            '#default_value' => $this->mailchimpConfig()->get('audience_id'),
            '#description'   => $this->t('Choose the audience you wish to use for user subcriptions')
        ];

        $form['refresh_audience'] = [
            '#title'  => $this->t('refresh audience'),
            '#markup' => $this->getResyncMarkup(),
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $config = $this->mailchimpConfig();
        $config
            ->set('audience_id', $form_state->getValue('audience_id'))
            ->save();

        parent::submitForm($form, $form_state);
    }

    /**
     * @return array
     */
    private function getMailchimpListOptions()
    {
        $lists   = $this->mailchimp->get('lists');
        $options = [];
        if (isset($lists['lists'])) {
            foreach ($lists['lists'] as $list) {
                $options[$list['id']] = $list['name'];
            }
        }

        return $options;
    }

    /**
     * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig|mixed
     */
    private function mailchimpConfig()
    {
        return $this->config('dpc_mailchimp.settings');
    }

    /**
     * @return string
     */
    private function getResyncMarkup()
    {
        $markup = '<div class="form-item"><span class="button ' . ( !$this->mailchimp ? 'is-disabled' : '' ) . '" id="dpc_resync_mc">Re-sync audience with users</span> <br/>';
        $markup .= $this->t('Syncing the Mailchimp Audience with the drupal users ensures that eligible users are part of the MailChimp audience.');
        $markup .= '</div>';

        return $markup;
    }
}
