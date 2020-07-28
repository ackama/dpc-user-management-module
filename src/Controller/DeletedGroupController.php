<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dpc_user_management\GroupEntity;

class DeletedGroupController extends ControllerBase
{
    /**
     * Table name
     *
     * @var string
     */
    public static $table_name = 'dpc_groups_deleted';

    /**
     * Table alias.
     *
     * @var string
     */
    private static $t = 'dpc_groups_deleted';

    /**
     * @var \Drupal\Core\Database\Connection
     */
    private $_db;

    /**
     * Provides Schema for Deleted Groups
     *
     * @var array
     */
    public static $schema = [
        'description' => 'Table for Keeping track of deleted groups',
        'fields'      => [
            'id'      => [
                'description' => 'Primary Key of the Group',
                'type'        => 'int',
                'unsigned'    => true,
                'not null'    => true,
            ],
            'label'    => [
                'description' => 'Group Name',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => '',
            ],
            'data' => [
                'description' => 'Array Data extracted from the toArray() method of the object',
                'type' => 'blob',
                'size' => 'normal',
                'not null' => FALSE
            ],
            'deleted' => [
                'type'     => 'int',
                'not null' => false,
                'size'     => 'normal',
            ],
        ],
        'primary key' => [
            'id',
        ],
    ];

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
        return $this->getDB()->select(self::$table_name, self::$t);
    }

    /**
     * Returns all records in table
     *
     * @return mixed
     */
    public function getAllRecords() {
        return $this->query()
            ->fields(self::$t)
            ->execute()
            ->fetchAll();
    }

    /**
     * Returns records that have not been processed
     *
     * @param $id
     * @return mixed
     */
    public function getRecord($id) {
        return $this->query()
            ->fields(self::$t)
            ->orderBy('id')
            ->condition('id',$id)
            ->execute()
            ->fetchObject();
    }

    /**
     * Returns records that have not been processed
     *
     * @param GroupEntity $group
     * @return mixed
     * @throws \Exception
     */
    public function saveDeletedRecord(GroupEntity $group) {
        $this->getDB()->insert(self::$t)->fields([
            'id'    => $group->id(),
            'label' => $group->label(),
            'data'  => serialize($group->toArray()),
            'deleted' => strtotime('now')
        ])->execute();

        $record = $this->getRecord($group->id());

        if ($record->id != $group->id()) {
            return false;
        }

        return $record;
    }
}
