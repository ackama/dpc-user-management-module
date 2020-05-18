<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Pager\PagerManager;
use Drupal\Core\Render\Markup;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;

class EventsLogController extends ControllerBase
{

    private $table_name = 'dpc_group_events';

    /**
     * @return \Drupal\Core\Database\Connection
     */
    private function getDB() {
        return \Drupal::database();
    }

    /**
     * Returns query object with passed SQL query
     *
     * @param $query
     * @return \Drupal\Core\Database\Query\SelectInterface
     */
    private function query() {
        return $this->getDB()->select($this->table_name);
    }

    /**
     * Returns all records in table
     *
     * @return mixed
     */
    private function getAllRecords() {
        return $this->query()->execute();
    }

    /**
     * Returns records that have not been processed
     *
     * @return mixed
     */
    private function getUnprocessedRecords() {
        return $this->query()
            ->orderBy('uid')
            ->orderBy('gid')
            ->orderBy('created')
            ->condition('status',null)
            ->execute();
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
     * @param $uid
     * @param $user_logs
     * @return array|bool
     */
    public function processRecordsForUser($uid, $user_logs) {
        // @ToDo
        if(false) {
            return false;
        }
        return [];
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
            $result = $this->processRecordsForUser($uid, $logs);
            if (!$result) {
                break;
            }
            // @ToDo mark status as processed and save timestamp
            // @ToDo request sending email
            // $this->sendEmail(['uid' => $uid, 'logs' => $logs, 'result' => $result]);
        }
    }
}
