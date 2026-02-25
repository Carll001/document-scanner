<?php

namespace App\Http\Services;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class AfsService
{
    private function formatValue($value): string
{
    if ($value === null) return '';

    $raw = trim((string)$value);

    // remove commas/spaces so "1,234.5" becomes "1234.5"
    $normalized = str_replace([',', ' '], '', $raw);

    if ($normalized !== '' && is_numeric($normalized)) {
        return number_format((float)$normalized, 2, '.', ',');
    }

    return $raw;
}
    public function processCsvToPdfBatch(UploadedFile $csvFile, string $templatePath): void
    {
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }

        $rows = $this->readCsvAssocRows($csvFile);
        if (count($rows) === 0) {
            throw new \RuntimeException("CSV has no data rows.");
        }

        $placeholders = $this->templatePlaceholdersAllParts($templatePath);
        if (count($placeholders) === 0) {
            throw new \RuntimeException("No placeholders found in template.");
        }

        $outDirRel = 'generated/afs';
        $outDirAbs = storage_path('app/public/' . $outDirRel);
        if (!is_dir($outDirAbs)) {
            mkdir($outDirAbs, 0775, true);
        }

        // We'll generate DOCX in chunks, convert each chunk in ONE soffice call.
        $chunkSize = 30; // tune 20-60 (too big = long command line)
        $rowChunks = array_chunk($rows, $chunkSize);

