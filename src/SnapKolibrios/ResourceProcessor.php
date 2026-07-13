<?php

namespace SnapKolibrios;

use SimpleXMLElement;

class ResourceProcessor
{
    private string $resourceDir;
    private string $referenceDir;
    private int $offsetImageData = 0;

    public function __construct(string $resourceDir, ?string $referenceDir = null)
    {
        $this->resourceDir = rtrim($resourceDir, '/\\');
        $this->referenceDir = $referenceDir !== null
            ? rtrim($referenceDir, '/\\')
            : $this->resourceDir;
    }

    public function ensureResourceDir(): void
    {
        if (!is_dir($this->resourceDir)) {
            mkdir($this->resourceDir, 0777, true);
        }
    }

    public function getOffsetImageData(): int
    {
        return $this->offsetImageData;
    }

    public function imageToRaw(SimpleXMLElement $img, string $name): string
    {
        $data = explode(',', $img->image);
        $centerX = (int)$img->{'center-x'};
        $centerY = (int)$img->{'center-y'};
        $b64 = $data[1];
        $im = imagecreatefromstring(base64_decode($b64));
        if (!$im) {
            throw new \RuntimeException("Failed to decode image: $name");
        }

        $width = imagesx($im);
        $height = imagesy($im);

        $dword = [$width, $height, $centerX, $centerY, 0, 0];
        $this->offsetImageData = count($dword) * 4;

        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col < $width; $col++) {
                $dword[] = imagecolorat($im, $col, $row);
            }
        }

        imagedestroy($im);

        $packed = pack('L*', ...$dword);
        $rawPath = "$this->resourceDir/$name.raw";
        file_put_contents($rawPath, $packed);

        return "unsigned char {$name}[] = FROM \"$this->referenceDir/$name.raw\";";
    }

    public function colorToDword(string $color): string
    {
        $rgba = explode(',', $color);
        $dec = ((($rgba[3] * 0x7F) & 0x7F) << 24)
            | ($rgba[2] << 16)
            | ($rgba[1] << 8)
            | $rgba[0];

        return '0x' . str_pad(dechex($dec), 8, '0', STR_PAD_LEFT);
    }
}
