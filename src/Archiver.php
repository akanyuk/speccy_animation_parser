<?php

class Archiver {
    private $zip;
    private $tmpFile;

    function __construct() {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'zip');
        $this->zip = new ZipArchive();
        $this->zip->open($this->tmpFile, ZIPARCHIVE::CREATE);
    }

    function AddFiles($files, $dirPrefix = "") {
        $dirPrefix = $dirPrefix == '' ? $dirPrefix : $dirPrefix.'/';

        foreach ($files as $file) {
            $this->zip->addFromString($dirPrefix.$file['filename'], $file['data']);
        }
    }

    function Done() {
        $this->zip->close();

        $result = file_get_contents($this->tmpFile);
        unlink($this->tmpFile);

        return $result;
    }
}
