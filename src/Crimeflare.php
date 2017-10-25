<?php
/**
 * User: kinney
 * Date: 2017/10/20
 * Time: 13:19
 */

namespace Arikin;

use Arikin\DownloadExtract as DownloadExtract;
use Arikin\DbUpdate as DbUpdate;

class Crimeflare
{
    /**
     * All settings from file
     * @var array
     */
    private $settings;

    /**
     * PDO object received
     * @var object
     */
    private $pdo;

    /**
     * Character to split on
     * @var string
     */
    private $delimiter;

    /**
     * Parameter Bind Limit
     * @var integer
     */
    private $binding_limit;

    public function __construct($options = array())
    {
        // Defaults are set there as well
        $this->setOptions($options);
    }

    public function update()
    {
        $dl = new DownloadExtract(array(
            'base_url' => $this->settings['base_url'],
            'base_dir' => $this->settings['base_dir'],
            'fetch_curl_timeout' => $this->settings['curl_timeout'],
        ));
        // Get files
        $extracted = array();
        foreach($this->settings['crimeflare'] as $key => $details) {
            $extracted[ $details['file'] ] = $dl->getFile($details['file']);
        }
        // Import from file and update DB
        foreach($extracted as $file => $path) {
            if(!is_null($path)) {
                $file_sql = $this->settings['crimeflare'][$file]['sql'];
                $data = $this->importData($path.$file);
                $this->setPdo();
                // Tables would be too big and have duplicate data
                $this->dropTable($file_sql['table']);
                $this->createTable($file_sql['table'], $file_sql['fields']);
                $this->chunkInsert($data, $file);
                // To prevent timeouts for large tables.
                $this->closePdo();
            }
        }
    }

    private function chunkInsert($data, $file)
    {
        if($data && $file) {
            // Multiple batches of INSERTs for this table
            foreach(array_chunk($data, $this->binding_limit, TRUE) as $set) {
                $details = $this->settings['crimeflare'][$file];
                $sql = $this->getFileSqlHead($details);
                $placeholder = $this->getPlaceholder($details);
                $data_inserts = array();
                $query_inserts = array();
                foreach($set as $row) {
                    $query_inserts[] = $placeholder;
                    foreach($row as $field) {
                        $data_inserts[] = $field;
                    }
                }
                // Combine all, in order, into the sql
                if(!empty($data_inserts)) {
                    $sql .= implode(', ', $query_inserts); // indexed placeholders: ( ?, ?, ? )
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($data_inserts); // combine with actual data
                }
            }
        }
    }

    private function getFileSqlHead($details)
    {
        $sql = sprintf("INSERT INTO `%s` (", $details['sql']['table'])
            . implode(", ", array_keys($details['sql']['fields']))
            . ") VALUES "; // trailing space required

        return $sql;
    }

    private function getPlaceholder($details)
    {
        $placeholder = '(' . implode(',', array_fill(0, count($details['sql']['fields']), '?')) . ')';
        return $placeholder;
    }

    public function dropTable($table = FALSE)
    {
        if($table) {
            $sql = sprintf("DROP TABLE IF EXISTS `%s`", $table);
            return $this->pdo->query($sql);
        } else {
            return FALSE;
        }
    }

    public function createTable($table = FALSE, $fields = FALSE)
    {
        if($table && $fields) {
            $sql = sprintf("CREATE TABLE IF NOT EXISTS `%s` (", $table);
            $sql .= "id INT AUTO_INCREMENT NOT NULL";
            foreach($fields as $name => $data_type) {
                $sql .= ", " . $name . " " . $data_type;
            }
            $sql .= ", PRIMARY KEY (id))";
            return $this->pdo->query($sql);
        } else {
            return FALSE;
        }
    }

    private function importData($path)
    {
        $input_data = array();
        $input_handle = fopen($path, "r");
        if($input_handle) {
            $index = 0;
            while(($line = fgets($input_handle)) !== FALSE) {
                $split = explode($this->delimiter, trim($line));
                $input_data[$index] = array();
                foreach($split as $s) {
                    $input_data[$index][] = $s;
                }
                $index++;
            }
        }
        fclose($input_handle);

        return $input_data;
    }

    private function setOptions($options)
    {
        // settings
        if(isset($options['settings_file'])) {
            $this->readSettings($options['settings_file']);
        } else {
            $this->readSettings(__DIR__ . '/../crimeflare.json');
        }
        // delimiter for source data split command
        if(isset($options['delimiter'])) {
            $this->setDelimiter($options['delimiter']);
        } else {
            $this->setDelimiter(" ");
        }
        // MySQL binding limit
        $this->setBindingLimit();
    }

    private function readSettings($settings_file)
    {
        // Needs full path to json file
        // Carefully set the permissions on this file
        $fromfile = json_decode( file_get_contents($settings_file), TRUE);
        $this->setSettings($fromfile);
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     */
    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return object
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * PDO object for DB
     * @param object $pdo
     */
    public function setPdo()
    {
        $this->pdo = new DbUpdate(
            $this->settings['pdo']['host'],
            $this->settings['pdo']['db'],
            $this->settings['pdo']['user'],
            $this->settings['pdo']['pass'],
            $this->settings['pdo']['charset'],
            $this->settings['pdo']['timeout']
        );
    }

    /**
     * Close the PDO object
     */
    public function closePdo()
    {
        $this->pdo = NULL;
    }

    /**
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * @param string $delimiter
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
    }

    /**
     * @return int
     */
    public function getBindingLimit()
    {
        return $this->binding_limit;
    }

    /**
     * @param int $binding_limit
     */
    public function setBindingLimit($binding_limit = 20000)
    {
        $this->binding_limit = $binding_limit;
    }
}