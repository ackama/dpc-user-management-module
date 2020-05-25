<?php

namespace Drupal\dpc_user_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Mailchimp\MailchimpAPIException;

/**
 * Configure Mailchimp settings for this site.
 */
class MailchimpAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'mailchimp_admin_settings';
  }

  protected function getEditableConfigNames() {
    return ['dpc_mailchimp.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dpc_mailchimp.settings');

    $mc_api_url = Url::fromUri('http://admin.mailchimp.com/account/api', array('attributes' => array('target' => '_blank')));
    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Mailchimp API Key'),
      '#required' => TRUE,
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('The API key for your Mailchimp account. Get or generate a valid API key at your @apilink.', array('@apilink' => Link::fromTextAndUrl($this->t('Mailchimp API Dashboard'), $mc_api_url)->toString())),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('dpc_mailchimp.settings');
    $config
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
