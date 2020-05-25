<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Pager\PagerManager;
use Drupal\Core\Render\Markup;
use Drupal\dpc_user_management\GroupEntity;
use Drupal\dpc_user_management\Plugin\QueueWorker\NotifyUserTask;
use Drupal\dpc_user_management\UserEntity;
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
     * @var \Drupal\Core\Database\Connection
     */
    private $_db;

    /**
     * Queue Name
     *
     * @var string
     */
    private static $queue_name = 'notify_user_task';

    /**
     * @var \Drupal\Core\Queue\QueueInterface
     */
    private $_queue;

    /**
     * @var NotifyUserTask
     */
    private $_queue_worker;

    /**
     * @var DeletedGroupController
     */
    private $_DeletedGroupController;

    /**
     * @return \Drupal\Core\Database\Connection
     */
    private function getDB() {
        if (is_null($this->_db)) {
            $this->_db = \Drupal::database();
        }

        return $this->_db;
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
    public function getAllRecords() {
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
    public function getUnprocessedRecords() {
        return $this->query()
            ->fields($this->t)
            ->orderBy('id')
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
        if (is_null($this->_queue)) {
            $this->_queue = \Drupal::queue(self::$queue_name, true);
        }

        return $this->_queue;
    }

    /**
     * @return NotifyUserTask
     */
    public function queueWorker() {
        if (is_null($this->_queue_worker)) {
            $this->_queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance(self::$queue_name);
        }

        return $this->_queue_worker;
    }

    /**
     * @return DeletedGroupController
     */
    private function getDeletedGroupController() {
        if($this->_DeletedGroupController) {
            return $this->_DeletedGroupController;
        }

        return new DeletedGroupController();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getDeletedGroup($id) {
        return $this->getDeletedGroupController()->getRecord($id);
    }

    /**
     * @param $id
     * @return array
     */
    public function getGroupData($id) {
        $group = GroupEntity::load($id);

        if(!is_null($group)) {
            return [
                'id' => $group->id(),
                'label' => $group->getName(),
                'markup' => Markup::create(sprintf('<a href="/group/%s">%s</a>', $group->id(), $group->getName()))
            ];
        }

        $group = $this->getDeletedGroup($id);

        return [
            'id' => $group->id,
            'label' => $group->label,
            'markup' => Markup::create(sprintf('%s (Deleted)', $group->label))
        ];
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
        $users  = User::loadMultiple(array_unique($uids));

        $table_items = [];
        foreach ($logs as $log) {
            $created       = DrupalDateTime::createFromTimestamp($log->created, \Drupal::currentUser()->getTimeZone())
                ->format('Y-m-d H:i:s');

            $user_display = isset($users[$log->uid])
                ? Markup::create(sprintf('<a href="/user/%s">%s</a>', $log->uid, $users[$log->uid]->getDisplayName()))
                : 'Unavailable';

            $table_items[] = [
                'Date'   => $created,
                'Action' => $log->name,
                'Group'  => $this->getGroupData($log->gid)['markup'],
                'User'   => $user_display,
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
            ->fields([
                'changed' => time(),
                'status' => 'processed']
            )
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
                ? array_merge($c[$log->gid], [$log])
                : [$log];
            return $c;
        }, []);

        $responses = [];

        foreach ($group_states as $group_data) {
            $responses[$group_data[0]->gid] = [
                'original' => $group_data[0]->name === 'added' ? 'removed' : 'added',
                'first' => $group_data[0]->name,
                'last' => (array_pop($group_data))->name
            ];
        }

        $added = array_filter($responses, function ($r) {
            return $r['original'] !== $r['last'] && $r['last'] === 'added';
        });

        $removed = array_filter($responses, function ($r) {
            return $r['original'] !== $r['last'] && $r['last'] === 'removed';
        });
        
        // Return state changes
        return [
            'added'   => $added,
            'removed' => $removed
        ];
    }

    /**
     * @param $logs
     * @return mixed
     */
    public function groupRecordsByUser($logs) {
        // Group records by User
        return array_reduce($logs, function($c, $log) {
            $c[$log->uid] = isset($c[$log->uid])
                ? array_merge($c[$log->uid], [$log])
                : [$log];
            return $c;
        }, []);
    }

    /**
     * Gets all unprocessed records, groups them by user and processes each user group.
     */
    public function processUnprocessedRecords(){
        // Get all unprocessed records
        $allRecords = $this->getUnprocessedRecords();

        // Group records by User
        $user_logs = $this->groupRecordsByUser($allRecords);

        foreach ($user_logs as $uid => $logs) {
            // Get Results based on logs
            $results = $this->processRecordsForUser($uid, $logs);

            if (empty($results['added']) && empty($results['removed'])) {
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
            if (!$user->inAccessGroup() && key_exists($user->accessGroup()->id(), $results['removed'])) {
                // Queues sending notifications
                $this->queue()->createItem([
                    'user_id' => $user->id(),
                    'removed' => $results['removed']
                ]);
            }

            // Mark logs as processed at the end always
            $this->markLogsAsProcessed($logs);
        }

        return [
            'total' => count($allRecords),
            'users' => count($user_logs),
            'queued' => $this->queue()->numberOfItems()
        ];
    }

    /**
     * Delete Log Records for Deleted User
     *
     * @param $user UserEntity
     */
    public function deleteRecordsForUser($user) {
        $this->getDB()->delete($this->t)->condition('uid', $user->id())->execute();
    }

    /**
     * Processes queue and sends out notifications
     */
    public function sendNotifications() {
        while($this->queue()->numberOfItems()){
            $item = $this->queue()->claimItem();
            try{
                $this->queueWorker()->processItem($item->data);
            } catch (\Exception $e) {
                $this->queue()->releaseItem($item);
            }
        }
    }
}
