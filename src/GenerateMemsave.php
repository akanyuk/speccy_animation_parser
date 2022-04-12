<?php

namespace SpeccyAnimationParser;

/*
    Первые два байта - начальный адрес экрана, далее команды:
    %00xxxxxх - вывести следующие xxxxx + 1 байт (1-64) на экран, сдвинув указатель адреса экрана
    %01xxxxxx - сдвинуть указатель адреса экрана на xxxxxx + 1 (1-64)
    %101yxxxx - сдвинуть указатель адреса экрана #100 байт xxxxx раз (0-15). Если установлен бит Y, то сдвигаемся еще на 128 байт
    %11xxxxxx - конец фрейма
*/
function GenerateMemsave($frames, $startAddress = 0) {
    // Removing 0-frame
    array_shift($frames);

    $generated = generateMemsaveRes($frames);

    $generated[] = array(
        'filename' => 'player.asm',
        'data' => memsavePlayer(array_keys($frames)),
    );

    $generated[] = array(
        'filename' => 'test.asm',
        'data' => memsaveTest($startAddress),
    );

    return $generated;
}

function generateMemsaveRes($frames) {
    $generated = array();
    foreach ($frames as $key => $frame) {
        if (empty($frame)) {
            $generated[$key] = "\t" . 'dw 0' . "\n";
            $generated[$key] .= "\t" . 'db %11100000' . "\n";
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
                $delta = $address - $curAddress > 64 ? 64 : $address - $curAddress;
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

function memsavePlayer($keys) {
    $player = '	; de : starting screen address (#4000, #c000, etc...)
DisplayFrame	ld HL,FRAME_0000
	ld c,(hl) : inc hl ; Screen shift
	ld b,(hl) : inc hl
	ex de,hl
	add hl,bc
	ex de,hl
	xor a : ld b,a
cycle	ld  a,(hl)
	inc hl
	ld c,a
	rla
	jp nc,2f
	rlca		; cp  #80
	ret c
	; long jump
	ld  a,c
	and #0f
	add a,d : ld d,a
	bit 4,c
	jp  z, cycle
	ld a,#80
	add e
	ld e,a
	jp nc,cycle
	inc d
	jp  cycle
2	rlca
	jp c,nearJmp
	inc c		; copy N bytes to screen
	ldir
	jp cycle
nearJmp	ld a,c
	res 6,a
	inc a
	add e
	ld e,a
	jp nc,cycle
	inc d
	jp  cycle

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

function memsaveTest($screenAddress) {
    return '	device zxspectrum128

	org #5d00
	ld sp, $-2
	ld hl, #5800
	ld de, #5801
	ld bc, #02ff
	ld (hl), %01000111
	ldir
	xor a : out (#fe), a
	ei

1	ld de, #' . sprintf("%04x", $screenAddress) . '
	call memsave.DisplayFrame
	call memsave.NextFrame
	halt
	jr 1b

DATA	module memsave
	include "player.asm"
	endmodule

	display /d, "Animation size: ", $-DATA
	savebin "memsave.bin", DATA, $-DATA
	savesna "memsave.sna", #5d00
';
}