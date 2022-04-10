<?php
/*
    Поток команд:
    %00xxxxxх - вывести следующие xxxxx + 1 байт (1-64) из потока данных на экран со сдвигом адреса +1
    %01xxxxxx - сдвинуть указатель адреса на xxxxxx + 1 (1-64)
    %101yxxxx - сдвинуть указатель адреса #100 байт xxxxx раз (0-15). Если установлен бит Y, то сдвигаемся еще на 128 байт
    %11111111 - конец фрейма
    далее - поток данных, где первые два байта - стартовый адрес в экране
*/
function GenerateMemsave($frames) {
    // Removing 0-frame
    array_shift($frames);

    $generated = generateMemsaveRes($frames);

    $generated[] = array(
        'filename' => 'player.asm',
        'data' => memsavePlayer(array_keys($frames)),
    );

    $generated[] = array(
        'filename' => 'test.asm',
        'data' => memsaveTest(),
    );

    return $generated;
}

function generateMemsaveRes($frames) {
    $generated = array();
    foreach ($frames as $key => $frame) {
        if (empty($frame)) {
            $generated[$key] = "\t" . 'db %11100000' . "\n";
            continue;
        }

        // Repack data
        $addresses = array();
        foreach ($frame as $byte => $addr_array) foreach ($addr_array as $address) {
            $addresses[$address] = $byte;
        }
        ksort($addresses);

        $data_flow = array();        // Поток данных сначала сохраняем в массив, потом разворачиваем в строку
        $commands_flow = array();    // Поток команд сначала сохраняем в массив, потом разворачиваем в строку
        $data_buf = array();        // Накопительный буфер для выводимых байтов
        $cur_address = 0;            // Последний обработанный экранный адрес
        foreach ($addresses as $address => $byte) {
            // Initial address
            if ($cur_address == 0) {
                $data_flow[] = "\t" . 'dw #' . sprintf("%04x", 0x4000 + $address);
                $data_buf[] = $byte;
                $cur_address = $address + 1;
                continue;
            }

            // Simple add $data_buf value
            if ($address == $cur_address && count($data_buf) < 32) {
                $data_buf[] = $byte;
                $cur_address = $address + 1;
                continue;
            }

            if (($address != $cur_address && !empty($data_buf)) || count($data_buf) == 64) {
                $commands_flow[] = "\t" . 'db %00' . sprintf("%06s", decbin(count($data_buf) - 1));

                foreach ($data_buf as $b) {
                    $data_flow[] = "\t" . 'db #' . sprintf("%02x", $b);
                }
                $data_buf = array();
            }

            while ($address > $cur_address + 128) {
                $delta = floor(($address - $cur_address) / 256);
                if ($delta > 15) $delta = 15;
                $cur_address += $delta * 256;
                $delta2 = $address >= $cur_address + 128 ? 0x10 : 0;
                $cur_address += $delta2 ? 128 : 0;

                $commands_flow[] = "\t" . 'db %101' . sprintf("%05s", decbin($delta + $delta2));
            }

            while ($cur_address != $address) {
                $delta = $address - $cur_address > 64 ? 64 : $address - $cur_address;
                $commands_flow[] = "\t" . 'db %01' . sprintf("%06s", decbin($delta - 1));
                $cur_address += $delta;
            }

            $data_buf[] = $byte;
            $cur_address++;
        }

        // Extract last buffer
        $commands_flow[] = "\t" . 'db %00' . sprintf("%06s", decbin(count($data_buf) - 1));
        foreach ($data_buf as $b) {
            $data_flow[] = "\t" . 'db #' . sprintf("%02x", $b);
        }

        // End of frame
        $commands_flow[] = "\t" . 'db %11111111';

        $generated[$key] = array(
            'filename' => 'res/' . sprintf("%04x", $key) . '.asm',
            'data' => implode("\n", $commands_flow) . "\n" . implode("\n", $data_flow),
        );
    }

    return $generated;
}

function memsavePlayer($keys) {
    $player = "anm_hl	ld hl, anima_proc
	ld a, (hl) : or a : jr nz, lab1
	ld hl, anima_proc
lab1	ld e, (hl) : inc hl
	ld d, (hl) : inc hl
	ld (anm_hl + 1), hl
	ex de, hl
						; determine data flow start
	push hl
lab2	ld a, (hl) : inc hl
	inc a
	jp nz, lab2
	pop ix
						; set start address
	ld e,(hl) : inc hl
	ld d,(hl) : inc hl
	ld b,a
cycle
	ld  a, (ix + 0)
	inc ix

	ld c,a
	rla
	jr nc,lab3

	rlca		; cp  #80
	ret c		; ret nc
jmp100
	ld  a,c
	and #0f
	add a,d : ld d,a
	bit 4,c
	jr  z, cycle
	ld  c, #80				; additional jump +128 bytes
	ex  de, hl
	add hl, bc
	ex  de, hl
	jp  cycle
						; end of frame		
lab3	rlca
	jr c,anc_jmp
	inc c					; copy N bytes from flow to screen
	ldir
	jp cycle
anc_jmp						; jump screen address
	res 6,c
	inc c
	ex  de, hl
	add hl, bc
	ex  de, hl
	jp  cycle
anima_proc
";

    $names = array();
    $includes = array();
    foreach ($keys as $key) {
        $key = sprintf("%04x", $key);

        $includes[] = "FRAME_" . $key . "\t" . 'include "res/' . '/' . $key . '.asm"';
        $names[] = "\t" . 'dw FRAME_' . $key;
    }

    $player .= implode("\n", $names);
    $player .= "\n\tdw #0000\n\n";
    $player .= implode("\n", $includes);

    return $player;
}

function memsaveTest() {
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
	savebin \"memsave.bin\", _TEST, $-_TEST
	savesna \"memsave.sna\", #5d00";
}