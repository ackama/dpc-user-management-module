<?php

namespace Drupal\dpc_user_management;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\dpc_user_management\Controller\EventsLogController;
use Drupal\dpc_user_management\Traits\HandleMembershipTrait;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;
use Drupal\dpc_user_management\GroupEntity;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;
use Drupal\dpc_user_management\Traits\SendsEmailVerificationEmail;

class UserEntity extends User
{
    use SendsEmailVerificationEmail, HandlesEmailDomainGroupMembership;
    use HandleMembershipTrait;

    /**
     * Defines the Group Id
     *
     * @var string
     */
    public static $group_id = 'dpc_group_grant_access';

    /**
     * Defines the Group Label
     *
     * @var string
     */
    public static $group_label = 'DPC Managed - Grant Access Group';

    /**
     * Defines the Group Type ID
     *
     * @var string
     */
    public static $group_type_id = 'dpc_gtype_grant_access';

    /**
     * Defines the Group Type Label
     *
     * @var string
     */
    public static $group_type_label = 'DPC Managed - Grant Access Group Type';

    /**
     * Special Group Type ID.
     * Initialised in ./config/install/group.type.dpc_gtype_special.yml
     *
     * @var string
     */
    public static $group_special_type_id = 'dpc_gtype_special';

    /**
     * Special Group Type Label.
     * Initialised in ./config/install/group.type.dpc_gtype_special.yml
     *
     * @var string
     */
    public static $group_special_type_label = 'DPC Managed - Special Groups';

    /**
     * Defines the Email Domain based group type ID
     *
     * @var string
     */
    public static $group_type_email_domain_id = 'dpc_gtype_email_domain';

    /**
     * Defines the Email Domain based group type label
     *
     * @var string
     */
    public static $group_type_email_domain_label = 'DPC Managed - Email Domain Groups';

    /**
     * @param EntityStorageInterface $storage
     *
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     * @throws \Exception
     */
    public function preSave(EntityStorageInterface $storage)
    {
        if ($this->isNew()) {
            return parent::preSave($storage);
        }

        $this->verify_email_addresses();

        parent::preSave($storage);
    }

    /**
     * @param EntityStorageInterface $storage
     * @param bool $update
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     */
    public function postSave(EntityStorageInterface $storage, $update = TRUE)
    {
        parent::postSave($storage, $update); // TODO: Change the autogenerated stub

        $this->processSpecialGroupsOnSave($update);
        $this->synchronizeMemberships($update);
    }

    public static function preDelete(EntityStorageInterface $storage, array $entities)
    {
        $EventLogsController = new EventsLogController();
        foreach($entities as $user) {
            $EventLogsController->deleteRecordsForUser($user);
        }

        parent::preDelete($storage, $entities);
    }

    /**
     * Verifies Email Addresses
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     */
    public function verify_email_addresses()
    {
        $verification_sent = [];
        $user              = User::load($this->id());
        // Check email addresses
        $addresses = $this->getDirtyAddresses();
        foreach ($addresses as $key => $address) {
            // If there is no status assume this is new, send a verification email
            if (empty($address['status']) || $address['status'] === 'new') {
                $token = Crypt::randomBytesBase64(55);
                $email = $address['value'];
                $this->sendVerificationNotification($email, $token, $user);
                $addresses[$key]['status']             = 'pending';
                $addresses[$key]['verification_token'] = $token;
                $verification_sent[]                   = $email;
            }
            // If a primary email flag has been set then override the mail setting
            if ($address['is_primary']) {
                $this->setEmail($address['value']);
            }

            // If a users email is unverified maybe remove the user from groups
            if ($address['status'] == 'unverified') {
                self::removeUsersFromGroups($this, [$address['value']]);
            }
        }

        $this->get('field_email_addresses')->setValue($addresses);
        if (!empty($verification_sent)) {
            \Drupal::messenger()->addMessage(t('A verification email was sent to ' . implode(',', $verification_sent)));
        }
    }

    /**
     * Returns Main Access Group
     *
     * @return \Drupal\dpc_user_management\GroupEntity
     */
    public function accessGroup() {
        // Toggles user access to content group
        $group_ids =  \Drupal::entityQuery('group')
            ->condition('label', UserEntity::$group_label)
            ->accessCheck(false)
            ->execute();

        /** @var GroupEntity $group */
        $group = GroupEntity::load(array_pop($group_ids));

        return $group;
    }

    /**
     * Is the user in the master 'Access' group
     *
     * @return bool
     */
    public function hasGroupContentAccess() {
        return $this->accessGroup()->getMember($this) ? true : false;
    }

