<?php

namespace Drupal\dpc_user_management\Events;

use Drupal\custom_events\Event\PrimaryEmailUpdated;
use Drupal\dpc_user_management\Traits\HandlesMailchimpSubscriptions;
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
     * @param PrimaryEmailUpdated $event
     */
    public function updateMailchimpEmailAddress(PrimaryEmailUpdated $event)
    {
        if (!$event->account->hasAccess()) {
            return;
        };

        $new_email = $event->account->getEmail();

        if ($event->old_address) {
            // find the MC member and update their address
            $this->updateEmail($event->account, $event->old_address, $new_email);
            return;
        }

        // subscribe the new email
        $this->subscribe($event->account);
    }

    /**
     * @param Event $event
     */
    public function unsubscribeEmailAddress(Event $event)
    {
        $this->unsubscribe($event->account, $event->address);
    }
}