<?php

namespace Drupal\dpc_user_management;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\dpc_user_management\Traits\HandleMembershipTrait;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;
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
     * @var bool
     */
    private $manualRemoval = false;

    /**
     * @param EntityStorageInterface $storage
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
        $this->processSpecialGroupsOnSave();
        $this->processManualRemoval();

        $this->synchronizeMemberships();

        parent::preSave($storage);
    }

    /**
     * Verifies Email Addresses
     *
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
     */
    protected function verify_email_addresses()
    {
        $verification_sent = [];
        $user = User::load($this->id());
        // Check email addresses
        $addresses = $this->getDirtyAddresses();
        foreach ($addresses as $key => $address) {
            // If there is no status assume this is new, send a verification email
            if (empty($address['status']) || $address['status'] === 'new') {
                $token      = Crypt::randomBytesBase64(55);
                $email      = $address['value'];
                $this->sendVerificationNotification($email, $token, $user);
                $addresses[$key]['status']             = 'pending';
                $addresses[$key]['verification_token'] = $token;
                $verification_sent[] = $email;
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

        $this->field_email_addresses->setValue($addresses);
        if (!empty($verification_sent)) {
            \Drupal::messenger()->addMessage(t('A verification email was sent to ' . implode(',', $verification_sent)));
        }
    }

    protected function accessGroup() {
        // Toggles user access to content group
        $group_ids =  \Drupal::entityQuery('group')
            ->condition('label', UserEntity::$group_label)
            ->accessCheck(false)
            ->execute();

        /** @var Group $group */
        $group = Group::load(array_pop($group_ids));

        return $group;
    }

    /**
     * Because drupal can't handle adding default values to old records (ie existing users)
     * when creating these fields, we need to check for unset values first
     * We use this helper function to keep things DRY
     *
     * @param string $field_name
     * @param bool $original
     * @return bool
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function _get_clean_boolean($field_name, $original = false)
    {
        $value = !$original ? $this->get($field_name)->getValue() : $this->original->get($field_name)->getValue();

        return empty($value) ? false : (bool) $value[0]['value'];
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
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    protected function processSpecialGroupsOnSave()
    {
        // Synchronise Special Groups memberships with checkboxes
        $_new = $this->_get_target_ids('special_groups');
        $_original = $this->_get_target_ids('special_groups', true);

        if ( $_original == $_new ) {
            return;
        }

        // Settings changed => Reprocess memberships

        array_map(function($_id) {
            $this->removeFromGroupByID($_id);
        }, array_diff($_original, $_new));

        array_map(function($_id) {
            $this->addToGroupByID($_id);
        }, array_diff($_new, $_original));
    }

    /**
     * @return mixed
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    private function getDirtyAddresses()
    {
        $user = \Drupal::entityManager()
            ->getStorage('user')
            ->loadUnchanged($this->id());
        $addresses = $user->field_email_addresses->getValue();
        $new_addresses = $this->field_email_addresses->getValue();
        foreach ($new_addresses as $key => $address) {
            if (array_search($address['value'], array_column($addresses, 'value')) === false) {
                $new_addresses[$key]['status'] = 'new';
            };
        }

        return $new_addresses;
    }

    /**
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    private function processManualRemoval() {
        $_access_new = $this->_get_clean_boolean('jse_access');

        // Prepare for removal from access group and set flag
        if($_access_new) {
            $this->set('jse_access', false);
            $this->manualRemoval = true;
        }
    }

    /**
     * @param string $type
     * @return \Drupal\group\Entity\Group[]|\Drupal\Core\Entity\EntityInterface[]
     */
    static function getGroupsByType(string $type)
    {
        $group_ids = \Drupal::entityQuery('group')
            ->condition('type', $type)
            ->accessCheck(false)
            ->execute();

        return Group::loadMultiple($group_ids);
    }


    /**
     * @return bool
     */
    protected function inSpecialGroups() {
        foreach(self::getGroupsByType(UserEntity::$group_special_type_id) as $group) {
            if ($this->inGroup($group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function inEmailGroups() {
        foreach(self::getGroupsByType(UserEntity::$group_type_email_domain_id) as $group) {
            if ($this->inGroup($group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * When all group memberships have been processed,
     * decide if the user should be in the Access Group
     *
     * @throws \Drupal\Core\TypedData\Exception\MissingDataException
     */
    private function synchronizeMemberships() {

        $hasAccess = false;

        if ($this->inSpecialGroups() && !$this->manualRemoval) {
            $hasAccess = true;
        }

        if ($this->inEmailGroups()) {
            $hasAccess = true;
        }

        if( $hasAccess ) {
            $this->accessGroup()->addMember($this);

            return;
        }

        $this->accessGroup()->removeMember($this);

        return;

    }
}
