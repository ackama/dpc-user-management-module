<?php

namespace Drupal\dpc_user_management\Traits;

use Drupal;
use Drupal\dpc_user_management\Controller\EventsLogController;
use Drupal\dpc_user_management\GroupEntity;
use Drupal\dpc_user_management\UserEntity as User;

trait MembershipMailTrait {

    /**
     * @param $id
     * @return string
     */
    static function getGroupLabel($id) {
        $group = GroupEntity::load($id);

        if(!is_null($group)) {
            return $group->label();
        }

        $group = (new EventsLogController())->getDeletedGroup($id);

        return $group->label . ' (Deleted)';
    }

    /**
     * @param User $user
     * @param int[] $group_ids
     */
    static function sendEmail($user, $group_ids)
    {
        $config = \Drupal::config('system.site');
        $site_name = $config->get('name');

        $group_names = array_map(function($gid) {
            /** @var int $gid */
            return self::getGroupLabel($gid);
        }, $group_ids);

        $user_emails = $user->getActiveEmails();

        // Build message
        $message = "Changes were made to you account which has affected your content access membership. \n\r";
        $message .= sprintf(
            "You have been removed from the following %s: %s",
            (count($group_names) > 1 ? 'groups' : 'group'),
            implode(', ', $group_names)
        );
        $params['context']['subject'] = "$site_name: You have been removed from a group" ;
        $params['context']['message'] = $message;

        /** @var Drupal\Core\Mail\MailManager $mailManager */
        $mailManager = \Drupal::service('plugin.manager.mail');
        foreach ($user_emails as $email) {
            $mailManager->mail('system', 'mail', $email, $user->getPreferredLangcode(), $params);
        }
    }
}
