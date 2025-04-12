<?php

class GenerateFast {
    static function Generate($frames, $startAddress, $delays, $borderColor, $keyFrame) {
        // Removing 0-frame (keyframe)
        array_shift($frames);

        if (count($delays) > 1) {
            $first = array_shift($delays);
            $delays[] = $first;
        }

        $generated = self::generateFastRes($frames, $startAddress);

        $generated[] = array(
            'filename' => 'player.asm',
            'data' => self::fastPlayer(array_keys($frames)),
        );

        $generated[] = array(
            'filename' => 'test.asm',
            'data' => self::fastTest(count($frames), $delays, $borderColor, $keyFrame),
        );

        if ($keyFrame) {
            $generated[] = array(
                'filename' => 'keyframe.scr',
                'data' => $keyFrame,
            );
        }

        return $generated;
    }

    static function generateFastRes($frames, $startAddress) {
        $generated = array();
        foreach ($frames as $key => $frame) {
            $output = '';
            $curA = -1;
            foreach ($frame as $byte => $addresses) {
                if ($byte == 0) {
                    $output .= "\txor a\n";
                } elseif ($byte - $curA == 1) {
                    $output .= "\tinc a\n";
                } else {
                    $output .= "\tld a, " . $byte . "\n";
                }
                $curA = $byte;

                $addresses = array_reverse($addresses, true);

                $output .= self::generateBatchV($addresses, $startAddress);
                $output .= self::generateBatchH($addresses, $startAddress);

                // Формируем оставшиеся фрагменты:
                // ld (addr1), a
                foreach ($addresses as $address) {
                    $output .= "\tld (#" . dechex($startAddress + $address) . "), a\n";
                }
            }

            $output .= "\tret\n";

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
    static function generateBatchV(&$source, $startAddress) {
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

                    $output .= "\tld hl, #" . dechex($startAddress + array_shift($batch)) . "\n";
                    $output .= "\tld (hl), a\n";

                    for ($i = 0; $i < count($batch); $i++) {
                        $output .= "\tinc h\n";
                        $output .= "\tld (hl), a\n";
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
    static function generateBatchH(&$source, $startAddress) {
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
                            $output .= "\tld d,a\n";
                            $output .= "\tld e,a\n";
                            $processedDE = true;
                        }

                        $output .= "\tld (#" . dechex($startAddress + $batch[0]) . "), de\n";
                    } else {
                        $output .= "\tld hl, #" . dechex($startAddress + array_shift($batch)) . "\n";
                        $output .= "\tld (hl), a\n";

                        for ($i = 0; $i < count($batch); $i++) {
                            $output .= "\tinc hl\n";
                            $output .= "\tld (hl), a\n";
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

    static function fastPlayer($keys) {
        $player = 'DisplayFrame    ld hl,FRAME_0000
                jp(hl)

NextFrame	ld HL,FRAMES
	inc hl : inc hl
	ld a,l
	cp low(FRAMES_END)
	jp nz, 1f
	ld a,h
	cp high(FRAMES_END)
	jp nz,1f
	ld hl,FRAMES
1	ld (NextFrame+1),hl
	ld e, (hl)
	inc hl
	ld d,(hl)
	ex de,hl
	ld (DisplayFrame+1),hl
	ret

';

        $player .= "FRAMES\n";
        foreach ($keys as $key) {
            $keyStr = sprintf("%04x", $key);
            $player .= "\t" . 'dw FRAME_' . $keyStr . "\n";
        }
        $player .= "FRAMES_END\n";

        foreach ($keys as $key) {
            $keyStr = sprintf("%04x", $key);
            $player .= "FRAME_" . $keyStr . "\t" . 'include "res/' . $keyStr . '.asm"' . "\n";
        }

        return $player;
    }

    static function fastTest($numFrames, $delays, $borderColor, $keyFrame) {
        if ($keyFrame) {
            $scrClean = '
	ld hl, KEY_FRAME
	ld de, #4000
	ld bc, #1b00
	ldir
';

            $incKeyFrame = 'KEY_FRAME    incbin "keyframe.scr"';
        } else {
            $scrClean = '
	ld hl, #5800
	ld de, #5801
	ld bc, #02ff
	ld (hl), %01000111
	ldir
';

            $incKeyFrame = '';
        }

        if (count($delays) > 1 && end($delays) > 0) {
            $beforeStartDelay = 'ld b, '.end($delays).' : halt : djnz $-1'."\n";
        } else {
            $beforeStartDelay = '';
        }

        $delaysDb = [];
        for ($i = 0; $i < $numFrames; $i++) {
            $d = isset($delays[$i+1]) ? $delays[$i+1] : 1;
            $delaysDb[] = "db ".$d;
        }

        return '	device zxspectrum128
	org #5d00
	ld sp, $-2
	ld a, '.$borderColor.' : out (#fe), a
	'.$scrClean.'
	ei

	'.$beforeStartDelay.'
1	ld bc, '.$numFrames.'
	ld hl, DELAYS
2	push bc
	push hl
	call fast.DisplayFrame
	call fast.NextFrame
	
	pop hl
	ld b, (hl)
	inc hl
	halt : djnz $-1
	
	pop bc 
	dec bc
	ld a, b : or c : jr nz, 1b

	jr 1b

DELAYS  '.implode("\n\t", $delaysDb).'

'.$incKeyFrame.'
    
DATA	module fast
	include "player.asm"
	endmodule

	display /d, "Fast animation size: ", $-DATA
	savesna "fast.sna", #5d00
';
    }
}
