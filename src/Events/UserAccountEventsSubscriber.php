<?php

namespace Drupal\dpc_user_management\Events;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use DrewM\MailChimp\MailChimp;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\custom_events\EventSubscriber
 */
class UserAccountEventsSubscriber implements EventSubscriberInterface
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

    public function __construct()
    {
        $this->config      = \Drupal::config('dpc_mailchimp.settings');
        $this->audience_id = $this->config->get('audience_id');
        try {
            $this->mailchimp = new MailChimp($this->config->get('api_key'));
        } catch (\Exception $e) {
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'primaryEmailUpdated'     => 'updateMailchimpEmailAddress',
            'primaryEmailInvalidated' => 'unsubscribeEmailAddress',
        ];
    }

    /**
     * @param Event $event
     */
    public function updateMailchimpEmailAddress(Event $event)
    {
        if (!$this->mailchimp || !$this->audience_id) {
            return;
        }

        $new_email = $event->account->getEmail();

        if ($event->old_address) {
            // find the MC member and update their address
            $member_id = $this->mailchimp::subscriberHash($event->old_address);
            $this->mailchimp->patch("lists/$this->audience_id/members/$member_id", [
                'email_address' => $new_email
            ]);
            \Drupal::service('user.data')->set('dpc_user_management', $event->account->id(), 'mc_subscribed_email', $new_email);
            return;
        }

        // subscribe the new email
        $this->mailchimp->post("lists/$this->audience_id/members", [
            'email_address' => $new_email,
            'status'        => 'subscribed',
        ]);

        \Drupal::service('user.data')->set('dpc_user_management', $event->account->id(), 'mc_subscribed_email', $new_email);

    }

    /**
     * @param Event $event
     */
    public function unsubscribeEmailAddress(Event $event)
    {
        if (!$this->mailchimp || !$this->audience_id) {
            return;
        }

        $member_id = $this->mailchimp::subscriberHash($event->address);

        $this->mailchimp->put( "lists/$this->audience_id/members/$member_id", [
            'status' => 'unsubscribed',
        ]);

        \Drupal::service('user.data')->delete('dpc_user_management', $event->account->id(), 'mc_subscribed_email');
    }
}