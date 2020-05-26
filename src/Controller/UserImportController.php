<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dpc_user_management\UserEntity;
use Drupal\file\Entity\File;

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
     * @var Connection
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
            'surname' => [
                'description' => 'Surname',
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
     * @return Connection
     */
    private function getDB() {
        if (is_null($this->_db)) {
            $this->_db = Drupal::database();
        }

        return $this->_db;
    }

    /**
     * Returns query object with passed SQL query
     *
     * @return SelectInterface
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
     * @param $record
     * @return Drupal\Core\Database\StatementInterface|int|null
     * @throws \Exception
     */
    public function insertRecord($record) {
        return $this->getDB()
            ->insert(self::$t)
            ->fields($record)
            ->execute();
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

    /**
     * @param FormStateInterface $state
     * @return EntityInterface|File|null
     */
    public function getCSVfile(FormStateInterface $state) {
        $file_array = $state->getValue('csv_file');

        if (!is_array($file_array) || !isset($file_array[0])) {
            return null;
        }

        return File::load($file_array[0]);
    }

    const ERR_INVALID_RECORD = false;
    const ERR_CONTAINS_COLUMN_NAME = false;
    const ERR_INVALID_RDATE = false;

    const OUT_SUCCESS = 'import';
    const OUT_FAILED = 'failed';
    const OUT_MAIL_EXISTS = 'mail exists';
    const OUT_USERNAME_EXISTS = 'username exists';
    const OUT_INVALID = 'invalid';

    const ST_NEW = 'new';
    const ST_IMPORTED = 'imported';
    const ST_NOT_ALLOWED = 'not allowed';

    /**
     * @param $record
     * @return bool|mixed
     */
    public function validateImportRecord($record) {
        return $record;
    }

    public function generateUsername($record, $attempt = 0) {
        $name = strtolower($record['first_name']);
        $surname = strtolower($record['surname']);

        $username = sprintf('%s_%s',
            substr($name,0,3),
            substr($surname, 0)
        );

        $record['username'] = $record;

        return $record;
    }

    public function validateUsername($record) {


        return $record;
    }

    public function validateDate($date) {
        $dt = \DateTime::createFromFormat("Y-m-d", $date);

        return $dt !== false && !array_sum($dt::getLastErrors());
    }

    /**
     * @param $data
     * @return bool|mixed
     */
    public function parseImportUserRecord($data) {
        $column_names = ['FIRST NAME', 'SURNAME', 'EMAIL', 'REGISTRATION DATE'];
        $column_keys = ['first_name', 'surname', 'email', 'registration_date'];

        $response = [];

        // Checks we have 4 columns
        if(count($data) !== count($column_keys)) {
            return self::ERR_INVALID_RECORD;
        }

        // Check we don't have any column names in out data
        foreach($column_names as $key => $name) {
            if($data[$key] == $name) {
                return self::ERR_CONTAINS_COLUMN_NAME;
            }
        }

        // We create a record like array with the keys and data
        $record = array_combine($column_keys, $data);

        if(!$this->validateDate($record['registration_date'])) {
            return self::ERR_INVALID_RDATE;
        }

        // generates valid username
        $username_attempts = 0;
        do {
            $record['username'] = $this->generateUsername($record, $username_attempts);
            $username_attempts++;
        } while (!$this->validateUsername($record));

        // Capitalise Names
        $record['first_name'] = ucfirst($record['first_name']);
        $record['surname'] = ucfirst($record['surname']);

        // Have a Full Name
        $record['name'] = sprintf('%s %s', $record['first_name'], $record['surname']);

        return $this->validateImportRecord($record);
    }

    /**
     * @param File $file
     * @return bool
     */
    public function parseCSVFile(File $file) {

        $handle = fopen($file->getFilename(),'r');

        if(!$handle) {
            return false;
        }

        while (!($data = fgetcsv($handle))) {
            $record = $this->parseImportUserRecord($data);

            if($record) {
                // $this->saveRecordInDB($record);
            }
        }

        fclose($handle);

        return true;
    }

    public function processImport(FormStateInterface $form_state)
    {
        drupal_flush_all_caches();
        /**
         * 1. Get Users from Data
         * 2. Get Domains from Data
         * 3. Validate Users
         * 4. Process each user and save record
         * 5. Report / Forward to Commit with Report
         **/

        $csv_file = $this->getCSVfile($form_state);
        if( !$csv_file) {
            return false;
        }
        $results = $this->parseCSVFile($csv_file);
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
