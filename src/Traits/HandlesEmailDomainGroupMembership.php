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
        $groups = self::getEmailDomainGroups();

        /** @var Group $group */
        foreach ($groups as $group) {
            if (self::userHasGroupEmailDomains([$email], $group)) {
                $group->addMember($user);
            }
        }
    }

    /**
     * @param UserInterface $user
     * @param array         $removed_emails
     */
    static function removeUsersFromGroups(UserInterface $user, $removed_emails)
    {
        // get all the verified users emails
        $user_emails = array_filter($user->field_email_addresses->getValue(), function($email) {
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
            };
        }

        if (!empty($groups_removed_from)) {
            self::sendNotificationUserIsRemovedFromGroup($user_emails, $groups_removed_from, $user->getPreferredLangcode());
        }
    }

    /**
     * @return \Drupal\Core\Entity\EntityInterface[]
     */
    static function getEmailDomainGroups()
    {
        $group_ids = \Drupal::entityQuery('group')
            ->condition('type', 'email_domain_group')
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
            return explode('@', $email)[1];
        }, $user_emails);
        $group_emails       = array_column($group->field_email_domain->getValue(), 'value');

        return count(array_intersect($user_email_domains, $group_emails)) > 0;
    }

    /**
     * @param array $user
     * @param Group[]|array $groups
     */
    static function sendNotificationUserIsRemovedFromGroup($user_emails, $groups, $langcode)
    {
        $config = \Drupal::config('system.site');
        $site_name = $config->get('name');

        $message = "Changes were made to you account which has affected your group membership. \n\r";
        $message .= sprintf(
            "You have been removed from the following %s: %s",
            (count($groups) > 1 ? 'groups' : 'group'),
            implode(', ', $groups)
        );
        $params['context']['subject'] = "$site_name: You have been removed from a group" ;
        $params['context']['message'] = $message;

        $mailManager = \Drupal::service('plugin.manager.mail');
        foreach ($user_emails as $email) {
            $mailManager->mail('system', 'mail', $email, $langcode, $params);
        }
    }
}