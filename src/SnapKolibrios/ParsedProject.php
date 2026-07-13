<?php

namespace SnapKolibrios;

readonly class ParsedProject
{
    public function __construct(
        public int    $width,
        public int    $height,
        public string $bgColor,
        public int    $costume,
        public string $title,
        public string $globalVarStr,
        public string $dataSection,
        public string $bgListStr,
        public string $eventsStr,
        public int    $offsetImageData,
        public int    $offsetSpriteCostume,
    ) {}
}
