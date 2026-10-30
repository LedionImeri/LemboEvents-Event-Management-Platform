<?php
/**
 * Minimal, dependency-free QR Code generator (ISO/IEC 18004), byte mode,
 * error correction level M, versions 1–10 (up to 213 bytes). Renders to SVG.
 *
 * Implementation follows the reference algorithm (function patterns, Reed–Solomon
 * ECC with block interleaving, zig-zag placement, 8 masks with penalty scoring).
 */
final class QrCode
{
    // Per version (index = version): total codewords, ECC blocks, ECC codewords per block (level M)
    private const TOTAL = [0, 26, 44, 70, 100, 134, 172, 196, 242, 292, 346];
    private const BLOCKS = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];
    private const ECC_PER_BLOCK = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
    private const ALIGN = [
        0 => [], 1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private int $version;
    private int $size;
    /** @var bool[][] */
    private array $modules = [];
    /** @var bool[][] */
    private array $isFunction = [];

    public static function svg(string $text, int $pixelSize = 220, string $dark = '#12101A', string $light = '#FFFFFF'): string
    {
        $qr = new self($text);
        return $qr->toSvg($pixelSize, $dark, $light);
    }

    public function __construct(string $text)
    {
        $data = array_values(unpack('C*', $text) ?: []);
        $len = count($data);
        $version = 0;
        for ($v = 1; $v <= 10; $v++) {
            $capacityBits = (self::TOTAL[$v] - self::BLOCKS[$v] * self::ECC_PER_BLOCK[$v]) * 8;
            $needed = 4 + ($v < 10 ? 8 : 16) + $len * 8;
            if ($needed <= $capacityBits) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new InvalidArgumentException('QR payload too long.');
        }
        $this->version = $version;
        $this->size = $version * 4 + 17;
        $this->modules = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->isFunction = $this->modules;

        $this->drawFunctionPatterns();
        $codewords = $this->addEccAndInterleave($this->encodeData($data));
        $this->drawCodewords($codewords);

        // Choose the mask with the lowest penalty score
        $best = 0;
        $minPenalty = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $this->applyMask($m);
            $this->drawFormatBits($m);
            $p = $this->penalty();
            if ($p < $minPenalty) {
                $minPenalty = $p;
                $best = $m;
            }
            $this->applyMask($m); // undo (XOR)
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);
    }

    // ------------------------------------------------------------------ data

    private function encodeData(array $bytes): array
    {
        $bits = [];
        $push = function (int $val, int $len) use (&$bits) {
            for ($i = $len - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };
        $push(0b0100, 4);
        $push(count($bytes), $this->version < 10 ? 8 : 16);
        foreach ($bytes as $b) {
            $push($b, 8);
        }
        $capacity = (self::TOTAL[$this->version] - self::BLOCKS[$this->version] * self::ECC_PER_BLOCK[$this->version]) * 8;
        $push(0, min(4, $capacity - count($bits)));
        $push(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $push($pad, 8);
        }
        $out = array_fill(0, intdiv(count($bits), 8), 0);
        foreach ($bits as $i => $bit) {
            $out[$i >> 3] |= $bit << (7 - ($i & 7));
        }
        return $out;
    }

    private function addEccAndInterleave(array $data): array
    {
        $numBlocks = self::BLOCKS[$this->version];
        $eccLen = self::ECC_PER_BLOCK[$this->version];
        $raw = self::TOTAL[$this->version];
        $numShort = $numBlocks - $raw % $numBlocks;
        $shortLen = intdiv($raw, $numBlocks);
        $divisor = self::rsDivisor($eccLen);

        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $ecc = self::rsRemainder($dat, $divisor);
            if ($i < $numShort) {
                $dat[] = 0;
            }
            $blocks[] = array_merge($dat, $ecc);
        }
        $result = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $result[] = $block[$i];
                }
            }
        }
        return $result;
    }

    private static function rsMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::rsMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::rsMultiply($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::rsMultiply($coef, $factor);
            }
        }
        return $result;
    }

    // ------------------------------------------------------------- patterns

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        $pos = self::ALIGN[$this->version];
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setFunction($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0);
        $this->drawVersion();
    }

    private function drawFinder(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunction($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (0b00 << 3) | $mask; // level M = 00
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn(int $i) => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $this->size - 8, true);
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $dark);
            $this->setFunction($b, $a, $dark);
        }
    }

    private function drawCodewords(array $data): void
    {
        $i = 0;
        $total = count($data) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function penalty(): int
    {
        $n = $this->size;
        $score = 0;
        $rows = [];
        $cols = array_fill(0, $n, '');
        $dark = 0;
        for ($y = 0; $y < $n; $y++) {
            $row = '';
            for ($x = 0; $x < $n; $x++) {
                $c = $this->modules[$y][$x] ? '1' : '0';
                $row .= $c;
                $cols[$x] .= $c;
                $dark += (int) $c;
            }
            $rows[] = $row;
        }
        foreach (array_merge($rows, $cols) as $line) {
            // Rule 1: runs of 5+ same-colour modules
            preg_match_all('/0{5,}|1{5,}/', $line, $m);
            foreach ($m[0] as $run) {
                $score += 3 + strlen($run) - 5;
            }
            // Rule 3: finder-like patterns
            $score += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
        }
        // Rule 2: 2×2 blocks
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $this->modules[$y][$x];
                if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        // Rule 4: dark/light balance
        $total = $n * $n;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $score + max(0, $k) * 10;
    }

    // --------------------------------------------------------------- render

    public function toSvg(int $pixelSize, string $dark, string $light): string
    {
        $border = 4;
        $dim = $this->size + $border * 2;
        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            $x = 0;
            while ($x < $this->size) {
                if (!$this->modules[$y][$x]) {
                    $x++;
                    continue;
                }
                $start = $x;
                while ($x < $this->size && $this->modules[$y][$x]) {
                    $x++;
                }
                $path .= 'M' . ($start + $border) . ' ' . ($y + $border) . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" width="' . $pixelSize . '" height="' . $pixelSize
            . '" shape-rendering="crispEdges" role="img" aria-label="Ticket QR code"><rect width="100%" height="100%" fill="' . htmlspecialchars($light, ENT_QUOTES)
            . '"/><path d="' . $path . '" fill="' . htmlspecialchars($dark, ENT_QUOTES) . '"/></svg>';
    }
}

// ---------------------------------------------------------------------
// Ticket QR payloads
// ---------------------------------------------------------------------

/**
 * The QR encodes "LEMBO1.<code>.<signature>". The HMAC signature lets the scanner reject
 * forged codes immediately, but the server ALWAYS re-validates the ticket in the database
 * (existence, event, status) — the QR alone is never trusted.
 */
function ticket_qr_payload(string $code): string
{
    return 'LEMBO1.' . $code . '.' . ticket_signature($code);
}

function ticket_signature(string $code): string
{
    return substr(hash_hmac('sha256', 'ticket:' . $code, APP_SECRET), 0, 10);
}

/** Extracts and verifies a ticket code from a scanned payload or a manually typed code. */
function parse_ticket_payload(string $raw): ?array
{
    $raw = strtoupper(trim($raw));
    $raw = preg_replace('/\s+/', '', $raw);
    if (preg_match('/^LEMBO1\.([A-Z0-9]{16})\.([A-F0-9]{10})$/', $raw, $m)) {
        $valid = hash_equals(strtoupper(ticket_signature($m[1])), $m[2]);
        return ['code' => $m[1], 'signed' => true, 'signature_ok' => $valid];
    }
    $code = str_replace('-', '', $raw);
    if (preg_match('/^[A-Z0-9]{16}$/', $code)) {
        return ['code' => $code, 'signed' => false, 'signature_ok' => true];
    }
    return null;
}
