<?php

namespace App\Http\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ZipArchive;

class AfsService
{
    private string $outDirRel = 'generated/afs';
    private int $chunkSize = 30;

    /* =========================================================
     * PUBLIC
     * ========================================================= */

    public function processCsvToPdfBatch(UploadedFile $csvFile, string $templatePath): void
    {
        $this->assertTemplateExists($templatePath);

        $rows = $this->readCsvAssocRowsFromPath($csvFile->getRealPath());
        if (!$rows) throw new \RuntimeException("CSV has no data rows.");

        $placeholders = $this->templatePlaceholdersAllParts($templatePath);
        if (!$placeholders) throw new \RuntimeException("No placeholders found in template.");

        [$outDirAbs, $outDirRel] = $this->ensureOutputDir();

        foreach (array_chunk($rows, $this->chunkSize) as $chunk) {
            $docxAbsList = [];
            $docxToDbMeta = []; // baseName => [file_id, pdf_rel]

            foreach ($chunk as $rowAssoc) {
                $result = $this->createFileAndMaybeDocx(
                    rowAssoc: $rowAssoc,
                    placeholders: $placeholders,
                    templatePath: $templatePath,
                    outDirAbs: $outDirAbs,
                    outDirRel: $outDirRel
                );

                // skipped OR incomplete => no docx
                if (!$result) continue;

                [$fileRow, $docxAbs, $pdfRel] = $result;

                if (!$docxAbs) continue;

                $docxAbsList[] = $docxAbs;
                $baseName = pathinfo($docxAbs, PATHINFO_FILENAME);
                $docxToDbMeta[$baseName] = ['file_id' => $fileRow->id, 'pdf_rel' => $pdfRel];
            }

            if ($docxAbsList) {
                $this->sofficeBatchConvert($outDirAbs, $docxAbsList);
            }

            foreach ($docxAbsList as $docxAbs) {
                $baseName = pathinfo($docxAbs, PATHINFO_FILENAME);
                $pdfAbs = $outDirAbs . '/' . $baseName . '.pdf';

                @unlink($docxAbs);

                $meta = $docxToDbMeta[$baseName] ?? null;
                if (!$meta) continue;

                $file = File::find($meta['file_id']);
                if (!$file) continue;

                $file->update([
                    'status' => file_exists($pdfAbs) ? 'completed' : 'failed',
                    'path'   => file_exists($pdfAbs) ? $meta['pdf_rel'] : null,
                ]);
            }
        }
    }

    public function updateFileRawData(File $file, array $rawData): void
    {
        $csvMap = $this->buildCsvMap($rawData);

        $company = $this->extractCompany($csvMap);
        $president = $this->extractPresident($csvMap);

        // Keep company keys consistent (optional but helpful)
        if ($company) {
            $rawData['COMPANY NAME'] = $company;
            $rawData['Company Name'] = $company;
            $rawData['company_name'] = $company;
        }

        if ($president) {
            $rawData["President’s Name"] = $president;
            $rawData["President's Name"] = $president;
        }

        $file->update([
            'raw_data'       => $rawData,
            'company_name'   => $company ?: $file->company_name,
            'president_name' => $president ?: $file->president_name,
        ]);
    }

