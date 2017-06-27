<?php

// https://github.com/client9/msgpack-php/blob/master/msgpack.php

/**
 * Pack some input into msgpack format.
 * Format specs: https://github.com/msgpack/msgpack/blob/master/spec.md
 *
 * @param mixed $input
 * @return string
 * @throws \InvalidArgumentException
 */
function msgpack_pack($input)
{
    static $bigendian;
    if (!isset($bigendian)) $bigendian = (pack('S',1)==pack('n',1));

    // null
    if (is_null($input)) {
        return pack('C',0xC0);
    }

    // booleans
    if (is_bool($input)) {
        return pack('C',$input ? 0xC3 : 0xC2);
    }

    // Integers
    if (is_int($input)) {
        // positive fixnum
        if (($input|0x7F) == 0x7F) return pack('C',$input&0x7F);
        // negative fixnum
        if ($input < 0 && $input>=-32) return pack('c',$input);
        // uint8
        if ($input > 0 && $input <= 0xFF) return pack('CC',0xCC,$input);
        // uint16
        if ($input > 0 && $input <= 0xFFFF) return pack('Cn',0xCD,$input);
        // uint32
        if ($input > 0 && $input <= 0xFFFFFFFF) return pack('CN',0xCE,$input);
        // uint64
        if ($input > 0 && $input <= 0xFFFFFFFFFFFFFFFF) {
            // pack() does not support 64-bit ints, so pack into two 32-bits
            $h = ($input&0xFFFFFFFF00000000)>>32;
            $l = $input&0xFFFFFFFF;
            return $bigendian ? pack('CNN',0xCF,$l,$h) : pack('CNN',0xCF,$h,$l);
        }
        // int8
        if ($input < 0 && $input >= -0x80) return pack('Cc',0xD0,$input);
        // int16
        if ($input < 0 && $input >= -0x8000) {
            $p = pack('s',$input);
            return pack('Ca2',0xD1,$bigendian ? $p : strrev($p));
        }
        // int32
        if ($input < 0 && $input >= -0x80000000) {
            $p = pack('l',$input);
            return pack('Ca4',0xD2,$bigendian ? $p : strrev($p));
        }
        // int64
        if ($input < 0 && $input >= -0x8000000000000000) {
            // pack() does not support 64-bit ints either so pack into two 32-bits
            $p1 = pack('l',$input&0xFFFFFFFF);
            $p2 = pack('l',($input>>32)&0xFFFFFFFF);
            return $bigendian ? pack('Ca4a4',0xD3,$p1,$p2) : pack('Ca4a4',0xD3,strrev($p2),strrev($p1));
        }
        throw new \InvalidArgumentException('Invalid integer: '.$input);
    }

    // Floats
    if (is_float($input)) {
        // Just pack into a double, don't take any chances with single precision
        return pack('C',0xCB).($bigendian ? pack('d',$input) : strrev(pack('d',$input)));
    }

    // Strings/Raw
    if (is_string($input)) {
        $len = strlen($input);
        if ($len<32) {
            return pack('Ca*',0xA0|$len,$input);
        } else if ($len<=0xFFFF) {
            return pack('Cna*',0xDA,$len,$input);
        } else if ($len<=0xFFFFFFFF) {
            return pack('CNa*',0xDB,$len,$input);
        } else {
            throw new \InvalidArgumentException('Input overflows (2^32)-1 byte max');
        }
    }

    // Arrays & Maps
    if (is_array($input)) {
        $keys = array_keys($input);
        $len = count($input);

        // Is this an associative array?
        $isMap = false;
        foreach ($keys as $key) {
            if (!is_int($key)) {
                $isMap = true;
                break;
            }
        }

        $buf = '';
        if ($len<16) {
            $buf .= pack('C',($isMap?0x80:0x90)|$len);
        } else if ($len<=0xFFFF) {
            $buf .= pack('Cn',($isMap?0xDE:0xDC),$len);
        } else if ($len<=0xFFFFFFFF) {
            $buf .= pack('CN',($isMap?0xDF:0xDD),$len);
        } else {
            throw new \InvalidArgumentException('Input overflows (2^32)-1 max elements');
        }

        foreach ($input as $key => $elm) {
            if ($isMap) $buf .= msgpack_pack($key);
            $buf .= msgpack_pack($elm);
        }
        return $buf;

    }

    throw new \InvalidArgumentException('Not able to pack/serialize input type: '.gettype($input));
}

/**
 * Unpack data from a msgpack'ed string
 *
 * @param string $input
 * @return mixed
 */