    /**
     * Because drupal can't handle adding default values to old records (ie existing users)
     * when creating these fields, we need to check for unset values first
     * We use this helper function to keep things DRY
     *
     * @param string $field_name
     * @param bool   $original
     *
     * @return bool
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function _get_clean_boolean($field_name, $original = false)
    {
        $value = !$original ? $this->get($field_name)->getValue() : $this->original->get($field_name)->getValue();

        return empty($value) ? false : (bool)$value[0]['value'];
    }

    /**
     * Returns arrays with id's from multiple checkboxes field type in drupal forms
     *
     * @param $field_name
     * @param bool $original
     * @return array
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function _get_target_ids($field_name, $original = false)
    {
        $values = !$original ? $this->get($field_name)->getValue() : $this->original->get($field_name)->getValue();

        return array_map(function ($v) {
            return $v['target_id'];
        }, $values);
    }

    /**
     * Adds or Removes membership of users into access group bases on profile checkboxes
     *
     * @param $update
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function processSpecialGroupsOnSave($update)
    {
        if(!$update) {
            return;
        }

        $_in_groups = $this->_get_target_ids('special_groups');

        /** @var $safe_groups Group[] */
        $safe_groups = array_filter(
            self::getGroupsByType(self::$group_special_type_id),
            function (GroupEntity $g) {
                if (in_array($g->id(), $this->_get_target_ids('special_groups'))) {
                    $this->addToGroup($g);

                    return true;
                }

                $this->removeFromGroup($g);

                return false;
            }
        );
    }

    /**
     * @return mixed
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    private function getDirtyAddresses()
    {
        $user          = \Drupal::entityManager()
            ->getStorage('user')
            ->loadUnchanged($this->id());
        $addresses = $user->get('field_email_addresses')->getValue();
        $new_addresses = $this->get('field_email_addresses')->getValue();
        foreach ($new_addresses as $key => $address) {
            if (array_search($address['value'], array_column($addresses, 'value')) === false
                && $user->getEmail() !== $address['value']) {
                $new_addresses[$key]['status'] = 'new';
            };
        }

        return $new_addresses;
    }

    /**
     * Checks if user already has that email in their information
     *
     * @param $email
     * @return bool
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function emailExists($email) {
        return in_array($email, array_map(function ($x) {
            return $x['value'];
        }, $this->get('field_email_addresses')->getValue()));
    }

    /**
     * Adds email address to user object
     *
     * @param $email
     * @return int|null
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function addEmailAddress($email) {
        if ($this->emailExists($email)) {
            return null;
        }

        $addresses = $this->get('field_email_addresses')->getValue();
        $addresses[] = [
            'value' => $email,
            'is_primary' => 0,
            'status' => 'new'
        ];
        $this->set('field_email_addresses', $addresses);

        return true;
    }

    /**
     * Remove Email Address form User
     *
     * @param $email
     * @return int|null
     * @throws \Drupal\Core\Entity\EntityStorageException
     * @throws \Exception
     */
    public function removeEmailAddress($email) {
        if (!$this->emailExists($email)) {
            return null;
        }

        $addresses = $this->get('field_email_addresses')->getValue();
        $addresses = array_filter($addresses, function($email_address) use ($email) {
            return $email != $email_address['value'];
        });
        $this->set('field_email_addresses', $addresses);

        self::removeUsersFromGroups($this, [$email]);

        return true;
    }

    /**
     * @param $email
     * @return bool|null
     * @throws \Drupal\Core\Entity\EntityStorageException
     * @throws \Exception
     */
    public function makeEmailVerified($email) {
        if (!$this->emailExists($email)) {
            return null;
        }

        $addresses = $this->get('field_email_addresses')->getValue();
        $addresses = array_map(function($email_address) use ($email) {
            if($email_address['value'] == $email) {
                $email_address['status'] = 'verified';
                $email_address['verification_token'] = null;
            }

            return $email_address;
        }, $addresses);
        $this->set('field_email_addresses', $addresses);

        self::addUserToGroups($this, $email);

        return true;
    }

    /**
     * @param $email
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function addEmailAndVerify($email) {
        $this->addEmailAddress($email);
        $this->save();

        $this->makeEmailVerified($email);
        $this->save();
    }

    /**
     * @param $email
     * @return |null
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function makeEmailPrimary($email) {
        if (!$this->emailExists($email)) {
            return null;
        }

        $addresses = $this->get('field_email_addresses')->getValue();
        $addresses = array_map(function($email_address) use ($email) {
            if($email_address['value'] == $email) {
                $email_address['is_primary'] = 1;

                return $email_address;
            }

            $email_address['is_primary'] = 0;

            return $email_address;
        }, $addresses);

        $this->set('field_email_addresses', $addresses);

        return true;
    }

    /**
     * Returns true if user is part of the Master Access Group.
     *
     * @return bool
     */
    public function inAccessGroup() {
        foreach(self::getGroupsByType(UserEntity::$group_type_id) as $group) {
            if ($this->inGroup($group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if user is part of Special Groups
     *
     * @return bool
     */
    public function inSpecialGroups() {
        foreach(self::getGroupsByType(UserEntity::$group_special_type_id) as $group) {
            if ($this->inGroup($group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if user is part of the Email Domain Groups
     *
     * @return bool
     */
    public function inEmailGroups() {
        foreach(self::getGroupsByType(UserEntity::$group_type_email_domain_id) as $group) {
            if ($this->inGroup($group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if user has been granted access via manual override
     *
     * @return bool
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    public function isOverrideAccessTrue() {
        return $this->_get_clean_boolean('jse_access');
    }

    /**
     * When all group memberships have been processed,
     * decide if the user should be in the Access Group
     *
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     * @throws \Exception
     */
    private function synchronizeMemberships($update) {

        // Keep record of access grant
        $grantAccess = false;

        // If User is in special groups, possibly grant Access
        if ($this->inSpecialGroups()) {
            $grantAccess = true;
        }

        // If user is part of the allowed email groups, grant access
        if ($this->inEmailGroups()) {
            $grantAccess = true;
        }

        if ($this->isOverrideAccessTrue()) {
            $grantAccess = true;
        }

        // Provide access after all calculations have been done
        if( $grantAccess ) {
            $this->addToGroup($this->accessGroup());

            return;
        }

        $this->removeFromGroup($this->accessGroup());

        return;

    }
}
