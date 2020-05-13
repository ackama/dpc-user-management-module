<?php

namespace Drupal\dpc_user_management\Traits;

use Drupal\group\Entity\Group;

/**
 * Class SpecialGroupsMembershipHandler
 * @package Drupal\dpc_user_management\Handlers
 */
trait HandleMembershipTrait
{
    /**
     * @param Group $group
     * @return bool
     */
    public function inGroup(Group $group)
    {
        return !!$group->getMember($this);
    }

    /**
     * @param Group           $group
     */
    public function addToGroup(Group $group)
    {
        if (!$this->inGroup($group)) {
            // @ToDo Log Event for Addition
            // dpc_log_event('added', $group->id(), $user->id());
            $group->addMember($this);
        }
    }

    /**
     * @param Group            $group
     */
    public function removeFromGroup(Group $group)
    {
        if($this->inGroup($group)) {
            // @ToDo Log Event for Removal
            // dpc_log_event('removed', $group->id(), $this->id());
            $group->removeMember($this);
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
