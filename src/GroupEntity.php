<?php
namespace Drupal\dpc_user_management;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
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
     *
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    public function rememberDomainsPreSave() {

        // Gets original field data
        $original = array_map(function($x){
            return $x['value'];
        }, $this->original->get('field_email_domain')->getValue());

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

    public function clearDomainRemovalMemory() {
        \Drupal::state()->delete('dpc_group_domains_remove');
    }

    public function preSave(EntityStorageInterface $storage)
    {
        if ($this->isNew()) {
            return parent::preSave($storage);
        }

        if ($this->getGroupType()->id() == UserEntity::$group_type_email_domain_id) {
            $this->rememberDomainsPreSave();
        }

        parent::preSave($storage);
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
        // Discover potential members and adds them to Group
        $newMembers = $this->discoverMembers();
        $this->addMembers($newMembers);

        // Discovers removable members and removes them from Group
        $discoverRemoveableMembers = $this->discoverRemovableMembers();
        $this->removeExistingMembers($discoverRemoveableMembers);

        // Clears domain removal memory from State API
        $this->clearDomainRemovalMemory();
    }

    /**
     * Discover Members who have emails belonging to the group's domain list
     *
     * @return array
     */
    public function discoverMembers() {
        $query = \Drupal::entityQuery('user');

        $emails_to_be_kept = $query->orConditionGroup();
        $emails_to_be_kept_main = $query->orConditionGroup();

        foreach ($this->domains() as $domain) {
            $emails_to_be_kept->condition(
                $query->andConditionGroup()
                    ->condition('field_email_addresses.value', '%' . $domain, 'like')
                    ->condition('field_email_addresses.status', 'verified')
            );
            $emails_to_be_kept_main->condition('mail', '%' . $domain, 'like');
        }

        $users_to_be_kept = $query->andConditionGroup()
            ->condition($emails_to_be_kept)
            ->condition($emails_to_be_kept_main);

        return $query->condition($users_to_be_kept)->execute();
    }

    /**
     * @param $uids
     * @throws \Exception
     */
    protected function addMembers($uids) {
        /** @var UserEntity[] $users */
        $users = \Drupal\user\Entity\User::loadMultiple($uids);

        foreach ($users as $user) {
            if ($this->getMember($user)) {
                continue;
            }

            $user->addToGroup($this);
        }
    }

    /**
     * @return array|void
     */
    protected function discoverRemovableMembers () {

        // Nothing to do here.
        if(empty($this->getDomainsToBeRemoved())){
            return;
        }

        $query = \Drupal::entityQuery('user');

        // First we get users to be removed based on the
        // domains provided by ($this->getDomainsToBeRemoved()

        $emails_to_be_removed = $query->orConditionGroup();
        $emails_to_be_removed_main = $query->orConditionGroup();

        foreach ($this->getDomainsToBeRemoved() as $domain) {
            $emails_to_be_removed->condition(
                $query->andConditionGroup()
                    ->condition('field_email_addresses.value', '%' . $domain, 'like')
                    ->condition('field_email_addresses.status', 'verified')
            );
            $emails_to_be_removed_main->condition('mail', '%' . $domain, 'like');
        }

        $users_to_be_removed = $query->andConditionGroup()
            ->condition($emails_to_be_removed)
            ->condition($emails_to_be_removed_main);

        $users_to_be_removed_uids = $query->condition($users_to_be_removed)->execute();

        // Early exit if we didn't find any users with these domains
        if(empty($users_to_be_removed_uids)) {
            return;
        }

        // Then if we haven't existed early, we find users that are entitled
        // to stay in the group based on the domains provided by $this->discoverMembers()

        $users_to_be_kept_uids = $this->discoverMembers();

        if(!empty($users_to_be_kept_uids)){
            $users_to_be_removed_uids = array_diff($users_to_be_removed_uids, $users_to_be_kept_uids);
        }

        return $users_to_be_removed_uids;
    }

    /**
     * Removes users from groups where domains are no longer valid
     * @param $users_to_be_removed_uids
     */
    protected function removeExistingMembers($users_to_be_removed_uids) {
        // Early exit
        if(empty($users_to_be_removed_uids)){
            return;
        }

        foreach ($users_to_be_removed_uids as $uid) {
            $user = UserEntity::load($uid);
            $user->removeFromGroup($this);
        }
    }
}
