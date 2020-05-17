<?php
namespace Drupal\dpc_user_management;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\group\Entity\Group;

class GroupEntity extends Group
{
    /**
     * @param EntityStorageInterface $storage
     *
     * @throws \Exception
     */
    public function postSave(EntityStorageInterface $storage, $update = TRUE)
    {
        parent::postSave($storage, $update);

        if ($this->getGroupType()->id() == UserEntity::$group_type_email_domain_id) {

            $queue = \Drupal::queue('group_membership_update_task');
            $queue->createItem(['group' => $this->id()]);
        }
    }
}