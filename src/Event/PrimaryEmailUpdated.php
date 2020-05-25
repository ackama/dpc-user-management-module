<?php

namespace Drupal\dpc_user_management\Event;

use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event that is fired when a user changes their primary email address.
 */
class PrimaryEmailUpdated extends Event {

    const EVENT_NAME = 'primaryEmailUpdated';

    /**
     * The user account.
     *
     * @var User
     */
    public $account;

    /**
     * @var string|null
     */
    public $old_address;

    /**
     * Constructs the object.
     *
     * @param User $account
     */
    public function __construct(User $account) {
        $this->account = $account;
        $this->old_address = \Drupal::service('user.data')->get('dpc_user_management', $account->id(), 'mc_subscribed_email');
    }
}