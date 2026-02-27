<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Http\Services\AfsService;
use App\Models\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AfsController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $searchLower = strtolower($search);
        $status = strtolower(trim((string) $request->query('status', 'all')));
        $document = strtolower(trim((string) $request->query('document', 'all')));

        $generatedFiles = File::query()
            ->when($search !== '', function ($query) use ($searchLower) {
                $query->where(function ($subQuery) use ($searchLower) {
                    $subQuery
                        ->whereRaw('LOWER(company_name) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw('LOWER(original_name) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw(
                            "CASE WHEN path IS NULL THEN 'no document generated because of missing fields' ELSE '' END LIKE ?",
                            ["%{$searchLower}%"]
                        );
                });
            })
            ->when(in_array($status, ['completed', 'incomplete'], true), function ($query) use ($status) {
                $query->whereRaw('LOWER(status) = ?', [$status]);
            })
            ->when($document === 'no_document', function ($query) {
                $query->whereNull('path');
            })
            ->when($document === 'with_document', function ($query) {
                $query->whereNotNull('path');
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('AFSScanner/Index', [
            'generatedFiles' => $generatedFiles,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['completed', 'incomplete'], true) ? $status : 'all',
                'document' => in_array($document, ['all', 'no_document', 'with_document'], true) ? $document : 'all',
            ],
        ]);
    }

    public function parse(Request $request, AfsService $afs)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $templatePath = storage_path('app/templates/afs-template.docx');

        try {
            $afs->processCsvToPdfBatch($request->file('file'), $templatePath);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Processed CSV rows successfully (PDF generated).');
    }

    public function updateRawData(Request $request, File $file)
    {
        $data = $request->validate([
            'raw_data' => ['required', 'array'],
        ]);

        $oldRaw = $file->raw_data ?? [];
        $newRaw = $data['raw_data'];

        foreach ($newRaw as $key => $newValue) {

            $oldValue = $oldRaw[$key] ?? null;

            // normalize to string to avoid false positives
            $old = trim((string) $oldValue);
            $new = trim((string) $newValue);

            if ($old !== $new) {

                History::create([
                    'user_id'   => Auth::id(),
                    'file_id'   => $file->id,
                    'field'     => $key,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ]);
            }
        }

        // Save new raw_data
        $file->update([
            'raw_data' => $newRaw,
        ]);

        return back()->with('success', 'Saved.');
    }

    public function regenerate(Request $request, File $file, AfsService $afs)
    {
        $templatePath = storage_path('app/templates/afs-template.docx');

        try {
            $afs->regeneratePdfFromFile($file, $templatePath);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Regenerated PDF.');
    }
}