    public function regenerateSingleFromRawData(File $file, string $templatePath): void
    {
        $this->assertTemplateExists($templatePath);

        $rowAssoc = $file->raw_data;
        if (!$rowAssoc || !is_array($rowAssoc)) {
            throw new \RuntimeException("No raw_data found for this file.");
        }

        $placeholders = $this->templatePlaceholdersAllParts($templatePath);
        if (!$placeholders) throw new \RuntimeException("No placeholders found in template.");

        [$outDirAbs, $outDirRel] = $this->ensureOutputDir();

        $csvMap = $this->buildCsvMap($rowAssoc);
        $company = $this->extractCompany($csvMap);
        $president = $this->extractPresident($csvMap);

        [$repl, $missingFields, $filledFields] = $this->buildReplacements($placeholders, $csvMap);

        $safeCompany = Str::slug($company ?: ($file->company_name ?: 'afs')) ?: 'afs';
        $baseName = $safeCompany . '-' . now()->format('Ymd-His') . '-' . Str::random(6);

        $docxAbs = $outDirAbs . '/' . $baseName . '.docx';
        $pdfAbs  = $outDirAbs . '/' . $baseName . '.pdf';
        $pdfRel  = $outDirRel . '/' . $baseName . '.pdf';

        $file->update(['status' => 'processing']);

        $this->replaceInDocxAllParts($templatePath, $docxAbs, $repl);
        $this->sofficeBatchConvert($outDirAbs, [$docxAbs]);
        @unlink($docxAbs);

        if (!file_exists($pdfAbs)) {
            $file->update(['status' => 'failed', 'path' => null]);
            return;
        }

        $file->update([
            'status'         => 'completed',
            'path'           => $pdfRel,
            'original_name'  => $baseName . '.pdf',
            'missing_fields' => $missingFields,
            'filled_fields'  => $filledFields,
            'company_name'   => $company ?: $file->company_name,
            'president_name' => $president ?: $file->president_name,
        ]);
    }

    /* =========================================================
     * CORE PIPELINE
     * ========================================================= */

    /**
     * Creates a File row and maybe generates DOCX if complete.
     * Returns:
     * - null => skipped
     * - [File $fileRow, null, string $pdfRel] => incomplete (no docx)
     * - [File $fileRow, string $docxAbs, string $pdfRel] => generated docx
     */
    private function createFileAndMaybeDocx(
        array $rowAssoc,
        array $placeholders,
        string $templatePath,
        string $outDirAbs,
        string $outDirRel
    ): ?array {
        $csvMap = $this->buildCsvMap($rowAssoc);

        $company = $this->extractCompany($csvMap) ?: 'AFS';
        $president = $this->extractPresident($csvMap);

        // ✅ SKIP RULE: if company already has a completed document
        if ($this->companyHasCompletedDocument($company)) {
            return null; // skip entirely (no new row, no pdf)
        }

        $clientUser = $this->getOrCreateClientUser($company, 'password');

        [$repl, $missingFields, $filledFields] = $this->buildReplacements($placeholders, $csvMap);
        $isIncomplete = count($missingFields) > 0;

        $safeCompany = Str::slug((string)$company) ?: 'afs';
        $baseName = $safeCompany . '-' . now()->format('Ymd-His') . '-' . Str::random(6);

        $docxAbs = $outDirAbs . '/' . $baseName . '.docx';
        $pdfRel  = $outDirRel . '/' . $baseName . '.pdf';
        $pdfName = $baseName . '.pdf';

        $fileRow = File::create([
            'client_id'      => $clientUser->id,
            'company_name'   => $company ?: null,
            'president_name' => $president ?: null,
            'original_name'  => $pdfName,
            'path'           => null,
            'status'         => $isIncomplete ? 'incomplete' : 'processing',
            'missing_fields' => $missingFields,
            'filled_fields'  => $filledFields,
            'raw_data'       => $rowAssoc,
        ]);

        if ($isIncomplete) {
            return [$fileRow, null, $pdfRel];
        }

        $this->replaceInDocxAllParts($templatePath, $docxAbs, $repl);
        return [$fileRow, $docxAbs, $pdfRel];
    }

    private function companyHasCompletedDocument(string $company): bool
    {
        $normalized = strtolower(trim($company));

        return File::whereRaw('LOWER(TRIM(company_name)) = ?', [$normalized])
            ->where('status', 'completed')
            ->whereNotNull('path')
            ->exists();
    }

    private function buildCsvMap(array $rowAssoc): array
    {
        $csvMap = [];
        foreach ($rowAssoc as $header => $val) {
            $csvMap[$this->normKey((string)$header)] = $val;
        }
        return $csvMap;
    }

