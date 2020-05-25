<?php

namespace Drupal\dpc_user_management\Plugin\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dpc_user_management\UserEntity as User;
use Drupal\dpc_user_management\Traits\HandleMembershipTrait;
use Drupal\dpc_user_management\Traits\MembershipMailTrait;

/**
 * Processes
 *
 * @QueueWorker(
 *   id = "notify_user_task",
 *   title = @Translation("Process Group Logs in order to trigger reports"),
 *   cron = {"time" = 60}
 * )
 */
class NotifyUserTask extends QueueWorkerBase {

    use MembershipMailTrait;
    use HandleMembershipTrait;

    /**
     * @param $data
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
        if (empty($data['removed'])) {
            return;
        }

        /** @var User $user */
        $user = User::load($data['user_id']);

        /** @var array */
        $groups_ids = $data['removed'];

        self::sendEmail($user, $groups_ids);
        // @ToDo Hook sending sms notifications when available

    }
}
