<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;

class EventsLogController extends ControllerBase
{
    public function display()
    {
        $db   = \Drupal::database();
        $logs = $db->query('SELECT * from {dpc_group_events}');
        $logs = $logs->fetchAll();
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
}