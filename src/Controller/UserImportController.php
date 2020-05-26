<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dpc_user_management\UserEntity;

class UserImportController extends ControllerBase
{
    /**
     * Table name
     *
     * @var string
     */
    public static $table_name = 'dpc_user_import';

    /**
     * Table alias.
     *
     * @var string
     */
    private static $t = 'dpc_user_import';

    /**
     * @var \Drupal\Core\Database\Connection
     */
    private $_db;

    /**
     * Provides Schema for Importing Users
     *
     * @var array
     */
    public static $schema = [
        'description' => 'Table for pre-importing users from a CSV file',
        'fields'      => [
            'id'      => [
                'description' => 'Primary Key of the User',
                'type'        => 'int',
                'unsigned'    => true,
                'not null'    => true,
            ],
            'first_name' => [
                'description' => 'First Name',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => '',
            ],
            'last_name' => [
                'description' => 'Last Name',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => '',
            ],
            'name'    => [
                'description' => 'User Full Name',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => '',
            ],
            'email'     => [
                'description' => 'User\'s Email',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => '',
            ],
            'username'     => [
                'description' => 'Username',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => '',
            ],
            'registration_date' => [
                'description' => 'Username',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => '',
            ],
            'pre_import_outcome' => [
                'description' => 'Pre Import Outcome',
                'type'        => 'varchar',
                'length'      => 255,
                'not null' => false,
                'default'  => ''
            ],
            'status' => [
                'description' => 'Import Status',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => 'invalid'
            ]
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
            ->condition('status', 'pending')
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
     * @param array $record
     * @return mixed
     */
    public function createUser($record) {
        // Stub
        // Generate New User Data
        // Verify its not conflicting
        // UserEntity::create($data);
        // Update User Record with outcome and status

        return true;
    }

    public function processCSVFile() {

    }

    public function processImport(FormStateInterface $form_state)
    {
        /**
         * 1. Get Users from Data
         * 2. Get Domains from Data
         * 3. Validate Users
         * 4. Process each user and save record
         * 5. Report / Forward to Commit with Report
         **/
    }

    public function processCommit()
    {
        /**
         * 1. Process Records in Batches of 10
         * 2. Create User from Records in DB
         * 3. Capture final outcome in Database
         */
    }
}
