<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use ZipArchive;

class AfsController extends Controller
{
    public function index(Request $request)
    {
        $generatedFiles = File::query()
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return Inertia::render('AFSScanner/Index', [
            'generatedFiles' => $generatedFiles,
        ]);
    }

    public function parse(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $templatePath = storage_path('app/templates/afs-template.docx');
        if (!file_exists($templatePath)) {
            abort(404, 'Template not found: storage/app/templates/afs-template.docx');
        }

        // ✅ Read CSV rows (assoc by header)
        $rows = $this->readCsvAssocRows($request->file('file'));
        if (count($rows) === 0) {
            return back()->with('error', 'CSV has no data rows.');
        }

        // ✅ Extract placeholders from template (document + headers + footers)
        $placeholders = $this->templatePlaceholdersAllParts($templatePath); // ["SHE", "PAYABLE TO SUPPLIERS", ...]
        if (count($placeholders) === 0) {
            return back()->with('error', 'No placeholders found in template.');
        }

        // Output directory must be inside storage/app/public
        $outDirRel = 'generated/afs'; // relative to storage/app/public
        $outDirAbs = storage_path('app/public/' . $outDirRel);
        if (!is_dir($outDirAbs)) {
            mkdir($outDirAbs, 0775, true);
        }

        foreach ($rows as $rowIndex => $rowAssoc) {
            // Build normalized CSV map: NORM_HEADER => value
            $csvMap = [];
            foreach ($rowAssoc as $header => $val) {
                $csvMap[$this->normKey($header)] = $val;
            }

            // Build replacements: placeholderName => value (NO braces)
            $repl = [];
            $missingFields = [];
            $filledFields = [];

            foreach ($placeholders as $ph) {
                $val = $csvMap[$this->normKey($ph)] ?? null;

                if ($this->isMissingValue($val)) {
                    $missingFields[] = $ph;
                    $repl[$ph] = 'N / A'; // or '' if you prefer blank
                } else {
                    $filledFields[] = $ph;
                    $repl[$ph] = (string) $val;
                }
            }

            $status = count($missingFields) > 0 ? 'incomplete' : 'completed';

            // Decide company name for filename + DB
            $company = $rowAssoc['COMPANY NAME']
                ?? $rowAssoc['Company Name']
                ?? $rowAssoc['company_name']
                ?? 'AFS';

            $safeCompany = Str::slug((string) $company) ?: 'afs';

            $docxFileName = $safeCompany . '-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.docx';
            $outputAbs = $outDirAbs . '/' . $docxFileName;
            $outputRel = $outDirRel . '/' . $docxFileName; // saved to DB, used by /storage/{path}

            // ✅ Only generate the doc when completed (you can change this behavior)
            $pathToSave = null;
            $originalNameToSave = $request->file('file')->getClientOriginalName();

            if ($status === 'completed') {
                $this->replaceInDocxAllParts($templatePath, $outputAbs, $repl);
                $pathToSave = $outputRel;
                $originalNameToSave = $docxFileName;
            }

            File::create([
                'company_name'   => $company ?: null,
                'original_name'  => $originalNameToSave, // generated name if completed, else uploaded CSV name
                'path'           => $pathToSave,         // null if incomplete
                'status'         => $status,             // completed | incomplete
                'missing_fields' => $missingFields,
                'filled_fields'  => $filledFields,
            ]);
        }

        return back()->with('success', 'Processed CSV rows successfully.');
    }

    /**
     * Read CSV and return rows as associative arrays keyed by header.
     */
    private function readCsvAssocRows(\Illuminate\Http\UploadedFile $file): array
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

    /**
     * Extract placeholders like {SHE} from document + headers + footers safely (via w:t text nodes).
     * Returns placeholder NAMES WITHOUT braces: ["SHE", "PAYABLE TO SUPPLIERS", ...]
     */
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

            // extract only <w:t> text, then search placeholders
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

    /**
     * Copy template -> output, then replace placeholders in document + headers + footers.
     * Works even when placeholders are split across w:t runs (by normalizing inside {...} regions).
     *
     * $repl keys are placeholder NAMES WITHOUT braces.
     */
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

            // 1) Normalize any placeholder regions so split runs become searchable:
            //    {PAYABLE</w:t>...<w:t> TO SUPPLIERS} -> {PAYABLE TO SUPPLIERS}
            $xml = preg_replace_callback('/\{([\s\S]*?)\}/u', function ($m) {
                $inside = $m[1];
                // remove any XML tags inside placeholder region, keep spaces
                $inside = preg_replace('/<[^>]+>/', '', $inside);
                $inside = html_entity_decode($inside, ENT_QUOTES | ENT_XML1, 'UTF-8');
                $inside = preg_replace('/\s+/u', ' ', $inside);
                $inside = trim($inside);
                return '{' . $inside . '}';
            }, $xml);

            // 2) Replace placeholders
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