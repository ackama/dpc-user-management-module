<?php
namespace Drupal\dpc_user_management;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\dpc_user_management\Controller\GroupEntityController;
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
            (new GroupEntityController())->processGroupMemberships(['group' =>  $this->id()]);
        }
    }

    public function getName()
    {
        return $this->get('label')->getValue()[0]['value'];
    }
}
