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
    /* ---------------------------------
     * Formatting / Helpers
     * --------------------------------- */

    private function formatValue($value): string
    {
        if ($value === null) return '';

        $raw = trim((string) $value);

        // remove commas/spaces so "1,234.5" becomes "1234.5"
        $normalized = str_replace([',', ' '], '', $raw);

        if ($normalized !== '' && is_numeric($normalized)) {
            return number_format((float) $normalized, 2, '.', ',');
        }

        return $raw;
    }

    private function makeGmailFromCompany(string $companyName): string
    {
        $local = strtolower(trim($companyName));
        $local = preg_replace('/\s+/', '.', $local);        // spaces -> dots
        $local = preg_replace('/[^a-z0-9.]/', '', $local);  // keep letters/numbers/dots
        $local = trim($local, '.');

        if ($local === '') $local = 'client';

        return $local . '@gmail.com';
    }

    private function getOrCreateClientUser(string $companyName, string $plainPassword): User
    {
        $baseEmail = $this->makeGmailFromCompany($companyName);
        $email = $baseEmail;

        // reuse if already exists
        $existing = User::where('email', $email)->first();
        if ($existing) return $existing;

        // ensure unique email: append .2 .3 ...
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

    private function normKey(string $key): string
    {
        $key = strtoupper(trim($key));
        return preg_replace('/[^A-Z0-9]/', '', $key);
    }

    // FINDING N / A DATA FROM DB
    private function isMissingValue($value): bool
    {
        if ($value === null) return true;
        $v = trim((string) $value);
        if ($v === '') return true;

        $lower = strtolower($v);
        return in_array($lower, ['n/a', 'na', 'null', 'undefined', 'n / a'], true);
    }

    /* ---------------------------------
     * Public API (HTTP request version)
     *  - Works, but can timeout on big CSV
     *  - Prefer using processCsvChunkToPdf via Jobs
     * --------------------------------- */

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

        $outDirRel = 'generated/';
        $outDirAbs = storage_path('app/public/' . $outDirRel);
        if (!is_dir($outDirAbs)) {
            mkdir($outDirAbs, 0775, true);
        }

        // We'll generate DOCX in chunks, convert each chunk in ONE soffice call.
        $chunkSize = 30; // tune 20-60 (too big = long command line)
        $rowChunks = array_chunk($rows, $chunkSize);

        foreach ($rowChunks as $chunk) {
            $docxAbsList = [];
            $docxToDbMeta = []; // baseName => [file_id, pdf_rel]

            foreach ($chunk as $rowAssoc) {
                // Build normalized CSV map: NORM_HEADER => value
                $csvMap = [];
                foreach ($rowAssoc as $header => $val) {
                    $csvMap[$this->normKey((string) $header)] = $val;
                }

                // company name for file + DB (normalized so BOM/spacing doesn't break it)
                $company =
                    $csvMap[$this->normKey('COMPANY NAME')] ??
                    $csvMap[$this->normKey('Company Name')] ??
                    $csvMap[$this->normKey('company_name')] ??
                    'AFS';

                // Create/find client user for this company
                $clientUser = $this->getOrCreateClientUser((string) $company, 'password');

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

                $safeCompany = Str::slug((string) $company) ?: 'afs';
                $baseName = $safeCompany . '-' . now()->format('Ymd-His') . '-' . Str::random(6);

                $docxAbs = $outDirAbs . '/' . $baseName . '.docx';
                $pdfRel  = $outDirRel . '/' . $baseName . '.pdf';
                $pdfName = $baseName . '.pdf';


                // dd($rowAssoc);
                // Create DB row immediately
                $fileRow = File::create([
                    'client_id'      => $clientUser->id,
                    'company_name'   => $company ?: null,
                    'original_name'  => $pdfName,
                    'path'           => null,
                    'status'         => $status === 'incomplete' ? 'incomplete' : 'processing',
                    'missing_fields' => $missingFields,
                    'filled_fields'  => $filledFields,
                    'raw_data' => $rowAssoc
                ]);

                // Only generate DOCX/PDF when completed
                if ($status === 'incomplete') {
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

    /* ---------------------------------
     * Public API (Queue-friendly version)
     *  - Call this inside a Job (chunk-based)
     * --------------------------------- */

    public function processCsvChunkToPdf(
        string $csvAbsPath,
        string $templatePath,
        int $chunkIndex,
        int $chunkSize = 30
    ): void {
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }
        if (!file_exists($csvAbsPath)) {
            throw new \RuntimeException("CSV not found: {$csvAbsPath}");
        }

        $rows = $this->readCsvAssocRowsFromPath($csvAbsPath);
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

        $offset = $chunkIndex * $chunkSize;
        $chunk = array_slice($rows, $offset, $chunkSize);
        if (!$chunk) return;

        $docxAbsList = [];
        $docxToDbMeta = []; // baseName => [file_id, pdf_rel]

        foreach ($chunk as $rowAssoc) {
            $csvMap = [];
            foreach ($rowAssoc as $header => $val) {
                $csvMap[$this->normKey((string) $header)] = $val;
            }

            $company =
                $csvMap[$this->normKey('COMPANY NAME')] ??
                $csvMap[$this->normKey('Company Name')] ??
                $csvMap[$this->normKey('company_name')] ??
                'AFS';

            $clientUser = $this->getOrCreateClientUser((string) $company, 'password');

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

            $safeCompany = Str::slug((string) $company) ?: 'afs';
            $baseName = $safeCompany . '-' . now()->format('Ymd-His') . '-' . Str::random(6);

            $docxAbs = $outDirAbs . '/' . $baseName . '.docx';
            $pdfRel  = $outDirRel . '/' . $baseName . '.pdf';
            $pdfName = $baseName . '.pdf';


            $fileRow = File::create([
                'client_id'      => $clientUser->id,
                'company_name'   => $company ?: null,
                'original_name'  => $pdfName,
                'path'           => null,
                'status'         => $status === 'incomplete' ? 'incomplete' : 'processing',
                'missing_fields' => $missingFields,
                'filled_fields'  => $filledFields,
            ]);

            if ($status === 'incomplete') continue;

            if ($status === 'incomplete') continue;

            $this->replaceInDocxAllParts($templatePath, $docxAbs, $repl);

            $docxAbsList[] = $docxAbs;
            $docxToDbMeta[$baseName] = [
                'file_id' => $fileRow->id,
                'pdf_rel' => $pdfRel,
            ];
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

    /* ---------------------------------
     * CSV Readers
     * --------------------------------- */

    private function readCsvAssocRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) return [];

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn($h) => trim((string) $h), $headers);

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

    private function readCsvAssocRowsFromPath(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) return [];

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn($h) => trim((string) $h), $headers);

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

    /* ---------------------------------
     * Template placeholder extraction
     * --------------------------------- */

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
                    $ph = trim((string) $ph);
                    if ($ph !== '') $placeholders[] = $ph;
                }
            }
        }

        $zip->close();

        $placeholders = array_values(array_unique($placeholders));
        sort($placeholders);
        return $placeholders;
    }

    /* ---------------------------------
     * Replace placeholders in DOCX
     * --------------------------------- */

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

            // normalize placeholders that are split by Word XML runs:
            $xml = preg_replace_callback('/\{([\s\S]*?)\}/u', function ($m) {
                $inside = $m[1];
                $inside = preg_replace('/<[^>]+>/', '', $inside);
                $inside = html_entity_decode($inside, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $inside = preg_replace('/\s+/u', ' ', $inside);
                $inside = trim($inside);
                return '{' . $inside . '}';
            }, $xml);

            foreach ($repl as $key => $value) {
                $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $pattern = '/\{\s*' . preg_quote($key, '/') . '\s*\}/u';
                $xml = preg_replace($pattern, $value, $xml);
            }

            $zip->addFromString($part, $xml);
        }

        $zip->close();
    }

    /* ---------------------------------
     * DOCX -> PDF (LibreOffice)
     * --------------------------------- */

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
    public function regeneratePdfFromFile(File $file, string $templatePath): void
    {
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template not found: {$templatePath}");
        }

        $raw = $file->raw_data ?? [];
        if (!is_array($raw) || count($raw) === 0) {
            throw new \RuntimeException("This file has no raw_data to regenerate.");
        }

        $placeholders = $this->templatePlaceholdersAllParts($templatePath);
        if (count($placeholders) === 0) {
            throw new \RuntimeException("No placeholders found in template.");
        }

        $outDirRel = 'generated/';
        $outDirAbs = storage_path('app/public/' . $outDirRel);
        if (!is_dir($outDirAbs)) {
            mkdir($outDirAbs, 0775, true);
        }

        // Build normalized CSV-like map from saved raw_data
        $csvMap = [];
        foreach ($raw as $header => $val) {
            $csvMap[$this->normKey((string) $header)] = $val;
        }

        $company =
            $csvMap[$this->normKey('COMPANY NAME')] ??
            $csvMap[$this->normKey('Company Name')] ??
            $csvMap[$this->normKey('company_name')] ??
            ($file->company_name ?? 'AFS');

        // Build replacements + missing/filled again
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

        // If still incomplete after edit, mark + stop
        if (count($missingFields) > 0) {
            $file->update([
                'company_name'    => $company ?: null,
                'status'          => 'incomplete',
                'path'            => null,
                'missing_fields'  => $missingFields,
                'filled_fields'   => $filledFields,
            ]);
            return;
        }

        // Create new output name every regenerate (or reuse old base if you want)
        $safeCompany = Str::slug((string) $company) ?: 'afs';
        $baseName = $safeCompany . '-' . now()->format('Ymd-His') . '-' . Str::random(6);

        $docxAbs = $outDirAbs . '/' . $baseName . '.docx';
        $pdfAbs  = $outDirAbs . '/' . $baseName . '.pdf';
        $pdfRel  = $outDirRel . '/' . $baseName . '.pdf';

        // Mark processing
        $file->update([
            'company_name'    => $company ?: null,
            'status'          => 'processing',
            'path'            => null,
            'missing_fields'  => $missingFields,
            'filled_fields'   => $filledFields,
        ]);

        // Generate docx then convert to pdf
        $this->replaceInDocxAllParts($templatePath, $docxAbs, $repl);
        $this->sofficeBatchConvert($outDirAbs, [$docxAbs]);

        @unlink($docxAbs);

        if (file_exists($pdfAbs)) {
            $file->update([
                'status' => 'completed',
                'path'   => $pdfRel,
                'original_name' => $baseName . '.pdf',
            ]);
        } else {
            $file->update([
                'status' => 'failed',
                'path'   => null,
            ]);
        }
    }
}
