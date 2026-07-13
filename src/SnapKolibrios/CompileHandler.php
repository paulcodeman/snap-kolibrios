<?php

namespace SnapKolibrios;

class CompileHandler
{
    private string $buildsDir;
    private string $cmmPath;
    private string $projectDir;

    public function __construct(string $buildsDir, string $cmmPath, string $projectDir)
    {
        $this->buildsDir = $buildsDir;
        $this->cmmPath = $cmmPath;
        $this->projectDir = $projectDir;
    }

    public function handle(string $xml): never
    {
        try {
            $buildId = uniqid('kolibri_', true);

            if (!is_dir($this->buildsDir)) {
                mkdir($this->buildsDir, 0777, true);
            }

            $cFile = "$this->buildsDir/$buildId.c";

            $transpiler = new Transpiler(
                resourceDir: $this->projectDir . '/resource',
                referenceDir: 'resource',
            );
            $transpiler->transpileRaw($xml, $cFile);

            if (!file_exists($this->cmmPath)) {
                throw new \RuntimeException('cmm.exe not found');
            }

            $cmd = sprintf(
                'cd /d "%s" && "%s" "%s" 2>&1',
                $this->projectDir,
                $this->cmmPath,
                $cFile
            );
            exec($cmd, $output, $returnCode);

            $comFile = "$this->buildsDir/$buildId.com";
            $kexFile = "$this->buildsDir/$buildId.kex";

            if (file_exists($comFile)) {
                rename($comFile, $kexFile);

                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="game.kex"');
                header('Content-Length: ' . filesize($kexFile));
                readfile($kexFile);
            } else {
                $errOutput = implode("\n", $output);
                if (preg_match('/Errors:\s*[1-9]\d*/', $errOutput)) {
                    $errFile = "$this->buildsDir/$buildId.err";
                    file_put_contents($errFile, $errOutput);
                    throw new \RuntimeException('Compilation errors:' . "\n" . $errOutput);
                }
                throw new \RuntimeException('Compilation failed (unknown error):' . "\n" . $errOutput);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo $e->getMessage();
        } finally {
            foreach ([$cFile ?? null, $kexFile ?? null, $comFile ?? null] as $f) {
                if ($f && file_exists($f)) {
                    @unlink($f);
                }
            }

            if (mt_rand(1, 10) === 1) {
                $expire = time() - 3600;
                foreach (glob($this->buildsDir . '/*.{c,com,kex,err}', GLOB_BRACE) ?: [] as $old) {
                    if (filemtime($old) < $expire) {
                        @unlink($old);
                    }
                }
            }
        }

        exit;
    }
}
