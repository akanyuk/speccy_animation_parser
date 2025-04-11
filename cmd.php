<?php

// All the command line classes are in the Garden\Cli namespace.
use Garden\Cli\Cli;

// Require composer's autoloader.
require_once 'vendor/autoload.php';

// Define the cli options.
$cli = Cli::create()
    ->command('*')
    ->arg('input', 'Input filename', true)
    ->arg('output', 'Output dir')
    ->command('diff')
    ->description('Creating text files with differences between frames')
    ->command('fast')
    ->description('Creating animation with fast method')
    ->opt('address:a', 'Screen address. 16384 by default', false, 'integer')
    ->opt('player', 'Generating player', false, 'bool')
    ->opt('test:t', 'Generating test.asm', false, 'bool')
    ->command('memsave')
    ->description('Creating animation with memsave method')
    ->opt('address:a', 'Screen address. 16384 by default', false, 'integer')
    ->opt('player:p', 'Generating player.asm', false, 'bool')
    ->opt('test:t', 'Generating test.asm', false, 'bool');

try {
    $args = $cli->parse($argv);
} catch (Exception $e) {
    exit;
}

$inputFile = $args->getArg('input');
if (!file_exists($inputFile)) {
    exit('File not found: ' . $inputFile);
}

$screenAddress = $args->getOpt('address');
if (!$screenAddress) {
    $screenAddress = 16384;
}

$withPlayer = $args->getOpt('player') === null || $args->getOpt('player') === true;
$withTest = $args->getOpt('test') === null || $args->getOpt('test') === true;

switch (pathinfo($inputFile, PATHINFO_EXTENSION)) {
    case 'gif':
        $parser = new ParserGif();
        $frames = $parser->Parse($inputFile);
        if ($frames === false) {
            exit("Parse GIF error");
        }
        $keyFrame = false;
        $delays = [];
        break;
    case 'zip':
        $parser = new ParserScrZip();
        if (!$parser->Load($inputFile)) {
            exit("Parse SCR files in ZIP archive error");
        }
        $frames = $parser->Parse();
        $keyFrame = $parser->KeyFrame();
        $delays = [];
        break;
    case 'sca':
        $parser = new ParserSca();
        if (!$parser->Load($inputFile)) {
            exit("Parse SCR files in ZIP archive error");
        }
        $frames = $parser->Parse();
        $keyFrame = $parser->KeyFrame();
        $delays = $parser->Delays();
        break;
    default:
        exit('Unknown input file type');
}

switch ($args->getCommand()) {
    case 'diff':
        $files = GenerateDiff::Generate($frames);
        break;
    case 'fast':
        $files = GenerateFast::Generate($frames, $screenAddress, $delays, $keyFrame);
        break;
    case 'memsave':
        $files = GenerateMemsave::Generate($frames, $screenAddress, $delays, $keyFrame);
        break;
    default:
        exit('Unknown command');
}

$outputDir = trim($args->getArg('output'), DIRECTORY_SEPARATOR);
if ($outputDir !== "" && $outputDir !== ".") {
    @mkdir($outputDir, 0777, true);
} else {
    $outputDir = ".";
}

foreach ($files as $file) {
    if ($file['filename'] === 'player.asm' && !$withPlayer) {
        continue;
    }

    if ($file['filename'] === 'test.asm' && !$withTest) {
        continue;
    }

    $fileDir = pathinfo($file['filename'], PATHINFO_DIRNAME);
    if ($fileDir != "") {
        @mkdir($outputDir . DIRECTORY_SEPARATOR . $fileDir, 0777, true);
    }

    $filePath = $outputDir . DIRECTORY_SEPARATOR . $file['filename'];
    if (file_put_contents($filePath, $file['data']) === false) {
        exit('Write file "' . $filePath . '" failed');
    }
}
