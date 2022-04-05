<?php

// Только парсер, без генерации кода в отличии от предыдущей версии
class parse256x192 {
    private $source = array();            // Массив, в который будет загружена исходная анимация для парсинга
    private $sourceType = 'gif';        // Идентификатор типа исходной анимации
    private $curScreen = array();        // Массив с данными текущего состояния экрана

    function __construct($config) {
        $this->sourceType = isset($config['sourceType']) ? $config['sourceType'] : $this->sourceType;

        // initial curScreen array
        for ($i = 0; $i < 6144; $i++) {
            $this->curScreen[$i] = 0;
        }
    }

    private function error($message, $file, $line) {
        ChromePhp::error('Error: ' . $message);
        ChromePhp::error('File: ' . $file . ':' . $line);
    }

    private function proceedFrameSCR($scr) {
        $result = array();
        for ($address = 0; $address < 6912; $address++) {
            if (!isset($scr[$address])) continue;

            $byte = ord($scr[$address]);
            if ($this->curScreen[$address] == $byte) continue;

            $this->curScreen[$address] = $byte;
            if (!isset($result[$byte])) $result[$byte] = array();
            $result[$byte][] = $address;
        }

        return $result;
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
                if ($byte === false) continue;

                if (!isset($result[$byte])) $result[$byte] = array();
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
                continue;
            }

            $byte += pow(2, 7 - $i);
        }

        $d = ($y & 0xc0) * 0x20 + ($y % 8) * 256 + ($y & 0x38) * 4 + $x;

        if ($this->curScreen[$d] == $byte) {
            return array(false, false);
        }

        $this->curScreen[$d] = $byte;

        return array($byte, $d);
    }

    private function loadSCRZIP($filename) {
        $zip = new ZipArchive;
        if (!$zip->open($filename)) {
            $this->error('Unable to open ZIP archive', __FILE__, __LINE__);
            return false;
        }

        $total = $key = 0;
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

            $this->source[] = array(
                'frame' => $frame,
            );

            $total++;
            $key++;
        }

        $zip->close();

        $this->source[] = array(
            'frame' => $this->source[0]['frame'],
        );

        return true;
    }

    /* Load frames from GIF file to $this->source
     *	Available options:
     *	from - from frame
     *	count - number of loaded frames
     */
    private function loadGIF($filename, $options = array()) {
        if (!GifFrameExtractor\GifFrameExtractor::isAnimatedGif($filename)) {
            $this->error('Wrong GIF file', __FILE__, __LINE__);
            return false;
        }

        $this->source = array();

        $gfe = new GifFrameExtractor\GifFrameExtractor();
        try {
            $gfe->extract($filename);
        } catch (Exception $e) {
            $this->error($e->getMessage(), __FILE__, __LINE__);
            return false;
        }

        $frames = $gfe->getFrameImages();
        $total = count($frames);

        // Partial loading setup
        $from = isset($options['from']) ? intval($options['from']) : 0;
        if ($from) {
            $this->source[] = array(
                'frame' => $frames[$from - 1],
            );
        }

        $to = isset($options['count']) ? $from + intval($options['count']) - 1 : $total - 1;

        $key = 0;
        foreach ($frames as $key => $frame) {
            if ($key < $from) {
                continue;
            }

            $this->source[] = array(
                'frame' => $frame,
            );

            if ($key >= $to) break;
        }

        if ($key == $total - 1) {
            $this->source[] = array(
                'frame' => $frames[0],
            );
        }

        return true;
    }


    function load($filename, $options = array()) {
        switch ($this->sourceType) {
            case 'gif':
                return $this->loadGIF($filename, $options);
            case 'scr_zip':
                return $this->loadSCRZIP($filename);
            default:
                $this->error('Unknown source type.', __FILE__, __LINE__);
                return false;
        }
    }

    function parseSource() {
        $result = array();
        foreach ($this->source as $f) {
            switch ($this->sourceType) {
                case 'gif':
                    $parsed = $this->proceedFrameGIF($f['frame']);
                    break;
                case 'scr_zip':
                    $parsed = $this->proceedFrameSCR($f['frame']);
                    break;
                default:
                    $parsed = array();
                    break;
            }

            $result[] = $parsed;
        }

        return $result;
    }
}
