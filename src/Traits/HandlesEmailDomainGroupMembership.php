<?php

namespace Drupal\dpc_user_management\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dpc_user_management\GroupEntity as Group;
use Drupal\dpc_user_management\UserEntity;
use Drupal\user\UserInterface;

trait HandlesEmailDomainGroupMembership
{
    /**
     * @param EntityInterface $user
     * @param string $email
     * @throws \Exception
     */
    static function addUserToGroups(EntityInterface $user, $email)
    {
        $groups = self::getEmailDomainGroups();

        /** @var Group $group */
        foreach ($groups as $group) {
            if (self::userHasGroupEmailDomains([$email], $group)) {
                if ($group->getMember($user)) {
                    continue;
                }

                self::addUserToGroup($user, $group);
            }
        }
    }

    /**
     * @param AccountInterface $user
     * @return bool
     */
    static function isUserInGroups(AccountInterface $user)
    {
        $groups = self::getEmailDomainGroups();

        // Test if current user is part of valid email groups
        /** @var Group $group */
        foreach (self::getEmailDomainGroups() as $group) {
            if ($group->getMember($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param EntityInterface $user
     * @param Group           $group
     *
     * @throws \Exception
     */
    static function addUserToGroup(EntityInterface $user, Group $group) {
        dpc_log_event('added', $group->id(), $user->id());
        $group->addMember($user);
    }

    /**
     * @param UserInterface $user
     * @param array         $removed_emails
     *
     *@throws \Exception
    */
    static function removeUsersFromGroups(UserInterface $user, $removed_emails)
    {
        // get all the verified users emails
        $user_emails = array_filter($user->get('field_email_addresses')->getValue(), function($email) {
            return isset($email['status']) && $email['status'] == 'verified';
        });

        $user_emails = array_column($user_emails, 'value');
        // filter the emails that are being removed
        $user_emails = array_diff($user_emails, $removed_emails);

        $groups = self::getEmailDomainGroups();
        $groups_removed_from = [];
        /** @var Group $group */
        foreach ($groups as $group) {
            // check if any of the users other emails would allow them stay in the group
            if (self::userHasGroupEmailDomains($user_emails, $group)) {
                continue;
            };

            // check if the group domains match the removed email domains
            if (self::userHasGroupEmailDomains($removed_emails, $group) && $group->getMember($user)) {
                // remove the user from the group
                $group->removeMember($user);
                array_push($groups_removed_from, $group->get('label')->getValue()[0]['value']);
                dpc_log_event('removed', $group->id(), $user->id());
            };
        }
    }

    /**
     * @return \Drupal\Core\Entity\EntityInterface[]
     */
    static function getEmailDomainGroups()
    {
        $group_ids = \Drupal::entityQuery('group')
            ->condition('type', UserEntity::$group_type_email_domain_id)
            ->accessCheck(false)
            ->execute();

        return Group::loadMultiple($group_ids);
    }

    /**
     * @param array $user_emails
     * @param Group $group
     *
     * @return bool
     */
    static function userHasGroupEmailDomains(array $user_emails, Group $group)
    {
        $user_email_domains = array_map(function ($email) {
            return explode('@', strtolower($email))[1];
        }, $user_emails);
        $group_emails       = array_column($group->get('field_email_domain')->getValue(), 'value');

        return count(array_intersect($user_email_domains, $group_emails)) > 0;
    }
}
