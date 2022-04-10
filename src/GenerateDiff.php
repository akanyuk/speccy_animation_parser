<?php

/**
 * @desc Generate differences between frame and previous frame as array of text files
 * @param $frames array
 * @return array
 */
function GenerateDiff($frames) {
    // Removing 0-frame
    array_shift($frames);

    $generated = array();
    foreach ($frames as $key => $frame) {
        $diffArray = array();
        foreach ($frame as $byte => $addrArray) {
            foreach ($addrArray as $address) {
                $diffArray[$address] = $byte;
            }
        }
        ksort($diffArray);

        $diff = '';
        foreach ($diffArray as $address => $byte) {
            $diff .= sprintf("%04x", $address) . ' ' . sprintf("%02x", $byte) . "\n";
        }

        $generated[] = array(
            'filename' => sprintf("%04x", $key) . '.txt',
            'data' => $diff,
        );
    }

    return $generated;
}
