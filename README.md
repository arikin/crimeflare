# Crimeflare imports
This keeps the crimeflare data current in a database. The download page for the data file zips: http://www.crimeflare.info/zippy.html
This has its own search form: http://www.crimeflare.info/cfs.html#box

### Overview
It downloads the data zips and then extracts them. Those extracted files are then parsed into variables.  That data is then inserted into a database with a table for each data file.

The settings file, crimeflare.json, lets you set the database credentials, the files to download, and the table sql information.

This is meant to run as a cronjob once every three weeks. Crimeflare only updates once every three weeks anyway.  If you attempt to do it hourly for example they will ban your IP for abuse. **So don't abuse the resources they spent their time building.**

### Installation
This is a simple composer require.
```bash
php composer.phar require arikin/crimeflare
```

Protect the **crimeflare.json** file by carefully setting permissions on it. You could also move it to another path if you need. Just pass the setting into Crimeflare:
```php
$crime = new Crimeflare(array(
    'settings_file' => "[full path to file]"
));
```
### Usage
Update the **crimeflare.json** file with real DB credentials.<br>
Initiate the Crimeflare class and call the **update** method.
```php
use Arikin\Crimeflare;

$crime = new Crimeflare();
$crime->update();
```

Please note that the Crimeflare data files are rather large. Each file is handled one at a time and line by line, but creating the sql INSERT statements does take up memory.  So be sure to set the memory limit for the script.
```php
ini_set('memory_limit', '1G');
```

### crimeflare.json
JSON formated settings file. Below is a description of settings:

- **base_dir** - Full path to base directory. No trailing slash. In here the download/ and extract/ directories are created. **Important** the data files are not deleted after finishing. Please do this in your script to save space.
- **base_url** - Base url to files. No trailing slash. Default is: http://crimeflare.net:82/domains
- **curl_timeout** - Timeout for fetching each file.
- **pdo** - Array of PDO object settings
  - **pdo: host** - IP or domain of mysql server
  - **pdo: db** - Database name
  - **pdo: user** - Username for mysql user
  - **pdo: pass** - Password for that user
  - **pdo: charset** - Character set to use in connecting to the database
  - **pdo: timeout** - Array of options for PDO connection. ToDo:

- **crimeflare** - Array of values for each Crimeflare data file. See below for an example of **ipout.zip**.

The keys are the filename without any prefixes or suffixes. Here is a sample of one:
- **ipout** - Array of options for this file
  - **ipout: file** - filename without any prefixes or suffixes. Important to identifying and creating files and tables.
  - **ipout: uri** - URL to the files without actual file name. No trailing slashes.
    - **ipout: sql** - Array of settings for the SQL and parsing the data file
    - **ipout: sql: table** - Name for table used for this file's data. A prefix is suggested as the tables will be Dropped and Created.
    - **ipout: sql: fields** - Array of fields. Each line is split on the space character. Order here is important. Index 0 is the data on the far left of the line.
      - **ipout: sql: fields: updated_at** - Datetime from data file.
      - **ipout: sql: fields: domain** - Domain
      - **ipout: sql: fields: ip_address** - IP address

### Notes
The inserts per table are done in groups of 20,000 records by default. If your DB can handle more parameter binds then set a new integer limit based on this formula before using the update method:
DB bind limit / Number of fields
```php
$crime->setBindingLimit(50000);
$crime->update();
```

The PDO was separated out so you could provide your own if needed. Change the use statement inside **src/Crimeflare.php**.
```php
use \DbUpdate;
```
And then provide a method to drop and create the tables like in **src/DbUpdate.php**.
```php
    public function dropTable($table = FALSE)
    {
        if($table) {
            $sql = sprintf("DROP TABLE IF EXISTS `%s`", $table);
            return $this->query($sql);
        } else {
            return FALSE;
        }
    }

    public function createTable($table = FALSE, $fields = FALSE)
    {
        if($table && $fields) {
            $sql = sprintf("CREATE TABLE IF NOT EXISTS `%s` (", $table);
            $sql .= "id INT AUTO_INCREMENT NOT NULL";
            foreach($fields as $field_line) {
                $sql .= $field_line;
            }
            $sql .= ", PRIMARY KEY (id))";
            return $this->query($sql);
        } else {
            return FALSE;
        }
    }
```