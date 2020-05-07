<?php

namespace Drupal\custom_events\Event;

use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user logs in.
 */
class PrimaryEmailUpdated extends Event {

    const EVENT_NAME = 'primaryEmailUpdated';

    /**
     * The user account.
     *
     * @var \Drupal\user\UserInterface
     */
    public $account;

    /**
     * @var string|null
     */
    public $old_address;

    /**
     * Constructs the object.
     *
     * @param \Drupal\user\UserInterface $account
     *   The account of the user logged in.
     */
    public function __construct(UserInterface $account) {
        $this->account = $account;
        $this->old_address = \Drupal::service('user.data')->get('dpc_user_management', $account->id(), 'mc_subscribed_email');
    }
}