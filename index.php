<?php
require 'vendor/autoload.php';

require('src/parse256x192.php');

require('src/GenerateDiff.php');
require('src/GeneratorFast.php');
require('src/GenerateMemsave.php');

require('src/Archiver.php');

if (!empty($_FILES)) {
    ini_set('max_execution_time', 300);

    // Upload animation
    if (!isset($_FILES['animation_file'])) {
        exit('No file selected.');
    }

    switch (pathinfo($_FILES['animation_file']['name'], PATHINFO_EXTENSION)) {
        case 'gif':
            $sourceType = 'gif';
            break;
        case 'zip':
            $sourceType = 'scr_zip';
            break;
        default:
            exit('Unknown animation type.');
    }

    $parser = new parse256x192(array(
        'sourceType' => $sourceType,
    ));
    if (!$parser->load($_FILES['animation_file']['tmp_name'])) {
        return false;
    }

    $frames = $parser->parseSource();

    // Generate data
    $archiver = new Archiver();
    $archiver->GenerateSources(GeneratorFast::Generate($frames), Archiver::METHOD_FAST);

    $archiver->AddFiles(GenerateMemsave($frames), 'memsave');
    $archiver->AddFiles(GenerateDiff($frames), 'diff');

    $content = $archiver->Done();

    header('Content-Type: application/zip');
    header('Content-Length: ' . strlen($content));
    header('Content-Disposition: attachment; filename="file.zip"');
    echo $content;
    exit;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ru" lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="language" content="ru"/>
    <title>Speccy animation parser</title>
    <style type="text/css">
        BODY {
            font: 15px Verdana, Geneva, sans-serif;
        }
    </style>
</head>
<body>

<form method="POST" enctype="multipart/form-data" action="">
    <fieldset>
        <legend>Speccy animation parser</legend>

        <label for="animation_file">GIF/ZIP file</label>
        <input type="file" name="animation_file" id="animation_file"/>

        <br/>
        <br/>
        <label>&nbsp;</label>
        <input type="submit" name="parse" value="Parse"/>
    </fieldset>
</form>

<p>&copy; 2022, Andrey <a href="http://nyuk.retropc.ru">nyuk</a> Marinov</p>

</body>
</html>