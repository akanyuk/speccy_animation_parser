<?php

namespace SpeccyAnimationParser;

use ChromePhp;
use ZipArchive;

/**
 * Class ParserScrZip
 * @desc парсинг архива с scr-файлами
 */
class ParserScrZip {
    private $curScreen = array();        // Массив с данными текущего состояния экрана

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

    private function loadSCRZIP($filename) {
        $files = array();
        $zip = new ZipArchive;
        if (!$zip->open($filename)) {
            $this->error('Unable to open ZIP archive', __FILE__, __LINE__);
            return false;
        }

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

            $files[] = $frame;
        }

        $zip->close();

        if (count($files) == 0) {
            $this->error('No files found in ZIP archive', __FILE__, __LINE__);
            return false;
        }

        $files[] = $files[0];
        return $files;
    }

    function Parse($filename) {
        $files = $this->loadSCRZIP($filename);
        if ($files == false) {
            return false;
        }

        $result = array();
        foreach ($files as $file) {
            $result[] = $this->proceedFile($file);
        }

        return $result;
    }
}
