<?php

class Archiver {
    const METHOD_FAST = 1;
    const METHOD_MEMSAVE = 2;

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
        // Adding make.cmd
        $tpl = 'sjasmplus --inc=fast\. fast\test-animation.asm' . "\n";
        $tpl .= 'sjasmplus --inc=memsave\. memsave\test.asm';
        $this->zip->addFromString('make.cmd', $tpl);

        $this->zip->close();

        $result = file_get_contents($this->tmpFile);
        unlink($this->tmpFile);

        return $result;
    }

    private function error($message, $file, $line) {
        ChromePhp::error('Error: ' . $message);
        ChromePhp::error('File: ' . $file . ':' . $line);
    }

    private function getSnippet($snippet_name, $options = array()) {
        if (!$xml = simplexml_load_file(dirname(__FILE__) . '/snippets/' . $snippet_name . '.xml')) {
            $this->error('Wrong XML file.', __FILE__, __LINE__);
            return false;
        }

        $snippet = array(
            'template' => (string)$xml->template,
            'length' => (int)$xml->length
        );

        foreach ($xml->params->param as $param) {
            $varname = (string)$param->varname;
            $value = isset($options['params'][$varname]) ? $options['params'][$varname] : (string)$param->default;
            $snippet['template'] = str_replace('%' . $varname . '%', $value, $snippet['template']);
        }

        // Set function name
        if (isset($options['function_name'])) {
            $snippet['template'] = $options['function_name'] . "\n" . $snippet['template'];
        }

        return $snippet;
    }

    public function GenerateSources($frames, $method) {
        // remove 0-frame
        array_shift($frames);

        $namePrefix = 'a' . substr(md5(serialize($frames)), 29);
        $methodPrefix = $method == self::METHOD_MEMSAVE ? 'memsave' : 'fast';

        // Generate $dataFlow array
        $dataFlow = array();
        $animaFrames = array();
        foreach ($frames as $key => $frame) {
            $key = sprintf("%04x", $key);

            $procName = 'a' . $namePrefix . '_' . $key;

            $dataFlow[] = $procName . "\t" . 'include "res/' . $namePrefix . '/' . $key . '.asm"';
            $this->zip->addFromString($methodPrefix . '/res/' . $namePrefix . '/' . $key . '.asm', $frame['source']);

            $animaFrames[] = "\t" . 'dw ' . $procName;
        }

        // Generate animation function
        $snippet = $this->getSnippet('player-' . $methodPrefix, array(
            'params' => array(
                'FRAMES' => implode("\n", $animaFrames)
            ),
        ));

        // Main code final generation
        $this->zip->addFromString($methodPrefix . '/player-' . $methodPrefix . '-' . $namePrefix . '.asm', $snippet['template'] . implode("\n", $dataFlow));

        $snippet = $this->getSnippet('test-animation', array(
            'params' => array(
                'method' => $methodPrefix,
                'prefix' => $namePrefix
            ),
        ));
        $this->zip->addFromString($methodPrefix . '/test-animation.asm', $snippet['template']);
    }
}
