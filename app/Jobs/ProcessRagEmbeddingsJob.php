<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Library\RagService;

class ProcessRagEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes timeout for handling large document chunks
    public $tries = 3;

    protected $documentId;
    protected $chunks;

    /**
     * Create a new job instance.
     *
     * @param int $documentId
     * @param array $chunks
     */
    public function __construct(int $documentId, array $chunks)
    {
        $this->documentId = $documentId;
        $this->chunks = $chunks;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ragService = new RagService();

        // Check if the document still exists
        $document = DB::table('tb_rag_documents')->where('id', $this->documentId)->first();
        if (!$document) {
            Log::warning("[ProcessRagEmbeddingsJob] Document ID {$this->documentId} not found. Aborting embedding generation.");
            return;
        }

        Log::info("[ProcessRagEmbeddingsJob] Starting embedding generation for Document ID: {$this->documentId} ({$document->file_name}) with " . count($this->chunks) . " chunks.");

        $successCount = 0;
        $failCount = 0;

        foreach ($this->chunks as $index => $chunk) {
            try {
                $embedding = $ragService->generateEmbedding($chunk);

                if (!empty($embedding)) {
                    DB::table('tb_rag_chunks')->insert([
                        'document_id' => $this->documentId,
                        'content'     => $chunk,
                        'embedding'   => json_encode($embedding),
                        'created_at'  => now(),
                        'updated_at'  => now()
                    ]);
                    $successCount++;
                } else {
                    $failCount++;
                    Log::warning("[ProcessRagEmbeddingsJob] Empty embedding returned for Document ID: {$this->documentId}, chunk index: {$index}.");
                }
            } catch (\Throwable $e) {
                $failCount++;
                Log::error("[ProcessRagEmbeddingsJob] Exception on chunk index {$index} for Document ID {$this->documentId}: " . $e->getMessage());
            }
        }

        Log::info("[ProcessRagEmbeddingsJob] Completed embedding generation for Document ID: {$this->documentId}. Success: {$successCount}, Failed: {$failCount}.");
    }
}
