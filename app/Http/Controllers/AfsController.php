<?php

namespace App\Http\Controllers;

use App\Http\Services\AfsService;
use App\Models\File;
use Illuminate\Http\Request;
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
                $query->where(function ($sub) use ($searchLower) {
                    $sub->whereRaw('LOWER(company_name) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw('LOWER(original_name) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw('LOWER(status) LIKE ?', ["%{$searchLower}%"])
                        ->orWhereRaw('LOWER(president_name) LIKE ?', ["%{$searchLower}%"]);
                });
            })
            ->when(in_array($status, ['completed', 'incomplete', 'failed', 'processing', 'skipped'], true), function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($document === 'no_document', fn($q) => $q->whereNull('path'))
            ->when($document === 'with_document', fn($q) => $q->whereNotNull('path'))
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return Inertia::render('AFSScanner/Index', [
            'generatedFiles' => $generatedFiles,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'document' => $document,
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

        return back()->with('success', 'Processed CSV rows successfully.');
    }

    public function updateRawData(Request $request, File $file, AfsService $afs)
    {
        $validated = $request->validate([
            'raw_data' => ['required', 'array'],
        ]);

        try {
            $afs->updateFileRawData($file, $validated['raw_data']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Data updated.');
    }

    public function regenerate(File $file, AfsService $afs)
    {
        $templatePath = storage_path('app/templates/afs-template.docx');

        try {
            $afs->regenerateSingleFromRawData($file, $templatePath);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'PDF regenerated.');
    }
}