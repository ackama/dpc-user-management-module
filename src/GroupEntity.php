<?php
namespace Drupal\dpc_user_management;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\dpc_user_management\Controller\GroupEntityController;
use Drupal\group\Entity\Group;

class GroupEntity extends Group
{

    /**
     * Saves removed domains in state key in order to process them postSave
     * It does this by looking at the dirty field and the new one pre save.
     * This pushes the domain values into an array of domains that is not cleaned
     * until the actual reprocessing of user memberships. Long story short,
     * getting these from guessing from DB data looks expensive.
     *
     * The result is stored in the State store.
     * IMPORTANT: values need to be rechecked before removing before doing anything
     */
    public function rememberDomainsPreSave() {

        // Gets original field data
        $original = array_map(function($x){
            return $x['value'];
        },$this->original->get('field_email_domain')->getValue());

        // Gets current object data
        $clean = array_map(function($x){
            return $x['value'];
        },$this->get('field_email_domain')->getValue());

        // Gets domains to be removed
        $removed = array_diff($original, $clean);

        if(!count($removed)) {
            return;
        }

        $domains = \Drupal::state()->get('dpc_group_domains_remove',[]);
        \Drupal::state()->set('dpc_group_domains_remove', array_merge($domains, $removed));
    }

    public function getDomainsToBeRemoved() {
        // Get domains from State API
        $doomed = \Drupal::state()->get('dpc_group_domains_remove',[]);

        // Return early if this is empty
        if(empty($doomed)) {
            return [];
        }

        return array_diff($doomed,$this->domains());
    }

    public function preSave(EntityStorageInterface $storage)
    {
        parent::preSave($storage);

        if ($this->getGroupType()->id() == UserEntity::$group_type_email_domain_id) {
            $this->rememberDomainsPreSave();
        }
    }

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

    /**
     * @return bool|EntityInterface[]|mixed|void
     */
    protected function discoverRemovableMembers () {

        // Nothing to do here.
        if(empty($this->getDomainsToBeRemoved())){
            return;
        }

        $query = \Drupal::entityQuery('user');

    }

    protected function removeExistingMembers() {

    }

}
