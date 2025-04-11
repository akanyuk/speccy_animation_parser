<?php

/**
 * Class ParserScrZip
 * @desc Parsing the sca file
 */
class ParserSca {
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

    private function loadSCA($filename) {
        $sca = file_get_contents($filename);

        if (strtoupper(substr($sca, 0, 3)) != "SCA") {
            $this->error('Not a SCA file', __FILE__, __LINE__);
            return false;
        }

        $version = ord(substr($sca, 3, 1));
        if ($version != 1) {
            $this->error("Unsupported version: " . $version, __FILE__, __LINE__);
            return false;
        }

        $payloadType = ord(substr($sca, 10, 1));
        if ($payloadType != 0) {
            $this->error("Unsupported payload type: " . $payloadType, __FILE__, __LINE__);
            return false;
        }

        $framesCount = ord(substr($sca, 8, 1)) + ord(substr($sca, 9, 1)) * 256;
        if ($framesCount == 0) {
            $this->error('No frames found', __FILE__, __LINE__);
            return false;
        }

        $delaysOffset = ord(substr($sca, 11, 1)) + ord(substr($sca, 12, 1)) * 256;
        $payloadOffset = $delaysOffset + $framesCount;

        $files = array();
        for ($i = 0; $i < $framesCount; $i++) {
            $frame = substr($sca, $i * 6912 + $payloadOffset, 6912);
            $files[] = $frame;
        }
        $files[] = $files[0];

        return $files;
    }

    function Parse($filename) {
        $files = $this->loadSCA($filename);
        if ($files === false) {
            return false;
        }

        $result = array();
        foreach ($files as $file) {
            $result[] = $this->proceedFile($file);
        }

        return $result;
    }

    function KeyFrame($filename) {
        $files = $this->loadSCA($filename);
        if ($files === false || count($files) == 0) {
            return false;
        }

        return $files[0];
    }
}
