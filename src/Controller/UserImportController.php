<?php
namespace Drupal\dpc_user_management\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\file\Entity\File;

class UserImportController extends ControllerBase
{
    // Schema definitions and table name/alias

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
     * Provides Schema for Importing Users
     *
     * @var array
     */
    public static $schema = [
        'description' => 'Table for pre-importing users from a CSV file',
        'fields'      => [
            'id'      => [
                'description' => 'Primary Key of the User',
                'type'        => 'serial',
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
            'outcome' => [
                'description' => 'Pre Import Outcome',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => false,
                'default'     => 'unknown'
            ],
            'status' => [
                'description' => 'Import Status',
                'type'        => 'varchar',
                'length'      => 255,
                'not null'    => true,
                'default'     => 'unknown'
            ]
        ],
        'primary key' => [
            'id',
        ],
    ];

    // Process Constants

    const ERR_INVALID_RECORD = false;
    const ERR_CONTAINS_COLUMN_NAME = false;
    const ERR_INVALID_RDATE = false;

    const OUT_VALID = 'valid to import';
    const OUT_MAIL_EXISTS = 'e-mail exists';
    const OUT_MAIL_REPEATED = 'email exists in import';
    const OUT_MAIL_DOMAIN_INVALID = 'e-mail domain not whitelisted';
    const OUT_USERNAME_EXISTS = 'username exists';
    const OUT_UNKNOWN = 'unknown';

    const ST_RAW = 'raw';
    const ST_NEW = 'new';
    const ST_IMPORTED = 'imported';
    const ST_NOT_ALLOWED = 'not allowed';
    const ST_UNKNOWN = 'unknown';

    // DB Access Methods and Properties

    /**
     * @var Connection
     */
    private $_db;

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
            ->fetchAssoc();
    }

    /**
     * Returns a count of each status in database
     *
     * @return mixed
     */
    public function getRecordsCount()
    {
        $records = $this->query()
            ->fields(self::$t, [
                'status'
            ]);
        $records->addExpression(sprintf('count(%s.status)',self::$t), 'count');

        return $records
            ->groupBy(self::$t . '.status')
            ->execute()
            ->fetchAll();
    }

    /**
     * Return a list of Objects with the id field given a certain status
     *
     * @param $status
     * @param null $limit
     * @return mixed
     */
    public function getRecordsIDsByStatus($status, $limit = null) {
        return $this->query()
            ->fields(self::$t, ['id'])
            ->condition('status', $status)
            ->range(0 , $limit)
            ->execute()
            ->fetchAll();
    }

