<?php
class GeneratorMemsave {
    /*
     * Генерация исходника анимации.
        поток команд:
        %00xxxxxх - вывести следующие xxxxx + 1 байт (1-64) из потока данных на экран со сдвигом адреса +1
        %01xxxxxx - сдвинуть указатель адреса на xxxxxx + 1 (1-64)
        %101yxxxx - сдвинуть указатель адреса #100 байт xxxxx раз (0-15). Если установлен бит Y, то сдвигаемся еще на 128 байт
        %11111111 - конец фрейма
        далее - поток данных, где первые два байта - стартовый адрес в экране
     */
    public static function Generate($inputData) {
        $generated = array();
        foreach ($inputData as $key => $frame) {
            $generated[$key] = array(
                'frame_len' => 0,
                'bytes_aff' => 0,
                'source' => '',
                'diff' => array()
            );

            if (empty($frame)) {
                $generated[$key]['source'] = "\t" . 'db %11100000' . "\n";
                $generated[$key]['frame_len'] = 1;
                continue;
            }

            // Repack data
            $addresses = array();
            foreach ($frame as $byte => $addr_array) foreach ($addr_array as $address) {
                $addresses[$address] = $byte;
                $generated[$key]['diff'][$address] = $byte;
            }
            ksort($addresses);
            ksort($generated[$key]['diff']);
            $generated[$key]['bytes_aff'] = count($addresses);

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

            $generated[$key]['frame_len'] = count($commands_flow) + count($data_flow) + 1;
            $generated[$key]['source'] = implode("\n", $commands_flow) . "\n" . implode("\n", $data_flow);
        }

        return $generated;
    }
}