<?php
namespace Drupal\dpc_user_management;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\dpc_user_management\Controller\GroupEntityController;
use Drupal\group\Entity\Group;

class GroupEntity extends Group
{

//    public function flagDirtyDomains() {
//
//    }
//
//    public function preSave(EntityStorageInterface $storage)
//    {
//        parent::preSave($storage);
//
//        if ($this->getGroupType()->id() == UserEntity::$group_type_email_domain_id) {
//            $this->flagDomainsForDeletion();
//        }
//    }

    /**
     * @param EntityStorageInterface $storage
     *
     * @throws \Exception
     */
    public function postSave(EntityStorageInterface $storage, $update = TRUE)
    {
        parent::postSave($storage, $update);

        if ($this->getGroupType()->id() == UserEntity::$group_type_email_domain_id) {
            $this->processGroupMemberships();
        }
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->get('label')->getValue()[0]['value'];
    }

    /**
     * @return array
     */
    public function domains() {
        return array_map(function ($x) {
            return $x['value'];
        }, $this->get('field_email_domain')->getValue());
    }

    /**
     * @throws \Exception
     */
    public function processGroupMemberships()
    {
        $this->discoverMembers();
    }

    /**
     * Discover Members who have emails belonging to the group's domain list
     *
     * @return EntityInterface[]|UserEntity[]
     */
    public function discoverMembers() {
        $query = \Drupal::entityQuery('user');

        $field_email_orgroup = $query->orConditionGroup();
        $mail_orgroup = $query->orConditionGroup();
        $base_orgroup = $query->orConditionGroup();

        foreach ($this->domains() as $domain) {
            $field_email_orgroup->condition(
                $query->andConditionGroup()
                    ->condition('field_email_addresses.value', '%' . $domain, 'like')
                    ->condition('field_email_addresses.status', 'verified')
            );
            $mail_orgroup->condition('mail', '%' . $domain, 'like');
        }

        $base_orgroup->condition($field_email_orgroup);
        $base_orgroup->condition($mail_orgroup);
        $query->condition($base_orgroup);

        $uids = $query->execute();

        return \Drupal\user\Entity\User::loadMultiple($uids);
    }

    /**
     * @param $users UserEntity[]
     * @throws \Exception
     */
    protected function addMembers($users) {
        foreach ($users as $user) {
            if ($this->getMember($user)) {
                continue;
            }

            $user->addToGroup($this);
        }
    }

    protected function removeExistingMembers() {

    }

}
