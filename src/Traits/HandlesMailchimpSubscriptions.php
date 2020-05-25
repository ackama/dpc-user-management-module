<?php

namespace Drupal\dpc_user_management\Traits;

use DrewM\MailChimp\MailChimp;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

trait HandlesMailchimpSubscriptions
{
    /**
     * @var MailChimp|null
     */
    protected $mailchimp = null;
    /**
     * @var array|mixed|null
     */
    protected $audience_id = null;
    /**
     * @var \Drupal\Core\Config\ImmutableConfig
     */
    protected $config;
    /**
     * @var \DrewM\MailChimp\Batch
     */
    protected $batch;

    /**
     * HandlesMailchimpSubscriptions constructor.
     *
     * @param string $mailchimp
     * @param null   $audience_id
     *
     * @throws \Exception
     */
    public function __construct($mailchimp = null, $audience_id = null)
    {
        $this->mailchimp = $mailchimp;
        $this->audience_id = $audience_id;

        $this->config      = \Drupal::config('dpc_mailchimp.settings');
        if (!$mailchimp) {
            $this->mailchimp   = new MailChimp($this->config->get('api_key'));
        }
        if (!$this->audience_id) {
            $this->audience_id = $this->config->get('audience_id');
        }

        $this->batch = $this->mailchimp->new_batch();
    }

    /**
     * @param UserInterface $user
     */
    public function subscribe(UserInterface $user)
    {
        if (!$this->mailchimpConnected()) {
            return;
        }

        $this->mailchimp->post("lists/$this->audience_id/members", [
            'email_address' => $user->getEmail(),
            'status'        => 'subscribed'
        ]);

        $user->field_mailchimp_audience_status->setValue('subscribed');
        \Drupal::service('user.data')->set('dpc_user_management', $user->id(), 'mc_subscribed_email', $user->getEmail());
    }

    /**
     * @param UserInterface $user
     * @param               $subscribed_address
     */
    public function unsubscribe(UserInterface $user, $subscribed_address)
    {
        if (!$this->mailchimpConnected()) {
            return;
        }

        $member_id = $this->mailchimp::subscriberHash($subscribed_address);

        $this->mailchimp->put("lists/$this->audience_id/members/$member_id", [
            'status' => 'unsubscribed',
        ]);

        $user->field_mailchimp_audience_status->setValue('unsubscribed');
        $user->save();
        \Drupal::service('user.data')->delete('dpc_user_management', $user->id(), 'mc_subscribed_email');
    }

    /**
     * @param UserInterface $user
     * @param               $old_address
     * @param               $new_email
     */
    public function updateEmail(UserInterface $user, $old_address, $new_email)
    {
        if (!$this->mailchimpConnected()) {
            return;
        }

        $member_id = $this->mailchimp::subscriberHash($old_address);
        $this->mailchimp->patch("lists/$this->audience_id/members/$member_id", [
            'email_address' => $new_email
        ]);

        \Drupal::service('user.data')->set('dpc_user_management', $user->id(), 'mc_subscribed_email', $new_email);
    }

    /**
     * @param $user
     */
    public function batchSubscribe(User $user)
    {
        if (!$this->mailchimpConnected()) {
            return;
        }

        $this->batch->post('subscribed', "lists/$this->audience_id/members", [
            'email_address' => $user->mail->value,
            'status'        => 'subscribed',
        ]);

        $user->field_mailchimp_audience_status->setValue('subscribed');
        $user->save();
        \Drupal::service('user.data')->set('dpc_user_management', $user->id(), 'mc_subscribed_email', $user->mail->value);
    }

    /**
     * @param      $subscribed_address
     */
    public function batchUnsubscribe($subscribed_address)
    {
        if (!$this->mailchimpConnected()) {
            return;
        }

        $member_id = $this->mailchimp::subscriberHash($subscribed_address);

        $this->batch->put('unsubscribed', "lists/$this->audience_id/members/" . $member_id, [
            'status' => 'unsubscribed',
        ]);
    }

    /**
     * @return bool
     */
    private function mailchimpConnected()
    {
        return $this->mailchimp && $this->audience_id;
    }
}