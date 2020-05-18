<?php

namespace Drupal\dpc_user_management\Traits;

use Drupal;
use Drupal\dpc_user_management\UserEntity as User;
use Drupal\dpc_user_management\GroupEntity as Group;

trait MembershipMailTrait {
    /**
     * @param User    $user
     * @param Group[] $groups
     */
    static function sendEmail($user, $groups)
    {
        $config = \Drupal::config('system.site');
        $site_name = $config->get('name');

        $group_names = array_map(function($g) {
            /** @var Group $g */
            return $g->label();
        }, $groups);

        $user_emails = $user->get('field_email_addresses');

        // Build message
        $message = "Changes were made to you account which has affected your content access membership. \n\r";
        $message .= sprintf(
            "You have been removed from the following %s: %s",
            (count($group_names) > 1 ? 'groups' : 'group'),
            implode(', ', $groups)
        );
        $params['context']['subject'] = "$site_name: You have been removed from a group" ;
        $params['context']['message'] = $message;

        $mailManager = \Drupal::service('plugin.manager.mail');
        foreach ($user_emails as $email) {
            $mailManager->mail('system', 'mail', $email, $user->getPreferredLangcode(), $params);
        }
    }
}
