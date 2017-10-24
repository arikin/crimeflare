# Crimeflare imports
This keeps the crimeflare data current in a database.

### Overview
It downloads the data zips and then extracts them. Those extracted files are then parsed into variables.  That data is then inserted into a database.

The settings file, crimeflare.json, lets you set the database credentials, the files to download, and the sql table information.

This is meant to run as a cronjob once every three weeks. Crimeflare only updates once every three weeks anyway.  If you attempt to do it hourly for example they will ban your IP for abuse. **So don't abuse the resources they spent their time building.**

### Installation
This is a simple composer require.
```bash
php composer.phar require arikin/crimeflare_import
```

Protect the **crimeflare.json** file by carefully setting permissions on it. You could also move it to another path if you need. Just update the readSettings method:
```php
    private function readSettings()
    {
        // Needs full path to json file
        // Carefully set the permissions on this file
        $settings_file = __DIR__ . '/../crimeflare.json';
        $fromfile = json_decode( file_get_contents($settings_file), TRUE);
        $this->setSettings($fromfile);
    }
```

### Usage

The **src/UpdateCrimeflare.php** only provides the settings and a PDO object. Those are passed onto **src/Crimeflare.php** that does the actual work. If you need to use your own PDO then copy out these methods: **readSettings()** and **update()** These are very simple methods.

Or you can initialize **src/UpdateCrimeflare.php** and simply call its **update()** method.
```php
use Arikin\UpdateCrimeflare;

$crime = new UpdateCrimeflare();
$crime->update();
```

Please note that the Crimeflare data files are rather large. Each file is handled one at a time and line by line, but creating the sql INSERT statement does take up memory.  So be sure to set the memory limit for the script.
```php
ini_set('memory_limit', '100G');
```

### crimeflare.json
JSON formated settings file. Below is a description of settings:

**base_url** - Full path to base directory. No trailing slash. In here the download/ and extract/ directories are created. **Important** the data files are not deleted after finishing. Please do this in your script to save space.

**pdo** - Array of PDO object settings
**pdo: host** - IP or domain of mysql server
**pdo: db** - Database name
**pdo: user** - Username for mysql user
**pdo: pass** - Password for that user
**pdo: charset** - Character set to use in connecting to the database
**pdo: options** - Array of options for PDO connection. ToDo:

**crimeflare** - Array of values for each Crimeflare data file

The keys are the filename without any prefixes or suffixes. Here is a sample of one:
**ipout** - Array of options for this file
**ipout: file** - filename without any prefixes or suffixes. Important to identifying and creating files and tables.
**ipout: uri** - URL to the files without actual file name. No trailing slashes.
**ipout: sql** - Array of settings for the SQL and parsing the data file
**ipout: sql: table** - Name for table used for this file's data. A prefix is suggested as the tables will be Dropped and Created.
**ipout: sql: fields** - Array of fields. Each line is split on the space character. Order here is important. Index 0 is the data on the far left of the line.
**ipout: sql: fields: updated_at** - Datetime from data file.
**ipout: sql: fields: domain** - Domain
**ipout: sql: fields: ip_address** - IP address