function msgpack_unpack($input)
{
    static $bigendian;
    if (!isset($bigendian)) $bigendian = (pack('S',1)==pack('n',1));

    // Store input into a memory buffer so we can operate on it with filepointers
    static $buffer;
    static $pos;
    if (!isset($buffer)) {
        $buffer = $input;
        $pos = 0;
    }

    if ($pos==strlen($buffer)) {
        $buffer = $input;
        $pos = 0;
    }

    // Read a single byte
    $byte = substr($buffer,$pos++,1);


    // null
    if ($byte == "\xC0") return null;

    // booleans
    if ($byte == "\xC2") return false;
    if ($byte == "\xC3") return true;

    // positive fixnum
    if (($byte & "\x80") == "\x00") {
        return current(unpack('C',$byte&"\x7F"));
    }

    // negative fixnum
    if (($byte & "\xE0") == "\xE0") {
        return current(unpack('c',$byte&"\xFF"));
    }

    // fixed raw
    if ((($byte ^ "\xA0") & "\xE0") == "\x00") {
        $len = current(unpack('c',($byte ^ "\xA0")));
        if ($len == 0) return "";
        $d = substr($buffer,$pos,$len);
        $pos+=$len;
        return current(unpack('a'.$len,$d));
    }

    // Arrays
    if ((($byte ^ "\x90") & "\xF0") == "\x00") {
        // fixed array
        $len = current(unpack('c',($byte ^ "\x90")));
        $data = array();
        for($i=0;$i<$len;$i++) {
            $data[] = msgpack_unpack($input);
        }
        return $data;
    } else if ($byte == "\xDC" || $byte == "\xDD") {
        if ($byte == "\xDC") {
            $d = substr($buffer,$pos,2);
            $pos+=2;
            $len = current(unpack('n',$d));
        }
        if ($byte == "\xDD") {
            $d = substr($buffer,$pos,4);
            $pos+=4;
            $len = current(unpack('N',$d));
        }
        $data = array();
        for($i=0;$i<$len;$i++) {
            $data[] = msgpack_unpack($input);
        }
        return $data;
    }

    // Maps
    if ((($byte ^ "\x80") & "\xF0") == "\x00") {
        // fixed map
        $len = current(unpack('c',($byte ^ "\x80")));
        $data = array();
        for($i=0;$i<$len;$i++) {
            $key = msgpack_unpack($input);
            $value = msgpack_unpack($input);
            $data[$key] = $value;
        }
        return $data;
    } else if ($byte == "\xDE" || $byte == "\xDF") {
        if ($byte == "\xDE") {
            $d = substr($buffer,$pos,2);
            $pos+=2;
            $len = current(unpack('n',$d));
        }
        else { // if ($byte == "\xDF") {
            $d = substr($buffer,$pos,4);
            $pos+=4;
            $len = current(unpack('N',$d));
        }
        $data = array();
        for($i=0;$i<$len;$i++) {
            $key = msgpack_unpack($input);
            $value = msgpack_unpack($input);
            $data[$key] = $value;
        }
        return $data;
    }

    switch ($byte) {
        // Unsigned integers
        case "\xCC": // uint 8
            return current(unpack('C',substr($buffer,$pos++,1)));
        case "\xCD": // uint 16
            $d = substr($buffer,$pos,2);
            $pos+=2;
            return current(unpack('n',$d));
        case "\xCE": // uint 32
            $d = substr($buffer,$pos,4);
            $pos+=4;
            return current(unpack('N',$d));
        case "\xCF": // uint 64
            $d = substr($buffer,$pos,8);
            $pos+=8;
            // Unpack into two uint32 and re-assemble
            $dat = unpack('Np1/Np2',$d);
            $dat['p1'] = $dat['p1'] << 32;
            return $dat['p1']|$dat['p2'];

        // Signed integers
        case "\xD0": // int 8
            return current(unpack('c',substr($buffer,$pos++,1)));
        case "\xD1": // int 16
            $d = substr($buffer,$pos,2);
            $pos+=2;
            // PHP does not have a "signed short, big-endian" unpacker
            // Get unsigned version and convert to negative if needed
            $unsigned = current(unpack('n',$d));
            return ($unsigned < 0x8000) ? $unsigned : ($unsigned & 0x7FFF) - 0x8000;
        case "\xD2": // int 32
            $d = substr($buffer,$pos,4);
            $pos+=4;
            // again, there is no "int32, big-endian" unpacker
            // the following might work on 32-bit machines, but fails on 64-bit
            //return (current(unpack('N',~$d))+1)*-1;
            $unsigned = current(unpack('N', $d));
            return ($unsigned < 0x80000000) ? $unsigned : ($unsigned & 0x7FFFFFFF) - 0x80000000;
        case "\xD3": // int 64
            $d = substr($buffer,$pos,8);
            $pos+=8;
            $dat = unpack('Np1/Np2',~$d);
            // this next line will cause p1 to be negative if
            //   high bit is set, on 64-bit machines
            $dat['p1'] = $dat['p1'] << 32;
            return (($dat['p1']|$dat['p2'])+1)*-1;
        // String / Raw
        case "\xDA": // raw 16
            $d = substr($buffer,$pos,2);
            $pos+=2;
            $len = current(unpack('n',$d));
            $d = substr($buffer,$pos,$len);
            $pos+=$len;
            return current(unpack('a'.$len,$d));
        case "\xDB": // raw 32
            $d = substr($buffer,$pos,4);
            $pos+=4;
            $len = current(unpack('N',$d));
            $d = substr($buffer,$pos,$len);
            $pos+=$len;
            return current(unpack('a'.$len,$d));

        // Floats
        case "\xCA": // single-precision
            $d = substr($buffer,$pos,4);
            $pos+=4;
            return current(unpack('f',$bigendian ? $d : strrev($d)));
        case "\xCB": // double-precision
            $d = substr($buffer,$pos,8);
            $pos+=8;
            return current(unpack('d',$bigendian ? $d : strrev($d)));

    }

    throw new \InvalidArgumentException('Can\'t unpack data with byte-header: '.$byte);
}
