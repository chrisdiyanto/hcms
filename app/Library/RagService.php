<?php

namespace App\Library;

class RagService
{
    /**
     * Hitung kemiripan kosinus (Cosine Similarity) antara dua vektor numerik.
     *
     * @param array $vec1 Vektor pertama (array float)
     * @param array $vec2 Vektor kedua (array float)
     * @return float Nilai kemiripan antara -1.0 dan 1.0 (1.0 berarti identik)
     */
    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        
        $count = count($vec1);
        if ($count !== count($vec2) || $count === 0) {
            return 0.0;
        }
        
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $normA += $vec1[$i] * $vec1[$i];
            $normB += $vec2[$i] * $vec2[$i];
        }
        
        return ($normA == 0 || $normB == 0) ? 0.0 : $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Memotong teks panjang menjadi chunks kecil dengan overlap.
     * Berusaha mencari batas paragraf, kalimat, atau kata agar tidak memotong kalimat di tengah.
     *
     * @param string $text Teks sumber
     * @param int $size Batas maksimal panjang chunk (karakter)
     * @param int $overlap Jumlah overlap antar chunk (karakter)
     * @return array Array berisi teks chunks
     */
    public function chunkText(string $text, int $size = 1000, int $overlap = 200): array
    {
        $chunks = [];
        $textLength = strlen($text);
        
        if ($textLength <= $size) {
            return [trim($text)];
        }
        
        $start = 0;
        while ($start < $textLength) {
            $chunk = substr($text, $start, $size);
            
            if ($start + $size < $textLength) {
                // Cari posisi spasi, tanda titik, atau baris baru terakhir untuk pemotongan yang rapi
                $lastNewline = strrpos($chunk, "\n");
                $lastPeriod = strrpos($chunk, '.');
                $lastSpace = strrpos($chunk, ' ');
                
                $cutPos = false;
                // Prioritaskan baris baru (> 70% dari ukuran chunk)
                if ($lastNewline !== false && $lastNewline > $size * 0.7) {
                    $cutPos = $lastNewline + 1;
                }
                // Jika tidak ada, gunakan titik (. > 70% dari ukuran chunk)
                elseif ($lastPeriod !== false && $lastPeriod > $size * 0.7) {
                    $cutPos = $lastPeriod + 1;
                }
                // Jika tidak ada, potong di spasi kata terakhir (> 70% dari ukuran chunk)
                elseif ($lastSpace !== false && $lastSpace > $size * 0.7) {
                    $cutPos = $lastSpace;
                }
                
                if ($cutPos !== false) {
                    $chunk = substr($chunk, 0, $cutPos);
                    $start += $cutPos - $overlap;
                } else {
                    $start += $size - $overlap;
                }
            } else {
                $start += $size;
            }
            
            $chunk = trim($chunk);
            if (!empty($chunk)) {
                $chunks[] = $chunk;
            }
        }
        
        return $chunks;
    }

    /**
     * Generate vector embedding untuk teks menggunakan AI provider yang terkonfigurasi.
     *
     * @param string $text Teks yang akan di-embed
     * @return array|null Vektor float array atau null jika gagal
     */
    public function generateEmbedding(string $text): ?array
    {
        $provider = config('ai.ai_provider') ?: config('ai.provider') ?: env('AI_PROVIDER', '');
        $apiKey = config('ai.ai_api_key') ?: config('ai.api_key') ?: env('AI_API_KEY', '');
        $endpoint = config('ai.ai_api_endpoint') ?: config('ai.api_endpoint') ?: env('AI_API_ENDPOINT', '');
        $model = config('ai.ai_model') ?: config('ai.model') ?: env('AI_MODEL', '');

        if (!$provider || !$apiKey) {
            return null;
        }

        if ($provider === 'gemini') {
            // Default model embedding untuk Gemini adalah gemini-embedding-2
            $embedModel = 'gemini-embedding-2';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$embedModel}:embedContent?key={$apiKey}";
            
            $payload = [
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];

            $response = $this->curlPost($url, $payload);
            if (isset($response['embedding']['values'])) {
                return $response['embedding']['values'];
            }
            return null;
        }

        // Default ke API berbasis format OpenAI (OpenAI, DeepSeek, Local Ollama, dll.)
        $url = $endpoint ? rtrim($endpoint, '/') . '/embeddings' : "https://api.openai.com/v1/embeddings";
        if ($provider === 'deepseek') {
            $url = $endpoint ?: "https://api.deepseek.com/v1/embeddings";
        }
        
        $headers = [
            "Authorization: Bearer {$apiKey}",
            "Content-Type: application/json"
        ];

        // Tentukan model embedding yang sesuai
        $embedModel = 'text-embedding-3-small';
        if ($provider === 'local') {
            $embedModel = $model ?: 'nomic-embed-text';
        } elseif ($model && strpos($model, 'embed') !== false) {
            $embedModel = $model;
        }

        $payload = [
            'model' => $embedModel,
            'input' => $text
        ];

        $response = $this->curlPost($url, $payload, $headers);
        if (isset($response['data'][0]['embedding'])) {
            return $response['data'][0]['embedding'];
        }

        return null;
    }

    /**
     * Cari potongan teks (chunks) terdekat secara semantik berdasarkan query embedding.
     *
     * @param array $queryEmbedding Vektor float array dari pertanyaan
     * @param float $threshold Batas nilai kemiripan minimal (default: 0.65)
     * @param int $limit Batas maksimal jumlah chunk yang diambil (default: 3)
     * @return array Array berisi chunks relevan beserta nama file dokumen asalnya
     */
    public function retrieveRelevantChunks(array $queryEmbedding, float $threshold = 0.65, int $limit = 3): array
    {
        $chunks = \DB::table('tb_rag_chunks')
            ->join('tb_rag_documents', 'tb_rag_chunks.document_id', '=', 'tb_rag_documents.id')
            ->select('tb_rag_chunks.content', 'tb_rag_chunks.embedding', 'tb_rag_documents.file_name')
            ->get();

        $matches = [];
        foreach ($chunks as $chunk) {
            $chunkVector = json_decode($chunk->embedding, true);
            if (is_array($chunkVector)) {
                $similarity = $this->cosineSimilarity($queryEmbedding, $chunkVector);
                if ($similarity >= $threshold) {
                    $matches[] = [
                        'content' => $chunk->content,
                        'file_name' => $chunk->file_name,
                        'similarity' => $similarity
                    ];
                }
            }
        }

        // Urutkan berdasarkan kemiripan tertinggi (descending)
        usort($matches, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($matches, 0, $limit);
    }

    /**
     * Helper cURL POST request
     */
    private function curlPost(string $url, array $payload, array $headers = ["Content-Type: application/json"]): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
