<?php

namespace Drupal\dpc_user_management\Handlers;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\Group;

/**
 * Class SpecialGroupsMembershipHandler
 * @package Drupal\dpc_user_management\Handlers
 */
class SpecialGroupsMembershipHandler
{

    /**
     * @var AccountInterface
     */
    protected $user;

    /**
     * SpecialGroupsMembershipHandler constructor.
     *
     * @param AccountInterface $user
     */
    function __construct(AccountInterface $user)
    {
        $this->user = $user;
    }

    /**
     * @param Group $group
     * @return bool
     */
    public function inGroup(Group $group)
    {
        return !!$group->getMember($this->user);
    }

    /**
     * @param Group           $group
     */
    public function addToGroup(Group $group)
    {
        if (!$this->inGroup($group)) {
            // @ToDo Log Event for Addition
            // dpc_log_event('added', $group->id(), $user->id());
            $group->addMember($this->user);
        }
    }

    /**
     * @param Group            $group
     */
    public function removeFromGroup(Group $group)
    {
        if($this->inGroup($group)) {
            // @ToDo Log Event for Removal
            // dpc_log_event('removed', $group->id(), $this->user->id());
            $group->removeMember($this->user);
        }
    }

    /**
     * @param int $_id
     */
    public function addToGroupByID(int $_id) {
        /** @var Group $group */
        $group = Group::load($_id);

        $this->addToGroup($group);
    }

    /**
     * @param int $_id
     */
    public function removeFromGroupByID(int $_id) {
        /** @var Group $group */
        $group = Group::load($_id);

        $this->removeFromGroup($group);
    }
}
