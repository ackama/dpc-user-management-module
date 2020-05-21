<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dpc_user_management\Plugin\QueueWorker\GroupMembershipUpdateTask;
use Drupal\group\Entity\Group;

class GroupEntityController extends ControllerBase
{
    /**
     * @param $data
     * @throws \Exception
     */
    public function processGroupMemberships($data)
    {
        /** @var Group $group */
        $group_id = $data['group'];
        $group =  Group::load($group_id);

        // check if existing users can be added to the group
        $query = \Drupal::entityQuery('user');

        $domains = $group->field_email_domain->getValue();

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

        $users = \Drupal\user\Entity\User::loadMultiple($uids);
        $domains = array_column($domains, 'value');

        foreach ($users as $user) {
            if ($group->getMember($user)) {
                continue;
            }

            $addresses = $user->field_email_addresses->getValue();
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
