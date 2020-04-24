<?php

namespace Drupal\dpc_user_management\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\Group;
use Drupal\user\UserInterface;

trait HandlesEmailDomainGroupMembership
{
    /**
     * @param EntityInterface $user
     * @param string          $email
     */
    static function addUserToGroups(EntityInterface $user, $email)
    {
        $domain = explode('@', $email)[1];
        $groups = self::getEmailDomainGroups();

        /** @var Group $group */
        foreach ($groups as $group) {
            foreach ($group->field_email_domain->getValue() as $group_domain) {
                if (trim(strtolower($domain)) === trim(strtolower($group_domain['value']))) {
                    $group->addMember($user);
                }
            }
        }
    }

    /**
     * @param UserInterface $user
     * @param string        $email
     */
    static function removeUsersFromGroups(UserInterface $user, $emails)
    {
        $user_emails = array_column($user->field_email_addresses->getValue(), 'value');
        $user_emails = array_diff($user_emails, $emails);

        // $domain = explode('@', $email)[1];
        // tODO what if the user has amny whitelisted emails
        $groups = self::getEmailDomainGroups();


        /** @var Group $group */
        foreach ($groups as $group) {
            // check if any of the users other emails would let them stay in the group
            self::userHasGroupEmailDomains($user_emails, $group);
            foreach ($group->field_email_domain->getValue() as $group_domain) {
                if (trim(strtolower($domain)) === trim(strtolower($group_domain['value']))) {
                    $group->removeMember($user);
                }
            }
        }
    }

    /**
     * @return \Drupal\Core\Entity\EntityInterface[]
     */
    static function getEmailDomainGroups()
    {
        $groups = \Drupal::entityQuery('group')
            ->condition('type', 'email_domain_group')
            ->accessCheck(false)
            ->execute();

        return Group::loadMultiple($groups);
    }

    /**
     * @param array $user_emails
     * @param Group $group
     */
    static function userHasGroupEmailDomains(array $user_emails, Group $group)
    {
        $group_emails = array_column($group->field_email_domain->getValue(), 'value');
    }
}