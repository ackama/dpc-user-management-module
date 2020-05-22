<?php

namespace Drupal\dpc_user_management\Traits;

use Drupal\dpc_user_management\GroupEntity as Group;

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
     * @param Group $group
     * @throws \Exception
     */
    public function addToGroup(Group $group)
    {
        if (!$this->inGroup($group)) {
            $group->addMember($this);
            dpc_log_event('added', $group->id(), $this->id());
        }
    }

    /**
     * @param Group $group
     * @throws \Exception
     */
    public function removeFromGroup(Group $group)
    {
        if($this->inGroup($group)) {
            $group->removeMember($this);
            dpc_log_event('removed', $group->id(), $this->id());
        }
    }

    /**
     * @param int $_id
     * @throws \Exception
     */
    public function addToGroupByID(int $_id) {
        /** @var Group $group */
        $group = Group::load($_id);

        $this->addToGroup($group);
    }

    /**
     * @param int $_id
     * @throws \Exception
     */
    public function removeFromGroupByID(int $_id) {
        /** @var Group $group */
        $group = Group::load($_id);

        $this->removeFromGroup($group);
    }
}
