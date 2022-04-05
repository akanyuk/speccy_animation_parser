<?php

class GeneratorFast {
    public static function Generate($inputData) {
        $generated = array();
        foreach ($inputData as $key => $frame) {
            $generated[$key] = array(
                'frame_len' => 0,
                'bytes_aff' => 0,
                'source' => '',
                'diff' => array()
            );

            $output = '';

            $curA = -1;
            foreach ($frame as $byte => $addresses) {
                // add diff
                foreach ($addresses as $address) {
                    $generated[$key]['diff'][$address] = $byte;
                }

                if ($byte == 0) {
                    $output .= "\t\txor a\n";
                    $generated[$key]['frame_len']++;
                } elseif ($byte - $curA == 1) {
                    $output .= "\t\tinc a\n";
                    $generated[$key]['frame_len']++;
                } else {
                    $output .= "\t\tld a, " . $byte . "\n";
                    $generated[$key]['frame_len'] += 2;
                }
                $curA = $byte;

                $addresses = array_reverse($addresses, true);

                $output .= self::generateBatchV($addresses, $generated[$key]['frame_len']);
                $output .= self::generateBatchH($addresses, $generated[$key]['frame_len']);

                // Формируем оставшиеся фрагменты:
                // ld (addr1), a
                foreach ($addresses as $address) {
                    $output .= "\t\tld (#" . dechex(0x4000 + $address) . "), a\n";
                    $generated[$key]['frame_len'] += 3;
                }
            }

            $output .= "\t\tret\n";
            $generated[$key]['frame_len']++;

            // Finalize with current frame
            $generated[$key]['source'] = $output;
            ksort($generated[$key]['diff']);
            $generated[$key]['bytes_aff'] = count($generated[$key]['diff']);
        }

        return $generated;
    }

    /* Формирует из массива $source фрагменты типа:
     * ld hl, addr : ld (hl), a : inc h : ld (hl), a
    */
    private static function generateBatchV(&$source, &$counter) {
        sort($source);

        $output = '';
        while (true) {
            $isBatchFound = false;

            foreach ($source as $address) {
                $batch = array();

                while (true) {
                    if (!in_array($address, $source)) break;

                    $batch[] = $address;
                    $address += 0x100;
                }

                // Batch end
                if (count($batch) > 2) {
                    // Remove batch addresses from source
                    foreach ($source as $k => $a) {
                        if (in_array($a, $batch)) unset($source[$k]);
                    }

                    $output .= "\t\tld hl, #" . dechex(0x4000 + array_shift($batch)) . "\n";
                    $output .= "\t\tld (hl), a\n";
                    $counter += 4;

                    for ($i = 0; $i < count($batch); $i++) {
                        $output .= "\t\tinc h\n";
                        $output .= "\t\tld (hl), a\n";
                        $counter += 2;
                    }

                    $isBatchFound = true;
                }
            }

            if (!$isBatchFound) break;
        }

        return $output;
    }

    /* Формирует из массива $source фрагменты типа:
     * ld hl, addr : ld (hl), a : inc hl : ld (hl), a
    */
    private static function generateBatchH(&$source, &$counter) {
        sort($source);

        $output = '';
        $processedDE = false;

        while (true) {
            $isBatchFound = false;

            foreach ($source as $address) {
                $batch = array();

                while (true) {
                    if (!in_array($address, $source)) break;

                    $batch[] = $address;
                    $address++;
                }

                // Batch end
                if (count($batch) >= 2) {
                    // Remove batch addresses from source
                    foreach ($source as $k => $a) {
                        if (in_array($a, $batch)) unset($source[$k]);
                    }

                    if (count($batch) == 2) {
                        if (!$processedDE) {
                            $output .= "\t\tld d,a\n";
                            $output .= "\t\tld e,a\n";
                            $counter += 2;
                            $processedDE = true;
                        }

                        $output .= "\t\tld (#" . dechex(0x4000 + $batch[0]) . "), de\n";
                        $counter += 4;
                    } else {
                        $output .= "\t\tld hl, #" . dechex(0x4000 + array_shift($batch)) . "\n";
                        $output .= "\t\tld (hl), a\n";
                        $counter += 4;


                        for ($i = 0; $i < count($batch); $i++) {
                            $output .= "\t\tinc hl\n";
                            $output .= "\t\tld (hl), a\n";
                            $counter += 2;
                        }
                    }

                    $isBatchFound = true;
                }
            }

            if (!$isBatchFound) break;
        }

        return $output;
    }
}

