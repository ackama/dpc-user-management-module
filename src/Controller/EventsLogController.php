<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Render\Markup;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;

class EventsLogController extends ControllerBase
{
    public function display()
    {
        $db     = \Drupal::database();
        $logs   = $db->query('SELECT * from {dpc_group_events}');
        $logs   = $logs->fetchAll();
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
        $pager_manager = \Drupal::service('pager.manager');
        $pager         = $pager_manager->createPager($total, $num_page);
        $current_page  = $pager->getCurrentPage();
        // Split an array into chunks
        $chunks = array_chunk($items, $num_page);
        // Return current group item
        $current_page_items = $chunks[$current_page];

        return $current_page_items;
    }
}