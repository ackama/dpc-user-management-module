<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Pager\PagerManager;
use Drupal\Core\Render\Markup;
use Drupal\dpc_user_management\Plugin\QueueWorker\NotifyUserTask;
use Drupal\dpc_user_management\UserEntity;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;

class EventsLogController extends ControllerBase
{
    /**
     * Table name
     *
     * @var string
     */
    private $table_name = 'dpc_group_events';

    /**
     * Table alias.
     *
     * @var string
     */
    private $t = 'dpc_group_events';

    /**
     * Queue Name
     *
     * @var string
     */
    private static $queue_name = 'notify_user_task';

    /**
     * @return \Drupal\Core\Database\Connection
     */
    private function getDB() {
        return \Drupal::database();
    }

    /**
     * Returns query object with passed SQL query
     *
     * @return \Drupal\Core\Database\Query\SelectInterface
     */
    private function query() {
        return $this->getDB()->select($this->table_name, $this->t);
    }

    /**
     * Returns all records in table
     *
     * @return mixed
     */
    private function getAllRecords() {
        return $this->query()
            ->fields($this->t)
            ->execute()
            ->fetchAll();
    }

    /**
     * Returns records that have not been processed
     *
     * @return mixed
     */
    private function getUnprocessedRecords() {
        return $this->query()
            ->fields($this->t)
            ->orderBy('uid')
            ->orderBy('gid')
            ->orderBy('created')
            ->condition('status','pending')
            ->execute()
            ->fetchAll();
    }

    /**
     * Returns Queue
     *
     * @return \Drupal\Core\Queue\QueueInterface
     */
    public function queue() {
        return \Drupal::queue(self::$queue_name, true);
    }

    /**
     * @return NotifyUserTask
     */
    public function queueWorker() {
        return \Drupal::service('plugin.manager.queue_worker')->createInstance(self::$queue_name);
    }

    /**
     * Displays all event records
     *
     * @return array
     */
    public function display()
    {
        $logs = $this->getAllRecords();
        $gids   = array_map(function ($log) {
            return $log->gid;
        }, $logs);
        $uids   = array_map(function ($log) {
            return $log->uid;
        }, $logs);
        $groups = Group::loadMultiple(array_unique($gids));
        $users  = User::loadMultiple(array_unique($uids));

        $table_items = [];
        foreach ($logs as $log) {
            $created       = DrupalDateTime::createFromTimestamp($log->created, \Drupal::currentUser()->getTimeZone())
                ->format('Y-m-d H:i:s');
            $group         = $groups[$log->gid]->getName();
            $user          = $users[$log->uid]->getDisplayName();
            $table_items[] = [
                'Date'   => $created,
                'Action' => $log->name,
                'Group'  => Markup::create("<a href='/group/$log->gid'>$group</a>"),
                'User'   => Markup::create("<a href='/user/$log->uid'>$user</a>"),
                'Status' => $log->status
            ];
        }

        $table_items = $this->_return_pager_for_array($table_items, 10);
        // Create table and pager
        $element['table'] = [
            '#theme'  => 'table',
            '#header' => [
                'Date',
                'Action',
                'Group',
                'User',
                'Status'
            ],
            '#rows'   => $table_items,
            '#empty'  => t('There is no data available.'),
        ];

        $element['pager_pager'] = ['#type' => 'pager'];

        return $element;
    }

    /**
     * Split array for pager.
     *
     * @param array   $items
     *   Items which need split
     *
     * @param integer $num_page
     *   How many items view in page
     *
     * @return array
     */
    private function _return_pager_for_array($items, $num_page)
    {
        // Get total items count
        $total = count($items);
        // Get the number of the current page
        /** @var PagerManager $pager_manager */
        $pager_manager = \Drupal::service('pager.manager');
        $pager         = $pager_manager->createPager($total, $num_page);
        $current_page  = $pager->getCurrentPage();
        // Split an array into chunks
        $chunks = array_chunk($items, $num_page);
        // Return current group item
        $current_page_items = $chunks[$current_page];

        return $current_page_items;
    }

    /**
     * Marks list of logged records as processed with a certain timestamp.
     * Receives a list of records
     *
     * @param $logs object[]
     */
    public function markLogsAsProcessed($logs) {
        $logs_ids = array_map(function($log) { return $log->id; }, $logs);
        $this->getDB()
            ->update($this->table_name)
            ->fields(['changed' => time()])
            ->condition('id', $logs_ids, 'IN')
            ->execute();
    }

    /**
     * Returns what happened to user based on the logs.
     *
     * @param $uid
     * @param $user_logs
     * @return array
     */
    public function processRecordsForUser($uid, $user_logs) {
        /** @var UserEntity $user */
        $user = User::load($uid);
        $group_states = [];

        $group_states = array_reduce($user_logs, function($c, $log) {
            $c[$log->gid] = isset($c[$log->gid])
                ? array_merge($c[$log->gid], [$log->status])
                : [];
            return $c;
        }, []);

        $responses = [];

        foreach ($group_states as $group_id => $group_data) {
            $responses[$group_id] = [
                'original' => $group_data[0] === 'added' ? 'removed' : 'added',
                'first' => $group_data[0],
                'last' => array_pop($group_data)
            ];
        }

        $added = array_filter($responses, function ($r) {
            return $r['original'] !== $r['last'] && $r['last'] === 'added';
        });

        $removed = array_filter($responses, function ($r) {
            return $r['original'] !== $r['last'] && $r['last'] === 'removed';
        });

        if(!empty($removed)) {
            return $removed;
        }

        // Return state changes
        return [
            'added'   => $added,
            'removed' => $removed
        ];
    }

    /**
     * Gets all unprocessed records, groups them by user and processes each user group.
     */
    public function processUnprocessedRecords(){
        $user_logs = array_reduce($this->getUnprocessedRecords(), function($c, $log) {
            $c[$log->uid] = isset($c[$log->uid])
                ? array_merge($c[$log->uid], [$log])
                : [];
            return $c;
        }, []);

        foreach ($user_logs as $uid => $logs) {
            $results = $this->processRecordsForUser($uid, $logs);

            if (empty($result['added']) && empty($result['removed'])) {
                // There're logs but nothing happened regarding memberships.
                // i.e. User does something and then undoes it
                // Mark logs as processed. Early Exit.
                $this->markLogsAsProcessed($logs);
                break;
            }

            // Get User
            /** @var UserEntity $user */
            $user = User::load($uid);

            // If user is not in access Group, check what happened and possibly send email
            // If there are groups in the removed key of the response, attempt sending emails
            if (!$user->inAccessGroup() && key_exists($user->accessGroup()->id(), $result['removed'])) {
                // Queues sending notifications
                $queue = \Drupal::queue('notify_user_task');
                $queue->createItem([
                    'user_id' => $user->id(),
                    'removed' => $result['removed']
                ]);
            }

            // Mark logs as processed at the end always
            $this->markLogsAsProcessed($logs);
        }
    }
}