    /**
     * Returns amount of records give a certain status
     *
     * @param $status
     * @return mixed
     */
    public function getCountByStatus($status) {
        return $this->query()
            ->condition('status', $status)
            ->countQuery()
            ->execute()
            ->fetchField();
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
     * @param $records
     * @return Drupal\Core\Database\StatementInterface|int|null
     * @throws \Exception
     */
    public function insertRecords($records)
    {
        $query = $this->getDB()
            ->insert(self::$t)
            ->fields(array_keys($records[0]));

        foreach ($records as $r) {
            $query->values(array_values($r));
        }

        return $query->execute();
    }

    /**
     * Updates Record with new information
     *
     * @param $record
     * @return Drupal\Core\Database\StatementInterface|int|null
     */
    public function updateRecord($record) {
        $rid = $record['id'];
        unset($record['id']);

        return $this->getDB()
            ->update(self::$t)
            ->fields($record)
            ->condition('id', $rid)
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

    /// Validation Methods

    /**
     * @param $record
     * @return array
     */
    public function validateImportRecord($record) {
        $record['outcome'] = self::OUT_UNKNOWN;
        $record['status'] = self::ST_UNKNOWN;

        if (!$this->validateEmailUnique($record)) {
            $record['outcome'] = self::OUT_MAIL_EXISTS;
            $record['status']  = self::ST_NOT_ALLOWED;

            return $record;
        }

        if (!$this->validateEmailUniqueInImport($record)) {
            $record['outcome'] = self::OUT_MAIL_REPEATED;
            $record['status']  = self::ST_NOT_ALLOWED;

            return $record;
        }

        if (!$this->validateUsername($record)) {
            $record['outcome'] = self::OUT_USERNAME_EXISTS;
            $record['status']  = self::ST_NOT_ALLOWED;

            return $record;
        }

        $record['outcome'] = self::OUT_VALID;
        $record['status']  = self::ST_NEW;

        return $record;
    }

    /**
     * Returns a valid username
     *
     * @param $record
     * @param int $attempt
     * @return string
     */
    public function generateUsername($record, $attempt = 0) {
        $name = strtolower($record['first_name']);
        $surname = strtolower($record['surname']);

        // join surname with a space
        $username = sprintf('%s.%s', $name, $surname);

        // Clean as a machine name
        $username = \Drupal::transliteration()->transliterate($username, LanguageInterface::LANGCODE_DEFAULT, '_', 60);
        $username = mb_strtolower($username);
        $username = preg_replace('@[^a-z0-9_.]+@', '_', $username);

        // If not first attempt at creating a valid username,
        // use the $attempt number to append into the surname
        // number is padded by a zero and a dot
        if($attempt) {
            $username = sprintf("%s.%02d", substr($username,0,57), $attempt);
        }

        return $username;
    }

    /**
     * Validates inqueness of username against existing user database
     *
     * @param $record
     * @return bool
     */
    public function validateUsername($record) {
        $query = \Drupal::entityQuery('user');

        $username = $record['username'];

        return !$query->condition('name', $username)
            ->count()
            ->execute();
    }

    /**
     * @param $record
     * @return boolean
     */
    public function validateEmailUnique($record) {
        $query = \Drupal::entityQuery('user');

        $email = $record['email'];

        return !$query->condition(
                $query->orConditionGroup()->condition('field_email_addresses.value', $email)
                    ->condition('mail', $email)
            )
            ->count()
            ->execute();
    }

    /**
     * Validates if record is not being imported twice
     *
     * @param $record
     * @return boolean
     */
    public function validateEmailUniqueInImport($record) {
        return !$this->query()
            ->fields(self::$t)
            ->condition('email', $record['email'])
            ->condition('outcome', self::OUT_VALID)
            ->condition('status', self::ST_NEW)
            ->countQuery()
            ->execute()
            ->fetchField();
    }

    /**
     * @param $record
     * @param $whitelist
     * @return bool
     */
    public function validateEmailDomain($record, $whitelist) {
        $parsed_data = explode('@', $record['email']);

        return in_array($parsed_data[1], $whitelist);
    }

    /**
     * @param $date
     * @return bool
     */
    public function validateDate($date) {
        $dt = \DateTime::createFromFormat("Y-m-d", $date);

        return $dt !== false && !array_sum($dt::getLastErrors());
    }

    /**
     * Return if the record has an outcome that is allowed to be stored
     *
     * @param $record
     * @return bool
     */
    public function isValidOutcome($record)
    {
        return in_array($record,
            [
                self::OUT_VALID,
                self::OUT_USERNAME_EXISTS,
                self::OUT_MAIL_DOMAIN_INVALID,
                self::OUT_MAIL_EXISTS,
                self::OUT_MAIL_REPEATED
            ]
        );
    }

    /**
     * @param $data
     * @param $whitelist
     * @return bool|mixed
     */
    public function parseImportUserRecord($data, $whitelist) {
        $column_names = ['FIRST NAME', 'SURNAME', 'EMAIL', 'REGISTRATION DATE'];
        $column_keys = ['first_name', 'surname', 'email', 'registration_date'];

        $response = [];

        // Checks we have 4 columns
        if(count($data) !== count($column_keys)) {
            return self::ERR_INVALID_RECORD;
        }

        // Check we don't have any column names in out data
        foreach($column_names as $key => $name) {
            if($data[trim($key)] == $name) {
                return self::ERR_CONTAINS_COLUMN_NAME;
            }
        }

        // We create a record like array with the keys and data
        $record = array_combine(
            $column_keys,
            array_map(
                function($v) {
                    // Clean whitespace from strings.
                    // Add other cleanup here
                    return trim($v);
                },
                $data
            )
        );
        $record['outcome'] = self::OUT_UNKNOWN;
        $record['status'] = self::ST_RAW;

        if(!$this->validateDate($record['registration_date'])) {
            return self::ERR_INVALID_RDATE;
        }

        // Mark record as not in whitelist early in the process
        if (!$this->validateEmailDomain($record, $whitelist)) {
            $record['outcome'] = self::OUT_MAIL_DOMAIN_INVALID;
            $record['status'] = self::ST_NOT_ALLOWED;
        }

        return $record;
    }

    /**
     * @param $record
     * @return mixed
     */
    public function populateImportedRecord($record) {

        // Capitalise Names
        $record['first_name'] = ucfirst($record['first_name']);
        $record['surname'] = ucfirst($record['surname']);

        // generates valid username
        $username_attempts = 0;
        do {
            $record['username'] = $this->generateUsername($record, $username_attempts);
            $username_attempts++;
        } while (!$this->validateUsername($record));

        // Have a Full Name
        $record['name'] = sprintf('%s %s', $record['first_name'], $record['surname']);

        return $record;
    }

    /**
     * @param File $file
     * @param array $whitelist
     * @return array|bool
     * @throws \Exception
     */
    public function importCSVFile(File $file, array $whitelist) {

        $handle = fopen($file->getFileUri(),'r');
        $records = [];
        $r_total = 0;
        $r_parsed = 0;

        if(!$handle) {
            return false;
        }

        while ($data = fgetcsv($handle)) {
            $record = $this->parseImportUserRecord($data, $whitelist);

            $r_total++;

            if(!$record) {
                continue;
            }

            $r_parsed++;

            $records[] = $record;

            if(count($records) > 200) {
                $this->insertRecords($records);
                $records = [];
            }
        }

        if(count($records)) {
            $this->insertRecords($records);
        }

        fclose($handle);

        return [
            'total' => $r_total,
            'parsed' => $r_parsed
        ];
    }

    /**
     * Method that processes raw records from imported CSV from batch api form
     *
     * @param $total
     * @param $context
     */
    public static function processAndValidateRecords($total, &$context) {

        $controller = new self();

        if (empty($context['sandbox'])) {
            $context['sandbox']['progress'] = 0;
            $context['sandbox']['current_id'] = 0;
            $context['sandbox']['max'] = $total;
            $context['results'] = [
                self::OUT_UNKNOWN => 0,
                self::OUT_MAIL_DOMAIN_INVALID => 0,
                self::OUT_VALID => 0,
                self::OUT_MAIL_REPEATED => 0,
                self::OUT_MAIL_EXISTS => 0,
                self::OUT_USERNAME_EXISTS => 0
            ];
        }

        $records_per_batch = 10;

        $record_ids = $controller->getRecordsIDsByStatus(self::ST_RAW, $records_per_batch);

        foreach($record_ids as $r) {
            $record = $controller->getRecord($r->id);
            $record = $controller->populateImportedRecord($record);
            $record = $controller->validateImportRecord($record);
            $controller->updateRecord($record);

            $context['sandbox']['current_id'] = $r->id;
            $context['sandbox']['progress']++;
            $context['results'][$record['outcome']]++;
        }

        if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
            $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
        }

        $context['message'] = 'Processed ' . $context['sandbox']['progress'] . ' records out of ' . $total;
    }

    /**
     * Method that gets called at the end of the process from @processAndValidateRecords
     *
     * @param $success
     * @param $results
     * @param $operations
     */
    public static function processAndValidateRecordsFinishedCallback($success, $results, $operations) {

        // If there's errors in processing
        if(!$success) {
            $error_operation = reset($operations);
            $message = t('An error occurred while processing %error_operation with arguments: @arguments', array(
                '%error_operation' => $error_operation[0],
                '@arguments' => print_r($error_operation[1], TRUE),
            ));
            (new \Drupal\Core\Messenger\Messenger)->addMessage($message);

            return;
        }

        // @ToDo display nice report
        dpm($results);
    }

    /**
     * @param File $file
     * @param array $whitelist
     * @return array|bool
     * @throws \Exception
     */
    public function processImport(File $file, array $whitelist)
    {
        drupal_flush_all_caches();

        if(!$file || empty($whitelist)) {
            return false;
        }

        $results = $this->importCSVFile($file, $whitelist);

        if (!$results) {
            return false;
        }

        return $results;
    }

    public function processCommit()
    {
        /**
         * 1. Process Records in Batches of 10
         * 2. Create User from Records in DB
         * 3. Capture final outcome in Database
         */
    }

    /**
     * Provides report on import records
     *
     * @return array
     */
    public function statusStep() {

        $statuses = [];

        $statuses[self::ST_RAW] = [
            'name' => 'Parsed records without validation or preprocessing',
            'count' => 0,
            'actions' => [
                'data' => [
                    'actions' => [
                        '#type' => 'link',
                        '#title' => $this->t('Validate and Preprocess'),
                        '#url' => \Drupal\Core\Url::fromRoute('dpc_user_management.user_import.validate'),
                        '#options' => [
                            'attributes' => [
                                'class' => 'button button--small'
                            ]
                        ]
                    ]
                ]
            ],
        ];

        $statuses[self::ST_NEW] = [
            'name' => 'Records that have been validated and preprocessed, and can be imported',
            'count' => 0,
            'actions' => [
                'data' => [
                    'actions' => [
                        '#type' => 'link',
                        '#title' => $this->t('Execute Import'),
                        '#url' => \Drupal\Core\Url::fromRoute('dpc_user_management.user_import.commit'),
                        '#options' => [
                            'attributes' => [
                                'class' => 'button button--small'
                            ]
                        ]
                    ]
                ]
            ],
        ];

        $statuses[self::ST_NOT_ALLOWED] = [
            'name' => 'Records that were parsed but will not be imported due to various reasons',
            'count' => 0,
            'actions' => '',
        ];

        $statuses[self::ST_IMPORTED] = [
            'name' => 'Records that have been imported already.',
            'count' => 0,
            'actions' => '',
        ];

        $statuses[self::ST_UNKNOWN] = [
            'name' => 'Records with unknown status. This should always be 0.',
            'count' => 0,
            'actions' => '',
        ];

        foreach ($this->getRecordsCount() as $row) {
            $statuses[$row->status]['count'] = $row->count;
        }

        $element = [];

        $element['test_table'] = [
            '#type' => 'table',
            '#header' => [
                'Status',
                'Number of Records',
                'Actions',
            ],
            '#rows' => $statuses,
            '#attributes' => array('class'=>array('import-report-table')),
            '#header_columns' => 3,
        ];

        return $element;
    }
}
