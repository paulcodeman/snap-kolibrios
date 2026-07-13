<?php

namespace SnapKolibrios;

use SimpleXMLElement;

class Transpiler
{
    private ResourceProcessor $resourceProcessor;
    private XmlParser $xmlParser;
    private CodeGenerator $codeGenerator;

    public function __construct(?string $resourceDir = null, ?string $referenceDir = null)
    {
        if ($resourceDir === null) {
            $resourceDir = getcwd() . '/resource';
        }
        if ($referenceDir === null) {
            $referenceDir = 'resource';
        }

        $this->resourceProcessor = new ResourceProcessor($resourceDir, $referenceDir);
        $this->xmlParser = new XmlParser($this->resourceProcessor);
        $this->codeGenerator = new CodeGenerator();
    }

    public function transpile(string $xmlPath, string $outputPath): void
    {
        $this->resourceProcessor->ensureResourceDir();

        $xml = simplexml_load_file($xmlPath);
        if (!$xml) {
            throw new \RuntimeException("Failed to parse XML: $xmlPath");
        }

        $this->process($xml, $outputPath);
    }

    public function transpileRaw(string $xmlString, string $outputPath): void
    {
        $this->resourceProcessor->ensureResourceDir();

        $parsed = simplexml_load_string($xmlString);
        if (!$parsed) {
            throw new \RuntimeException('Failed to parse XML');
        }

        $this->process($parsed, $outputPath);
    }

    private function process(SimpleXMLElement $xml, string $outputPath): void
    {
        $project = $this->xmlParser->parse($xml);
        $cmm = $this->codeGenerator->generate($project);

        file_put_contents($outputPath, str_replace("\n", "\r\n", $cmm));
    }
}
