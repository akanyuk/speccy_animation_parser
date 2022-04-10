<?php

function GenerateFast($frames) {
    // Removing 0-frame
    array_shift($frames);

    $generated = generateFastRes($frames);

    $generated[] = array(
        'filename' => 'player.asm',
        'data' => fastPlayer(array_keys($frames)),
    );

    $generated[] = array(
        'filename' => 'test.asm',
        'data' => fastTest(),
    );

    return $generated;
}

function generateFastRes($frames) {
    $generated = array();
    foreach ($frames as $key => $frame) {
        $output = '';
        $curA = -1;
        foreach ($frame as $byte => $addresses) {
            if ($byte == 0) {
                $output .= "\t\txor a\n";
            } elseif ($byte - $curA == 1) {
                $output .= "\t\tinc a\n";
            } else {
                $output .= "\t\tld a, " . $byte . "\n";
            }
            $curA = $byte;

            $addresses = array_reverse($addresses, true);

            $output .= generateBatchV($addresses);
            $output .= generateBatchH($addresses);

            // Формируем оставшиеся фрагменты:
            // ld (addr1), a
            foreach ($addresses as $address) {
                $output .= "\t\tld (#" . dechex(0x4000 + $address) . "), a\n";
            }
        }

        $output .= "\t\tret\n";

        // Finalize with current frame
        $generated[$key]['source'] = $output;

        $generated[$key] = array(
            'filename' => 'res/' . sprintf("%04x", $key) . '.asm',
            'data' => $output,
        );
    }

    return $generated;
}

/* Формирует из массива $source фрагменты типа:
 * ld hl, addr : ld (hl), a : inc h : ld (hl), a
*/
function generateBatchV(&$source) {
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

                for ($i = 0; $i < count($batch); $i++) {
                    $output .= "\t\tinc h\n";
                    $output .= "\t\tld (hl), a\n";
                }

                $isBatchFound = true;
            }
        }

        if (!$isBatchFound) {
            break;
        }
    }

    return $output;
}

/* Формирует из массива $source фрагменты типа:
 * ld hl, addr : ld (hl), a : inc hl : ld (hl), a
*/
function generateBatchH(&$source) {
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
                        $processedDE = true;
                    }

                    $output .= "\t\tld (#" . dechex(0x4000 + $batch[0]) . "), de\n";
                } else {
                    $output .= "\t\tld hl, #" . dechex(0x4000 + array_shift($batch)) . "\n";
                    $output .= "\t\tld (hl), a\n";

                    for ($i = 0; $i < count($batch); $i++) {
                        $output .= "\t\tinc hl\n";
                        $output .= "\t\tld (hl), a\n";
                    }
                }

                $isBatchFound = true;
            }
        }

        if (!$isBatchFound) {
            break;
        }
    }

    return $output;
}

function fastPlayer($keys) {
    $player = "anm_hl	ld hl, anm_db
	ld a, (hl) : or a : jr nz, 1f
	ld hl, anm_db			; revert to start of animation
1	ld e, (hl) : inc hl
	ld d, (hl) : inc hl
	ld (anm_hl + 1), hl
	ex de, hl
	jp (hl)
anm_db
";

    $names = array();
    $includes = array();
    foreach ($keys as $key) {
        $key = sprintf("%04x", $key);
        $includes[] = "FRAME_" . $key . "\t" . 'include "res/' . '/' . $key . '.asm"';
        $names[] = "\t" . 'dw FRAME_' . $key;
    }

    $player .= implode("\n", $names);
    $player .= "\n\tdb #00\n\n";
    $player .= implode("\n", $includes);

    return $player;
}

function fastTest() {
    return "	device zxspectrum128

	org #5d00
	ld sp, $-2
	ld hl, #5800
	ld de, #5801
	ld bc, #02ff
	ld (hl), %01000111
	ldir
	xor a : out (#fe), a
	ei
	
_LOOP	call _TEST : halt : jr _LOOP 

_TEST	include \"player.asm\"
	display /d, \"Animation size: \", $-_TEST
	savebin \"fast.bin\", _TEST, $-_TEST
	savesna \"fast.sna\", #5d00";
}