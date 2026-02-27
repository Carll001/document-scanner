<?php

namespace App\Jobs;

use App\Http\Services\AfsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAfsChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60000; // seconds (10 mins) for heavy soffice work
    public int $tries = 2;

    public function __construct(
        public string $csvAbsPath,
        public string $templateAbsPath,
        public int $chunkIndex,
        public int $chunkSize = 30
    ) {}

    public function handle(AfsService $afs): void
    {
        $afs->processCsvChunkToPdf(
            $this->csvAbsPath,
            $this->templateAbsPath,
            $this->chunkIndex,
            $this->chunkSize
        );
    }
}