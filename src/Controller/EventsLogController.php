<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
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

        $markup = '<table>';
        $markup .= '<tr><th>Date</th><th>Action</th><th>Group</th><th>User</th><th>Status</th></tr>';

        foreach ($logs as $log) {
            $created = DrupalDateTime::createFromTimestamp($log->created, \Drupal::currentUser()->getTimeZone())
                ->format('Y-m-d H:i:s');
            $group   = $groups[$log->gid]->getName();
            $user    = $users[$log->uid]->getDisplayName();

            $markup .= '<tr>';
            $markup .= "<td>$created</td>";
            $markup .= "<td>$log->name</td>";
            $markup .= "<td><a href='/group/$log->gid'>$group</a></td>";
            $markup .= "<td><a href='/user/$log->uid'>$user</a></td>";
            $markup .= "<td>$log->status</td>";
            $markup .= '</tr>';
        }

        $markup .= '</table>';

        return [
            '#markup' => $markup
        ];
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
            // @ToDo request sending email
            // $this->sendEmail(['uid' => $uid, 'logs' => $logs, 'result' => $result]);
        }
    }
}
