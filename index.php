<?php

use SpeccyAnimationParser\Archiver;
use SpeccyAnimationParser\ParserGif;
use SpeccyAnimationParser\ParserScrZip;
use function SpeccyAnimationParser\GenerateDiff;
use function SpeccyAnimationParser\GenerateFast;
use function SpeccyAnimationParser\GenerateMemsave;

require 'vendor/autoload.php';

require('src/Archiver.php');
require('src/ParserGif.php');
require('src/ParserScrZip.php');
require('src/GenerateDiff.php');
require('src/GenerateFast.php');
require('src/GenerateMemsave.php');

if (!empty($_FILES)) {
    ini_set('max_execution_time', 300);

    if (!isset($_FILES['animation_file'])) {
        exit('No file selected.');
    }

    switch (pathinfo($_FILES['animation_file']['name'], PATHINFO_EXTENSION)) {
        case 'gif':
            $parser = new ParserGif();
            $frames = $parser->Parse($_FILES['animation_file']['tmp_name']);
            if ($frames === false) {
                exit("Parse GIF error");
            }
            break;
        case 'zip':
            $parser = new ParserScrZip();
            $frames = $parser->Parse($_FILES['animation_file']['tmp_name']);
            if ($frames === false) {
                exit("Parse SCR files in ZIP archive error");
            }
            break;
        default:
            exit('Unknown animation type.');
    }

    $startAddress = isset($_POST['screen_address']) ? intval($_POST['screen_address']) : 0;

    $archiver = new Archiver();
    $archiver->AddFiles(GenerateFast($frames, $startAddress), 'fast');
    $archiver->AddFiles(GenerateMemsave($frames, $startAddress), 'memsave');
    $archiver->AddFiles(GenerateDiff($frames), 'diff');

    $content = $archiver->Done();

    header('Content-Type: application/zip');
    header('Content-Length: ' . strlen($content));
    header('Content-Disposition: attachment; filename="file.zip"');
    echo $content;
    exit;
}
?>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="language" content="ru"/>
    <title>Speccy animation parser</title>
    <style type="text/css">
        BODY {
            font: 15px Verdana, Geneva, sans-serif;
        }

        LABEL {
            display: block;
            padding: 1rem 0 0.5rem 0;
        }
    </style>
</head>
<body>

<form method="POST" enctype="multipart/form-data" action="">
    <fieldset>
        <legend>Speccy animation parser</legend>

        <label for="animation_file">GIF/ZIP file</label>
        <input type="file" name="animation_file" id="animation_file"/>

        <label for="screen_address">Screen start address: 16384 (default), 49152 (#c000), or any other integer. Included test player work properly only with 16384 value</label>
        <input type="number" min="0" max="65535" name="screen_address" value="16384" id="screen_address"/>

        <label></label>
        <input type="submit" name="parse" value="Parse"/>
    </fieldset>
</form>

<p><small>&copy; 2022, Andrey <a href="http://nyuk.retropc.ru">nyuk</a> Marinov</small></p>

</body>
</html>