        foreach ($rowChunks as $chunk) {
            $docxAbsList = [];
            $docxToDbMeta = []; // baseName => [company, missing, filled, status]

            foreach ($chunk as $rowAssoc) {
                // Build normalized CSV map: NORM_HEADER => value
                $csvMap = [];
                foreach ($rowAssoc as $header => $val) {
                    $csvMap[$this->normKey((string)$header)] = $val;
                }

                // Build replacements
                $repl = [];
                $missingFields = [];
                $filledFields = [];

                foreach ($placeholders as $ph) {
                    $val = $csvMap[$this->normKey($ph)] ?? null;
                    if ($this->isMissingValue($val)) {
                        $missingFields[] = $ph;
                        $repl[$ph] = 'N / A';
                    } else {
                        $filledFields[] = $ph;
                        $repl[$ph] = $this->formatValue($val);
                    }
                }

                $status = count($missingFields) > 0 ? 'incomplete' : 'processing';

                // company name for file + DB
                $company = $rowAssoc['COMPANY NAME']
                    ?? $rowAssoc['Company Name']
                    ?? $rowAssoc['company_name']
                    ?? 'AFS';

                $safeCompany = Str::slug((string) $company) ?: 'afs';
                $baseName = $safeCompany . '-' . now()->format('Ymd-His') . '-' . Str::random(6);

                $docxAbs = $outDirAbs . '/' . $baseName . '.docx';
                $pdfRel  = $outDirRel . '/' . $baseName . '.pdf';
                $pdfName = $baseName . '.pdf';

                // Create DB row immediately (like your old code)
                $fileRow = File::create([
                    'company_name'   => $company ?: null,
                    'original_name'  => $pdfName, // we want PDF name
                    'path'           => null,     // set after conversion
                    'status'         => $status === 'incomplete' ? 'incomplete' : 'processing',
                    'missing_fields' => $missingFields,
                    'filled_fields'  => $filledFields,
                ]);

                // Match your old behavior: only generate when completed
                if ($status === 'incomplete') {
                    // skip docx/pdf generation
                    continue;
                }

                // Generate DOCX
                $this->replaceInDocxAllParts($templatePath, $docxAbs, $repl);

                $docxAbsList[] = $docxAbs;
                $docxToDbMeta[$baseName] = [
                    'file_id' => $fileRow->id,
                    'pdf_rel' => $pdfRel,
                ];
            }

            // Batch convert DOCX -> PDF (ONE call)
            if ($docxAbsList) {
                $this->sofficeBatchConvert($outDirAbs, $docxAbsList);
            }

            // Verify + update DB + delete DOCX
            foreach ($docxAbsList as $docxAbs) {
                $baseName = pathinfo($docxAbs, PATHINFO_FILENAME);
                $pdfAbs = $outDirAbs . '/' . $baseName . '.pdf';

                // delete docx
                @unlink($docxAbs);

                $meta = $docxToDbMeta[$baseName] ?? null;
                if (!$meta) continue;

                $file = File::find($meta['file_id']);
                if (!$file) continue;

                if (file_exists($pdfAbs)) {
                    $file->update([
                        'status' => 'completed',
                        'path'   => $meta['pdf_rel'],
                    ]);
                } else {
                    $file->update([
                        'status' => 'failed',
                        'path'   => null,
                    ]);
                }
            }
        }
    }

    private function sofficeBatchConvert(string $outDirAbs, array $docxAbsList): void
    {
        $cmd = 'soffice --headless --nologo --nofirststartwizard --convert-to pdf --outdir '
            . escapeshellarg($outDirAbs) . ' '
            . implode(' ', array_map('escapeshellarg', $docxAbsList));

        exec($cmd, $out, $code);

        if ($code !== 0) {
            throw new \RuntimeException("soffice failed:\n" . implode("\n", $out));
        }
    }

    /* ============================
       Your existing helpers (copied)
       ============================ */

    private function readCsvAssocRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) return [];

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn($h) => trim((string)$h), $headers);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $assoc = [];
            foreach ($headers as $i => $h) {
                if ($h === '') continue;
                $assoc[$h] = $row[$i] ?? '';
            }
            $rows[] = $assoc;
        }

        fclose($handle);
        return $rows;
    }

    private function templatePlaceholdersAllParts(string $docxPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) return [];

        $placeholders = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) continue;

            if (
                $name !== 'word/document.xml' &&
                !preg_match('#^word/header\d+\.xml$#', $name) &&
                !preg_match('#^word/footer\d+\.xml$#', $name)
            ) continue;

            $xml = $zip->getFromName($name) ?: '';

            preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $tMatches);
            $text = implode('', $tMatches[1] ?? []);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

            preg_match_all('/\{([^{}]+)\}/u', $text, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $ph) {
                    $ph = trim((string)$ph);
                    if ($ph !== '') $placeholders[] = $ph;
                }
            }
        }

        $zip->close();

        $placeholders = array_values(array_unique($placeholders));
        sort($placeholders);
        return $placeholders;
    }

    private function replaceInDocxAllParts(string $templatePath, string $outputPath, array $repl): void
    {
        copy($templatePath, $outputPath);

        $zip = new ZipArchive();
        if ($zip->open($outputPath) !== true) {
            throw new \RuntimeException("Cannot open docx: {$outputPath}");
        }

        $targets = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) continue;

            if (
                $name === 'word/document.xml' ||
                preg_match('#^word/header\d+\.xml$#', $name) ||
                preg_match('#^word/footer\d+\.xml$#', $name)
            ) {
                $targets[] = $name;
            }
        }

        foreach ($targets as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) continue;

            $xml = preg_replace_callback('/\{([\s\S]*?)\}/u', function ($m) {
                $inside = $m[1];
                $inside = preg_replace('/<[^>]+>/', '', $inside);
                $inside = html_entity_decode($inside, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $inside = preg_replace('/\s+/u', ' ', $inside);
                $inside = trim($inside);
                return '{' . $inside . '}';
            }, $xml);

            foreach ($repl as $key => $value) {
                $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $pattern = '/\{\s*' . preg_quote($key, '/') . '\s*\}/u';
                $xml = preg_replace($pattern, $value, $xml);
            }

            $zip->addFromString($part, $xml);
        }

        $zip->close();
    }

    private function normKey(string $key): string
    {
        $key = strtoupper(trim($key));
        return preg_replace('/[^A-Z0-9]/', '', $key);
    }

    private function isMissingValue($value): bool
    {
        if ($value === null) return true;
        $v = trim((string) $value);
        if ($v === '') return true;
        $lower = strtolower($v);
        return in_array($lower, ['n/a', 'na', 'null', 'undefined', 'n / a'], true);
    }
}