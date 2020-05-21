<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dpc_user_management\Plugin\QueueWorker\GroupMembershipUpdateTask;
use Drupal\dpc_user_management\GroupEntity as Group;

class GroupEntityController extends ControllerBase
{
    /**
     * Runs the group_membership_update_task queue
     */
    public function runGroupMembershipUpdateTask() {
        $_queue_name = 'group_membership_update_task';
        $_queue = \Drupal::queue($_queue_name, true);
        /** @var GroupMembershipUpdateTask $_queue_worker */
        $_queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance($_queue_name);

        while($_queue->numberOfItems()){
            $item = $_queue->claimItem();
            try{
                $_queue_worker->processItem($item->data);
            } catch (\Exception $e) {
                $_queue->releaseItem($item);
            }
        }
    }
}