    private function buildReplacements(array $placeholders, array $csvMap): array
    {
        $repl = [];
        $missing = [];
        $filled = [];

        foreach ($placeholders as $ph) {
            $val = $csvMap[$this->normKey($ph)] ?? null;

            if ($this->isMissingValue($val)) {
                $missing[] = $ph;
                $repl[$ph] = 'N / A';
            } else {
                $filled[] = $ph;
                $repl[$ph] = $this->formatValue($val);
            }
        }

        return [$repl, $missing, $filled];
    }

    private function extractCompany(array $csvMap): ?string
    {
        $company =
            $csvMap[$this->normKey('COMPANY NAME')] ??
            $csvMap[$this->normKey('Company Name')] ??
            $csvMap[$this->normKey('company_name')] ??
            null;

        $company = $company !== null ? trim((string)$company) : '';
        return $company !== '' ? $company : null;
    }

    private function extractPresident(array $csvMap): ?string
    {
        $p =
            $csvMap[$this->normKey("President’s Name")] ??
            $csvMap[$this->normKey("President's Name")] ??
            $csvMap[$this->normKey("PRESIDENT")] ??
            null;

        $p = $p !== null ? trim((string)$p) : '';
        return $p !== '' ? $p : null;
    }

    /* =========================================================
     * FORMAT / VALIDATION
     * ========================================================= */

    private function formatValue($value): string
    {
        if ($value === null) return '';

        $raw = trim((string)$value);
        $normalized = str_replace([',', ' '], '', $raw);

        if ($normalized !== '' && is_numeric($normalized)) {
            return number_format((float)$normalized, 2, '.', ',');
        }

        return $raw;
    }

    private function normKey(string $key): string
    {
        $key = strtoupper(trim($key));
        return preg_replace('/[^A-Z0-9]/', '', $key);
    }

    private function isMissingValue($value): bool
    {
        if ($value === null) return true;
        $v = trim((string)$value);
        if ($v === '') return true;

        $lower = strtolower($v);
        return in_array($lower, ['n/a', 'na', 'null', 'undefined', 'n / a'], true);
    }

    private function assertTemplateExists(string $templatePath): void
    {
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }
    }

    /* =========================================================
     * USER CREATION
     * ========================================================= */

    private function makeGmailFromCompany(string $companyName): string
    {
        $local = strtolower(trim($companyName));
        $local = preg_replace('/\s+/', '.', $local);
        $local = preg_replace('/[^a-z0-9.]/', '', $local);
        $local = trim($local, '.');

        if ($local === '') $local = 'client';
        return $local . '@gmail.com';
    }

    private function getOrCreateClientUser(string $companyName, string $plainPassword): User
    {
        $baseEmail = $this->makeGmailFromCompany($companyName);
        $email = $baseEmail;

        $existing = User::where('email', $email)->first();
        if ($existing) return $existing;

        $n = 2;
        while (User::where('email', $email)->exists()) {
            [$local, $domain] = explode('@', $baseEmail, 2);
            $email = $local . '.' . $n . '@' . $domain;
            $n++;
        }

        return User::create([
            'name'     => $companyName,
            'email'    => $email,
            'password' => Hash::make($plainPassword),
            'role'     => 'client',
        ]);
    }

    /* =========================================================
     * CSV READER
     * ========================================================= */

    private function readCsvAssocRowsFromPath(string $path): array
    {
        $handle = fopen($path, 'r');
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

    /* =========================================================
     * TEMPLATE HELPERS
     * ========================================================= */

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
            foreach (($matches[1] ?? []) as $ph) {
                $ph = trim((string)$ph);
                if ($ph !== '') $placeholders[] = $ph;
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

    /* =========================================================
     * PDF CONVERSION
     * ========================================================= */

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

    /* =========================================================
     * OUTPUT DIR
     * ========================================================= */

    private function ensureOutputDir(): array
    {
        $outDirAbs = storage_path('app/public/' . $this->outDirRel);
        if (!is_dir($outDirAbs)) mkdir($outDirAbs, 0775, true);

        return [$outDirAbs, $this->outDirRel];
    }
}