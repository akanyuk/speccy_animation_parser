<?php

use GifFrameExtractor\GifFrameExtractor;

class ParserGif {
    private $curScreen = array(); // Массив с данными текущего состояния экрана

    function __construct() {
        // initial curScreen array
        for ($i = 0; $i < 6144; $i++) {
            $this->curScreen[$i] = 0;
        }
    }

    private function error($message, $file, $line) {
        ChromePhp::error('Error: ' . $message);
        ChromePhp::error('File: ' . $file . ':' . $line);
    }

    private function proceedFrameGIF($frame) {
        $frameWidth = imagesx($frame);
        $frameHeight = imagesy($frame);

        $maxX = intval($frameWidth / 8) > 32 ? 32 : intval($frameWidth / 8);
        $maxY = $frameHeight > 216 ? 216 : $frameHeight;

        $result = array();

        for ($y = 0; $y < $maxY; $y++) {
            for ($x = 0; $x < $maxX; $x++) {
                list($byte, $address) = $this->proceedByteGIF($frame, $x, $y);
                if ($byte === false) {
                    continue;
                }

                if (!isset($result[$byte])) {
                    $result[$byte] = array();
                }

                $result[$byte][] = $address;
            }
        }

        return $result;
    }

    private function proceedByteGIF($frame, $x, $y) {
        $byte = 0;
        for ($i = 0; $i < 8; $i++) {
            $c = imagecolorsforindex($frame, imagecolorat($frame, $x * 8 + $i, $y));
            if ($c['red'] + $c['green'] + $c['blue'] > 391 || $c['alpha'] != 0) {
                $byte += pow(2, 7 - $i);
            }
        }

        $d = ($y & 0xc0) * 0x20 + ($y % 8) * 256 + ($y & 0x38) * 4 + $x;

        if ($this->curScreen[$d] == $byte) {
            return array(false, false);
        }

        $this->curScreen[$d] = $byte;

        return array($byte, $d);
    }

    public function Parse($filename) {
        if (!GifFrameExtractor::isAnimatedGif($filename)) {
            $this->error('Wrong GIF file', __FILE__, __LINE__);
            return false;
        }

        $gfe = new GifFrameExtractor();
        try {
            $gfe->extract($filename);
        } catch (Exception $e) {
            $this->error($e->getMessage(), __FILE__, __LINE__);
            return false;
        }


        $frames = $gfe->getFrameImages();
        if (count($frames) == 0) {
            $this->error('No frames in GIF file', __FILE__, __LINE__);
            return false;
        }

        $frames[] = $frames[0]; // Difference between last and first frames
        $result = array();
        foreach ($frames as $frame) {
            $result[] = $this->proceedFrameGIF($frame);
        }

        return $result;
    }
}
