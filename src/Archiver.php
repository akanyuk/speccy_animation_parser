<?php

namespace SpeccyAnimationParser;

use ZipArchive;

class Archiver {
    private $zip;
    private $tmpFile;

    function __construct() {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'zip');
        $this->zip = new ZipArchive();
        $this->zip->open($this->tmpFile, ZIPARCHIVE::CREATE);
    }

    function AddFiles($files, $prefix = "") {
        $prefix = $prefix == '' ? $prefix : $prefix.'/';

        foreach ($files as $file) {
            $this->zip->addFromString($prefix.$file['filename'], $file['data']);
        }
    }

    function Done() {
        $tpl = 'sjasmplus --inc=fast\. fast\test.asm' . "\n";
        $tpl .= 'sjasmplus --inc=memsave\. memsave\test.asm';
        $this->zip->addFromString('make.cmd', $tpl);

        $this->zip->close();

        $result = file_get_contents($this->tmpFile);
        unlink($this->tmpFile);

        return $result;
    }
}
