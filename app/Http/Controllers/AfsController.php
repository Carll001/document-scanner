<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Http\Services\AfsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
}