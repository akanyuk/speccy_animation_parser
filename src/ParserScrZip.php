<?php

/**
 * Class ParserScrZip
 * @desc парсинг архива с scr-файлами
 */
class ParserScrZip {
    private $scrFiles = [];         // Loaded scr files
    private $curScreen = array();   // Current screen state

    function __construct() {
        // initial curScreen array
        for ($i = 0; $i < 6912; $i++) {
            $this->curScreen[$i] = 0;
        }
    }

    private function error($message, $file, $line) {
        ChromePhp::error('Error: ' . $message);
        ChromePhp::error('File: ' . $file . ':' . $line);
    }

    private function proceedFile($scr) {
        $result = array();
        for ($address = 0; $address < 6912; $address++) {
            if (!isset($scr[$address])) continue;

            $byte = ord($scr[$address]);
            if ($this->curScreen[$address] == $byte) {
                continue;
            }

            $this->curScreen[$address] = $byte;

            if (!isset($result[$byte])) {
                $result[$byte] = array();
            }

            $result[$byte][] = $address;
        }

        return $result;
    }

    function Load($filename) {
        $zip = new ZipArchive;
        if (!$zip->open($filename)) {
            $this->error('Unable to open ZIP archive', __FILE__, __LINE__);
            return false;
        }

        $this->scrFiles = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (substr($entry, -1) == '/') {
                continue;    // skip directories
            }

            $fp = $zip->getStream($entry);
            if (!$fp) {
                $this->error('Unable to extract the file: ' . $entry, __FILE__, __LINE__);
                return false;
            }
            $frame = '';
            while (!feof($fp)) {
                $frame .= fread($fp, 2);
            }

            $this->scrFiles[] = $frame;
        }

        $zip->close();

        if (count($this->scrFiles) == 0) {
            $this->error('No files found in ZIP archive', __FILE__, __LINE__);
            return false;
        }

        $this->scrFiles[] = $this->scrFiles[0];

        return true;
    }

    function Parse() {
        $result = array();
        foreach ($this->scrFiles as $file) {
            $result[] = $this->proceedFile($file);
        }

        return $result;
    }

    function KeyFrame() {
        return count($this->scrFiles) > 0 ? $this->scrFiles[0] : false;
    }
}
