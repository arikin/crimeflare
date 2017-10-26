<?php
/**
 * User: kinney
 * Date: 2017/10/20
 * Time: 13:41
 */

namespace Arikin;

use \PhpZip\ZipFile;

class DownloadExtract
{
    /**
     * Base directory to use for processing
     * @var string
     */
    private $base_dir;

    /**
     * @var string
     */
    private $download_dir;

    /**
     * Location of extracted zips
     * @var string
     */
    private $extract_dir;

    /**
     * URL base
     * @var string
     */
    private $uri_base;

    /**
     * @var integer
     */
    private $uri_port;

    /**
     * Curl Timeout in seconds
     * @var integer
     */
    private $fetch_curl_timeout;

    private $dl_complete;
    private $dl_fail;
    private $extract_complete;
    private $extract_fail;

    /**
     * DownloadExtract constructor.
     * @param bool $options
     */
    public function __construct($options = array())
    {
        // Defaults are set there as well
        $this->setOptions($options);
    }

    public function getFile($file)
    {
        $input_file = $this->isBaseFilename($file);
        // Make directories
        $this->checkCreateDir(sprintf("%s/%s", $this->download_dir, $input_file));
        $this->checkCreateDir(sprintf("%s/%s", $this->extract_dir, $input_file));

        // Download the Zip file
        $this->fetchZip($input_file);
        // Extract the Zip file
        $this->unZipTo($input_file);

        return $this->extract_complete[$file];
    }

    private function isBaseFilename($file)
    {
        $output = ltrim(rtrim($file, '.zip'), '/');
        return $output;
    }

    /**
     * Download the zip file
     * @param $file
     */
    private function fetchZip($file)
    {
        $file_url = sprintf("%s/%s.zip", $this->uri_base, $file);
        $local = sprintf("%s/%s/%s.zip", $this->download_dir, $file, $file);
        $local_handle = fopen($local, "w");

        $ch_start = curl_init();
        curl_setopt($ch_start, CURLOPT_URL, $file_url);
        curl_setopt($ch_start, CURLOPT_FAILONERROR, true);
        curl_setopt($ch_start, CURLOPT_HEADER, 0);
        curl_setopt($ch_start, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch_start, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch_start, CURLOPT_BINARYTRANSFER,true);
        curl_setopt($ch_start, CURLOPT_TIMEOUT, $this->fetch_curl_timeout);
        curl_setopt($ch_start, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch_start, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch_start, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch_start, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36");
        curl_setopt($ch_start,CURLOPT_PORT, $this->uri_port);
        curl_setopt($ch_start, CURLOPT_FILE, $local_handle);

        $page = curl_exec($ch_start);

        if(!$page || !file_exists($local)) {
            $this->dl_fail[] = $file;
        } else {
            $this->dl_complete[$file] = $local;
        }
        curl_close($ch_start);
    }

    private function unZipTo($file)
    {
        if(isset($this->dl_complete[$file])) {
            $destination = sprintf("%s/%s/", $this->extract_dir, $file);

            $zip = new \PhpZip\ZipFile();
            $zip->openFile($this->dl_complete[$file]);
            $zip->extractTo($destination);
            $zip->close();

            if(file_exists($destination)) {
                $this->extract_complete[$file] = $destination;
            } else {
                $this->extract_fail[] = $file;
            }
        }
    }

    /**
     * Check and create the
     * output directories
     */
    private function setupDir($base_dir)
    {
        $this->setBaseDir($base_dir);
        $this->setDownloadDir();
        $this->setExtractDir();
        $this->checkCreateDir($this->base_dir);
        $this->checkCreateDir($this->download_dir);
        $this->checkCreateDir($this->extract_dir);
    }

    private function checkCreateDir($directory)
    {
        $result = FALSE;
        $dir = rtrim($directory, '/');

        if(file_exists($dir)) {
            if(is_dir($dir)) {
                $result = $dir;
            }
        } else {
            mkdir($dir, 0777, TRUE);
            $result = $dir;
        }

        return $result;
    }

    /*
     * Setters and Getters
     */

    private function setOptions($options)
    {
        // common url base
        if(isset($options['base_url'])) {
            $this->setUriBase($options['base_url']);
        } else {
            // Default
            $this->setUriBase('http://crimeflare.net:82/domains');
        }
        // Path to use as base directory
        if(isset($options['base_dir'])) {
            $this->setupDir($options['base_dir']);
        } else {
            // Default
            $this->setupDir('/tmp/crimeflare');
        }
        // Nonstandard port used...
        if(isset($options['base_port'])) {
            $this->setUriPort($options['base_port']);
        } else {
            $this->setUriPort(82);
        }
        // Curl timeout in seconds
        if(isset($options['fetch_curl_timeout'])) {
            $this->setFetchCurlTimeout($options['fetch_curl_timeout']);
        } else {
            $this->setFetchCurlTimeout(120);
        }
    }

    /**
     * @return string
     */
    public function getBaseDir()
    {
        return $this->base_dir;
    }

    /**
     * @param string $base_dir
     */
    public function setBaseDir($base_dir)
    {
        $base_dir = rtrim($base_dir, '/');
        $this->base_dir = $base_dir;
    }

    /**
     * @return string
     */
    public function getDownloadDir()
    {
        return $this->download_dir;
    }

    /**
     * No params
     */
    public function setDownloadDir()
    {
        $this->download_dir = $this->base_dir . '/downloads';
    }

    /**
     * @return string
     */
    public function getExtractDir()
    {
        return $this->extract_dir;
    }

    /**
     * No param
     */
    public function setExtractDir()
    {
        $this->extract_dir = $this->base_dir . '/extract';
    }

    /**
     * @return string
     */
    public function getUriBase()
    {
        return $this->uri_base;
    }

    /**
     * @param string $uri_base
     */
    public function setUriBase($uri_base)
    {
        $this->uri_base = $uri_base;
    }

    /**
     * @param int $timeout
     */
    public function setFetchCurlTimeout($timeout)
    {
        $this->fetch_curl_timeout = $timeout;
    }

    /**
     * @return int
     */
    public function getFetchCurlTimeout()
    {
        return $this->fetch_curl_timeout;
    }

    /**
     * @return int
     */
    public function getUriPort()
    {
        return $this->uri_port;
    }

    /**
     * @param int $uri_port
     */
    public function setUriPort($uri_port)
    {
        $this->uri_port = $uri_port;
    }

}