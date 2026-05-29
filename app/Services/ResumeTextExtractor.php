<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

final class ResumeTextExtractor
{
    public function extract(string $path): ?string
    {
        $disk = config('filesystems.default');

        if ($disk === 's3') {
            $tempFile = tempnam(sys_get_temp_dir(), 'resume_');
            $contents = Storage::disk('s3')->get($path);
            file_put_contents($tempFile, $contents);
            $fullPath = $tempFile;
        } else {
            $fullPath = Storage::disk('public')->path($path);
            if (! is_file($fullPath)) {
                return null;
            }
        }

        $text = $this->extractWithPdfParser($fullPath);
        if ($text) {
            $text = $this->cleanText($text);
            if ($disk === 's3') {
                unlink($tempFile);
            }

            return $text;
        }

        $text = $this->extractWithPdftotext($fullPath);
        if ($text) {
            $text = $this->cleanText($text);
            if ($disk === 's3') {
                unlink($tempFile);
            }

            return $text;
        }

        if ($disk === 's3') {
            unlink($tempFile);
        }

        return null;
    }

    private function extractWithPdfParser(string $file): ?string
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($file);
            $text = $pdf->getText();

            return mb_trim($text) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function extractWithPdftotext(string $file): ?string
    {
        $cmd = 'pdftotext -layout '.escapeshellarg($file).' -';
        $output = shell_exec($cmd);

        return $output ? mb_trim($output) : null;
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(['•', '◦', '▪', '▫', '–', '—'], ['-', '-', '-', '-', '-', '-'], $text);
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_trim($text);
    }
}
