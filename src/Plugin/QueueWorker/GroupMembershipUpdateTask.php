<?php

namespace Drupal\dpc_user_management\Plugin\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dpc_user_management\Traits\HandlesEmailDomainGroupMembership;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;

/**
 * Processes Entity Update Tasks for My Module.
 *
 * @QueueWorker(
 *   id = "group_membership_update_task",
 *   title = @Translation("Group membership updates after Group update"),
 *   cron = {"time" = 60}
 * )
 */
class GroupMembershipUpdateTask extends QueueWorkerBase {
    use HandlesEmailDomainGroupMembership;

    /**
     * Works on a single queue item.
     *
     * @param mixed $group
     *   The data that was passed to
     *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
     *
     * @throws \Drupal\Core\Queue\RequeueException
     *   Processing is not yet finished. This will allow another process to claim
     *   the item immediately.
     * @throws \Exception
     *   A QueueWorker plugin may throw an exception to indicate there was a
     *   problem. The cron process will log the exception, and leave the item in
     *   the queue to be processed again later.
     * @throws \Drupal\Core\Queue\SuspendQueueException
     *   More specifically, a SuspendQueueException should be thrown when a
     *   QueueWorker plugin is aware that the problem will affect all subsequent
     *   workers of its queue. For example, a callback that makes HTTP requests
     *   may find that the remote server is not responding. The cron process will
     *   behave as with a normal Exception, and in addition will not attempt to
     *   process further items from the current item's queue during the current
     *   cron run.
     *
     * @see \Drupal\Core\Cron::processQueues()
     */
    public function processItem($data)
    {
        /** @var Group $group */
        $group_id = $data['group'];
        $group =  Group::load($group_id);

        // check if existing users can be added to the group
        $query = \Drupal::entityQuery('user');

        $domains = $group->get('field_email_domain')->getValue();

        $field_email_orgroup = $query->orConditionGroup();
        $mail_orgroup = $query->orConditionGroup();
        $base_orgroup = $query->orConditionGroup();

        foreach ($domains as $domain) {
            $field_email_orgroup->condition('field_email_addresses', '%' . $domain['value'], 'like');
            $mail_orgroup->condition('mail', '%' . $domain['value'], 'like');
        }

        $base_orgroup->condition($field_email_orgroup);
        $base_orgroup->condition($mail_orgroup);
        $query->condition($base_orgroup);

        $uids = $query->execute();

        $users = User::loadMultiple($uids);
        $domains = array_column($domains, 'value');

        foreach ($users as $user) {
            if ($group->getMember($user)) {
                continue;
            }

            $addresses = $user->get('field_email_addresses')->getValue();
            if (empty($addresses)) {
                self::addUserToGroup($user, $group);

                continue;
            }
            foreach ($addresses as $key => $email) {
                if (in_array(explode('@', $email['value'])[1], $domains) && $email['status'] === 'verified') {
                    self::addUserToGroup($user, $group);
                }
            }
        }
    }
}
