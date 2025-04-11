<?php

/*
    Первые два байта - начальный адрес экрана, далее команды:
    %00xxxxxх - вывести следующие xxxxx + 1 байт (1-64) на экран, сдвинув указатель адреса экрана
    %01xxxxxx - сдвинуть указатель адреса экрана на xxxxxx + 1 (1-64)
    %101yxxxx - сдвинуть указатель адреса экрана #100 байт xxxxx раз (0-15). Если установлен бит Y, то сдвигаемся еще на 128 байт
    %11xxxxxx - конец фрейма
*/

class GenerateMemsave {
    static function Generate($frames, $startAddress = 0, $keyFrame = false) {
        // Removing 0-frame
        array_shift($frames);

        $generated = self::generateMemsaveRes($frames);

        $generated[] = array(
            'filename' => 'player.asm',
            'data' => self::memsavePlayer(array_keys($frames)),
        );

        $generated[] = array(
            'filename' => 'test.asm',
            'data' => self::memsaveTest($startAddress, $keyFrame),
        );

        if ($keyFrame) {
            $generated[] = array(
                'filename' => 'keyframe.scr',
                'data' => $keyFrame,
            );
        }

        return $generated;
    }

    static function generateMemsaveRes($frames) {
        $generated = array();
        foreach ($frames as $key => $frame) {
            if (empty($frame)) {
                $generated[$key] = array(
                    'filename' => 'res/' . sprintf("%04x", $key) . '.asm',
                    'data' => '
    dw 0
    db %11100000',
                );
                continue;
            }

            // Repack frame
            $addresses = array();
            foreach ($frame as $byte => $addrArray) {
                foreach ($addrArray as $address) {
                    $addresses[$address] = $byte;
                }
            }
            ksort($addresses);

            $dataFlow = array();        // Поток данных сначала сохраняем в массив, потом разворачиваем в строку
            $dataBuf = array();         // Накопительный буфер для выводимых байтов
            $curAddress = 0;            // Последний обработанный экранный адрес
            foreach ($addresses as $address => $byte) {
                // Initial address
                if ($curAddress == 0) {
                    $dataFlow[] = "\t" . 'dw #' . sprintf("%04x", $address);
                    $dataBuf[] = $byte;
                    $curAddress = $address + 1;
                    continue;
                }

                // Simple add $data_buf value
                if ($address == $curAddress && count($dataBuf) < 32) {
                    $dataBuf[] = $byte;
                    $curAddress = $address + 1;
                    continue;
                }

                if (($address != $curAddress && !empty($dataBuf)) || count($dataBuf) == 64) {
                    $dataFlow[] = "\t" . 'db %00' . sprintf("%06s", decbin(count($dataBuf) - 1));

                    foreach ($dataBuf as $b) {
                        $dataFlow[] = "\t" . 'db #' . sprintf("%02x", $b);
                    }
                    $dataBuf = array();
                }

                while ($address > $curAddress + 128) {
                    $delta = floor(($address - $curAddress) / 256);
                    if ($delta > 15) $delta = 15;
                    $curAddress += $delta * 256;
                    $delta2 = $address >= $curAddress + 128 ? 0x10 : 0;
                    $curAddress += $delta2 ? 128 : 0;

                    $dataFlow[] = "\t" . 'db %101' . sprintf("%05s", decbin($delta + $delta2));
                }

                while ($curAddress != $address) {
                    $delta = min($address - $curAddress, 64);
                    $dataFlow[] = "\t" . 'db %01' . sprintf("%06s", decbin($delta - 1));
                    $curAddress += $delta;
                }

                $dataBuf[] = $byte;
                $curAddress++;
            }

            // Extract last buffer
            $dataFlow[] = "\t" . 'db %00' . sprintf("%06s", decbin(count($dataBuf) - 1));
            foreach ($dataBuf as $b) {
                $dataFlow[] = "\t" . 'db #' . sprintf("%02x", $b);
            }

            // End of frame
            $dataFlow[] = "\t" . 'db %11111111';

            $generated[$key] = array(
                'filename' => 'res/' . sprintf("%04x", $key) . '.asm',
                'data' => implode("\n", $dataFlow),
            );
        }

        return $generated;
    }

    static function memsavePlayer($keys) {
        $player = 'play	; DE = starting screen address (#4000, #c000, etc...)
        ld	hl,FRAME_0000
        ld	a,h : sub high FRAME_END : or l : sub low FRAME_END
        jr	nz,1f
        ld	hl,FRAME_0000
1	ld	c,(hl)  :  inc hl	; Screen shift
        ld	b,(hl)  :  inc hl
        ex	de,hl
        add	hl,bc
        ld	b,0
cycle	ld	a,(de)  :  inc de
        ld	c,a
        add	a
        jr	nc,2f
        jp	m, nextFrame
        ; long jump
        ld	a,c
        and	#0f
        add	a,h : ld h,a
        bit	4,c
        jr	z, cycle
        ld	c,#80
        add	hl,bc
        jp	cycle
    
2	jp	m,nearJmp
        inc	c
        ex	de,hl
        ldir
        ex	de,hl
        jp	cycle
    
nearJmp	res	6,c
        inc	c
        add	hl,bc
        jp	cycle
nextFrame   ld	(play+1),de
        ret

';

        foreach ($keys as $key) {
            $keyStr = sprintf("%04x", $key);
            $player .= "FRAME_" . $keyStr . "\t" . 'include "res/' . $keyStr . '.asm"' . "\n";
        }

        $player .= "FRAME_END\n";
        return $player;
    }

    static function memsaveTest($screenAddress, $keyFrame = false) {
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

        return '	device zxspectrum128

	org #5d00
	'.$scrClean.'

1   ei : halt
    ld de, #' . sprintf("%04x", $screenAddress) . '
	ld a,1 : out (#fe),a
	call	player
	xor a : out (#fe),a
	jp	1b

'.$incKeyFrame.'

player	module memsave
	include "player.asm"
	endmodule

	display /d, "Memsave animation size: ", $-player
	savesna "memsave.sna", #5d00
';
    }
}