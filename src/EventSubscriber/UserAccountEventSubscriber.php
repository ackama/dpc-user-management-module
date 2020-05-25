<?php

namespace Drupal\dpc_user_management\EventSubscriber;

use DrewM\MailChimp\MailChimp;
use Drupal\dpc_user_management\Traits\HandlesMailchimpSubscriptions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class UserAccountEventSubscriber.
 */
class UserAccountEventSubscriber implements EventSubscriberInterface
{
    use HandlesMailchimpSubscriptions {
        HandlesMailchimpSubscriptions::__construct as private _mc_handler_construct;
    }

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
        try {
            $this->_mc_handler_construct();
        } catch (\Exception $e) {
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $events['primaryEmailUpdated']     = ['updateMailChimpAddress'];
        $events['primaryEmailInvalidated'] = ['unsubscribeEmailAddress'];

        return $events;
    }

    /**
     * This method is called when the PrimaryEmailUpdated is dispatched.
     *
     * @param \Symfony\Component\EventDispatcher\Event $event
     *   The dispatched event.
     */
    public function updateMailChimpAddress(Event $event)
    {
        \Drupal::messenger()->addMessage('Event PrimaryEmailUpdated thrown by Subscriber in module dpc_user_management.', 'status', true);
        \Drupal::messenger()->addStatus('attempt to update primary');

        if (!$event->account->hasGroupContentAccess()) {
            $event->stopPropagation();

            return;
        };

        $new_email = $event->account->getEmail();

        if ($event->old_address) {
            // find the MC member and update their address
            $this->updateEmail($event->account, $event->old_address, $new_email);

            return;
        }
        \Drupal::messenger()->addStatus($event->account->getDisplayName() . ' primary email updated in MailChimp');
        // subscribe the new email
        $this->subscribe($event->account);

        $event->stopPropagation();
    }

    /**
     * This method is called when the PrimaryEmailInvalidated is dispatched.
     *
     * @param \Symfony\Component\EventDispatcher\Event $event
     *   The dispatched event.
     */
    public function unsubscribeEmailAddress(Event $event)
    {
        \Drupal::messenger()->addMessage('Event PrimaryEmailInvalidated thrown by Subscriber in module dpc_user_management.', 'status', true);
        \Drupal::messenger()->addStatus($event->account->getDisplayName(). ' unsubscribed from MailChimp');

        $this->unsubscribe($event->account, $event->address);

        $event->stopPropagation();
    }
}
