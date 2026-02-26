<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\File;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Get total client users (exclude super admin and registrar)
        $totalClients = User::query()
            ->where('role', 'client')
            ->where('email', '!=', 'superadmin@gmail.com')
            ->count();

        // Total files for dashboard cards: completed + incomplete
        $totalFiles = File::query()
            ->whereIn('status', ['completed', 'incomplete'])
            ->count();

        // Get files by status
        $filesByStatus = File::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Get recent files (latest 8)
        $recentFiles = File::query()
            ->latest()
            ->limit(8)
            ->get(['id', 'company_name', 'original_name', 'status', 'created_at']);

        return Inertia::render('Dashboard', [
            'totalClients' => $totalClients,
            'totalFiles' => $totalFiles,
            'filesByStatus' => $filesByStatus,
            'recentFiles' => $recentFiles,
        ]);
    }
}
