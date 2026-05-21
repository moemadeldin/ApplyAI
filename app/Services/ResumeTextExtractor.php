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
        $fullPath = Storage::disk('public')->path($path);
        if (! is_file($fullPath)) {
            return null;
        }

        $text = $this->extractWithPdfParser($fullPath);
        if ($text) {
            return $this->cleanText($text);
        }

        $text = $this->extractWithPdftotext($fullPath);
        if ($text) {
            return $this->cleanText($text);
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
