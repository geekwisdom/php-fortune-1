<?php
/**
 * Author: Jak Wings (https://github.com/jakwings)
 *
 * Description: It is just a PHP script inspired by http://www.aasted.org/quote/
 */
class Fortune {

    public function QuoteFromDir($dir) {
        $files = array_filter(glob($dir . '/*', GLOB_NOSORT) ?: array(), function ($file) {
            return is_file($file) and substr(strrchr($file, '.'), 1) !== 'dat';
        });
        if (empty($files)) {
            return;
        }
        $amount = 0;
        $amounts = array();
        foreach ($files as $index => $file) {
            if (!file_exists($file . '.dat')) {
                $this->CreateIndexFile($file);
            }
            $amount += $this->GetNumberOfQuotes($file);
            $amounts[$index] = $amount;
        }
        if ($amount < 1) {
            return;
        }
        $n = mt_rand(1, $amount);
        $index = 0;
        while ($amounts[$index] < $n)  {
            $index += 1;
        }
        return $this->GetRandomQuote($files[$index]);
    }

    public function GetNumberOfQuotes($file) {
        $fh = fopen($file . '.dat', 'rb');
        fseek($fh, 4, SEEK_SET);
        $number = $this->_ReadUint32($fh);
        fclose($fh);
        return $number;
    }

    public function GetExactQuote($file, $index) {
        $index_file = $file . '.dat';
        if (($fh = fopen($index_file, 'rb')) === FALSE) {
            return 'FORTUNE: Failed to open index file.';
        }
        fseek($fh, 4 * (6 + $index), SEEK_SET);
        $physical_index = $this->_ReadUint32($fh);
        fclose($fh);
        if (($fh = fopen($file, 'rb')) === FALSE) {
            return 'FORTUNE: Failed to open source file.';
        }
        $quote = $this->_GetQuote($fh, $physical_index);
        fclose($fh);
        return $quote;
    }

    public function GetRandomQuote($file) {
        $number = $this->GetNumberOfQuotes($file);
        if ($number < 1) {
            return;
        }
        $index = mt_rand(1, $number - 1);
        return $this->GetExactQuote($file, $index);
    }

    public function CreateIndexFile($file) {
        // Generate indices.
        if (($fh = fopen($file, 'r')) === FALSE) {
            throw new Exception('FORTUNE: Failed to load source file.');
        }
        $length = 0;
        $max_length = 2147483647;  // 2^31 - 1
        $eol_length = strlen(PHP_EOL);
        $longest = 0;
        $shortest = $max_length;
        $indices = array();
        $last_index = 0;
        while (!feof($fh)) {
            $line = fgets($fh);
            if (($line === '%' . PHP_EOL) or feof($fh)) {
                if (($length > $eol_length) and ($length <= $max_length)) {
                    $indices[] = $last_index;
                    if ($length > $longest) {
                        $longest = $length;
                    }
                    if ($length < $shortest) {
                        $shortest = $length;
                    }
                    $last_index = ftell($fh);
                }
                $length = 0;
            } else {
                $length += strlen($line);
            }
        }
        fclose($fh);

        // Write header.
        if (($fh = fopen($file . '.dat', 'w')) === FALSE) {
            throw new Exception('FORTUNE: Failed to write index file.');
        }
        $number = count($indices);
        $this->_WriteUint32($fh, 2);                // version number (unofficial)
        $this->_WriteUint32($fh, $number);          // number of quotes
        $this->_WriteUint32($fh, $longest);         // length of longest quote
        $this->_WriteUint32($fh, $shortest);        // length of shortest quote
        $this->_WriteUint32($fh, 0);                // flags (reserved)
        $this->_WriteUint32($fh, ord('%') << 24);   // delimiter
        for ($i = 0; $i < $number; $i++) {
            $this->_WriteUint32($fh, $indices[$i]);
        }
        fclose($fh);
    }

    private function _GetQuote($fh, $index) {
        fseek($fh, $index, SEEK_SET);
        $line = '';
        $quote = '';
        do {
            $quote .= $line;
            $line = fgets($fh);
        } while (($line !== "%" . PHP_EOL) and ($line !== '%') and (!feof($fh)));
        return $quote;
    }

    private function _WriteUint32($fh, $n) {
        fwrite($fh, chr(($n >> 24) & 0xFF));
        fwrite($fh, chr(($n >> 16) & 0xFF));
        fwrite($fh, chr(($n >> 8) & 0xFF));
        fwrite($fh, chr($n & 0xFF));
    }

    private function _ReadUint32($fh) {
        $bytes = fread($fh, 4);
        $n = isset($bytes[3]) ? ord($bytes[3]) : 0;
        $n += isset($bytes[2]) ? (ord($bytes[2]) << 8) : 0;
        $n += isset($bytes[1]) ? (ord($bytes[1]) << 16) : 0;
        $n += isset($bytes[0]) ? (ord($bytes[0]) << 24) : 0;
        return $n;
    }
}
?>
