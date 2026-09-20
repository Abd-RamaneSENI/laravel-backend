<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PdfFirstPagePreview
{
    public function previewPath(string $privatePath, string $cacheKey): ?string
    {
        return $this->pagePath($privatePath, $cacheKey, 1, 'previews', (int) config('previews.resolution', 140));
    }

    public function pagePath(string $privatePath, string $cacheKey, int $page = 1, string $directory = 'reader-pages', int $resolution = 150): ?string
    {
        $disk = Storage::disk('private');
        if (! $disk->exists($privatePath)) {
            return null;
        }

        $page = max(1, $page);
        $resolution = max(72, min(220, $resolution));
        $hash = hash('sha256', $cacheKey.'|'.$page.'|'.$resolution.'|'.$disk->lastModified($privatePath).'|'.$disk->size($privatePath));
        $target = trim($directory, '/')."/{$hash}.png";
        if ($disk->exists($target)) {
            return $target;
        }

        $binary = $this->resolveBinary('pdftoppm');
        if (! $binary) {
            return null;
        }

        $tmpDir = storage_path('app/private/'.trim($directory, '/').'/tmp/'.$hash);
        File::ensureDirectoryExists($tmpDir);
        $outputPrefix = $tmpDir.DIRECTORY_SEPARATOR.'page-'.$page;
        $outputFile = $outputPrefix.'.png';

        try {
            $process = new Process([
                $binary,
                '-f', (string) $page,
                '-l', (string) $page,
                '-png',
                '-singlefile',
                '-r', (string) $resolution,
                $disk->path($privatePath),
                $outputPrefix,
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputFile)) {
                return null;
            }

            $disk->put($target, File::get($outputFile));

            return $target;
        } catch (\Throwable) {
            return null;
        } finally {
            File::deleteDirectory($tmpDir);
        }
    }

    public function pageCount(string $privatePath): ?int
    {
        $disk = Storage::disk('private');
        if (! $disk->exists($privatePath)) {
            return null;
        }

        $binary = $this->resolveBinary('pdfinfo');
        if (! $binary) {
            return null;
        }

        try {
            $process = new Process([$binary, $disk->path($privatePath)]);
            $process->setTimeout(15);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            if (preg_match('/^Pages:\s+(\d+)/mi', $process->getOutput(), $matches) !== 1) {
                return null;
            }

            return max(1, (int) $matches[1]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveBinary(string $binary): ?string
    {
        $configured = $binary === 'pdftoppm' ? config('previews.pdftoppm_binary') : config('previews.pdfinfo_binary');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        $finder = new ExecutableFinder();
        $found = $finder->find($configured ?: $binary);
        if ($found) {
            return $found;
        }

        $profile = rtrim((string) getenv('USERPROFILE'), '\\/');
        $candidates = array_filter([
            $profile ? $profile.'\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\native\\poppler\\Library\\bin\\'.$binary.'.exe' : null,
            'C:\\Program Files\\poppler\\Library\\bin\\'.$binary.'.exe',
            'C:\\Program Files\\poppler\\bin\\'.$binary.'.exe',
            'C:\\laragon\\bin\\poppler\\bin\\'.$binary.'.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
