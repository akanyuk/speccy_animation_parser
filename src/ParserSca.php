<?php

/**
 * Class ParserScrZip
 * @desc Parsing the sca file
 */
class ParserSca {
    private $scrFiles = [];         // Loaded scr files
    private $delays = [];           // Delays
    private $recommendedBorder = 0; // Recommended border color (1 byte)

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

        $payloadType = ord(substr($sca, 11, 1));
        if ($payloadType != 0) {
            $this->error("Unsupported payload type: " . $payloadType, __FILE__, __LINE__);
            return false;
        }

        $framesCount = ord(substr($sca, 9, 1)) + ord(substr($sca, 10, 1)) * 256;
        if ($framesCount == 0) {
            $this->error('No frames found', __FILE__, __LINE__);
            return false;
        }

        $this->recommendedBorder = ord(substr($sca, 8, 1));

        $delaysOffset = ord(substr($sca, 12, 1)) + ord(substr($sca, 13, 1)) * 256;
        $payloadOffset = $delaysOffset + $framesCount;

        $this->scrFiles = [];
        for ($i = 0; $i < $framesCount; $i++) {
            $frame = substr($sca, $i * 6912 + $payloadOffset, 6912);
            $this->scrFiles[] = $frame;
        }

        $this->delays = [];
        for ($i = 0; $i < $framesCount; $i++) {
            $this->delays[] = ord(substr($sca, $delaysOffset + $i, 1));
        }

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

    function Delays() {
        return $this->delays;
    }

    function RecommendedBorder() {
        return $this->recommendedBorder;
    }
}
