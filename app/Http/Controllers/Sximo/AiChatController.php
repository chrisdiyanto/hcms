<?php namespace App\Http\Controllers\sximo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Core\Groups;

class AiChatController extends Controller {

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            // Check authorization groups
            $allowedGroupsStr = config('ai.ai_allowed_groups') ?? env('AI_ALLOWED_GROUPS', '');
            $allowedGroups = $allowedGroupsStr !== '' ? explode(',', $allowedGroupsStr) : [];
            
            // Clean up group IDs (trim spaces just in case)
            $allowedGroups = array_map('trim', $allowedGroups);
            
            $userGroupId = session('gid'); // group id of logged-in user
            
            if (!in_array($userGroupId, $allowedGroups)) {
                return redirect('dashboard')
                    ->with('message', 'You do not have permission to access the AI Chatbot!')
                    ->with('status', 'error');
            }
            return $next($request);
        });
    }

    public function getIndex(Request $request, $sessionId = null)
    {
        $userId = session('uid');
        
        // Fetch sessions
        $sessions = DB::table('tb_ai_chat_sessions')
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();
            
        // If no session ID is provided, load the most recent session
        if (!$sessionId && $sessions->count() > 0) {
            $sessionId = $sessions->first()->id;
        }

        $messages = [];
        if ($sessionId !== null) {
            $this->ensureSessionOwnership($sessionId, $userId);

            $messages = DB::table('tb_ai_chat_messages')
                ->where('session_id', $sessionId)
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $this->data = [
            'pageTitle' => 'AI Chatbot',
            'pageNote'  => 'Chat with your configured AI assistant',
            'sessions'  => $sessions,
            'activeSessionId' => $sessionId,
            'messages'  => $messages,
            'active'    => 'aichat'
        ];

        return view('sximo.config.aichat', $this->data);
    }

    public function getActiveSession()
    {
        $userId = session('uid');
        $session = \DB::table('tb_ai_chat_sessions')
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->first();
            
        if ($session) {
            $messages = \DB::table('tb_ai_chat_messages')
                ->where('session_id', $session->id)
                ->orderBy('created_at', 'asc')
                ->get();
                
            return response()->json([
                'status'    => 'success',
                'sessionId' => $session->id,
                'title'     => $session->title,
                'messages'  => $messages
            ]);
        }
        
        return response()->json(['status' => 'empty']);
    }

    public function postReset()
    {
        $userId = session('uid');
        $sessionId = \DB::table('tb_ai_chat_sessions')->insertGetId([
            'user_id' => $userId,
            'title'   => 'New Chat Session',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json([
            'status'    => 'success',
            'sessionId' => $sessionId
        ]);
    }

    public function postMessage(Request $request)
    {
        $userId = session('uid');
        $sessionId = $request->input('session_id');
        $userMessage = $request->input('message');

        if (!$userMessage) {
            return response()->json(['status' => 'error', 'message' => 'Message is empty']);
        }

        // If no session exists, create one
        if (!$sessionId) {
            $sessionId = DB::table('tb_ai_chat_sessions')->insertGetId([
                'user_id' => $userId,
                'title'   => substr($userMessage, 0, 40) . (strlen($userMessage) > 40 ? '...' : ''),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $this->ensureSessionOwnership($sessionId, $userId);
        }

        // Save user's message
        DB::table('tb_ai_chat_messages')->insert([
            'session_id' => $sessionId,
            'role'       => 'user',
            'message'    => $userMessage,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Update session's updated_at timestamp
        DB::table('tb_ai_chat_sessions')
            ->where('id', $sessionId)
            ->update(['updated_at' => now()]);

        // Retrieve chat history for context
        $chatLogs = DB::table('tb_ai_chat_messages')
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get();

        $context = [];
        foreach ($chatLogs as $log) {
            $context[] = [
                'role' => $log->role,
                'content' => $log->message
            ];
        }

        // --- RAG INTEGRATION ---
        $ragService = new \App\Library\RagService();
        $hasDocs = DB::table('tb_rag_documents')->exists();
        
        if ($hasDocs) {
            $queryEmbedding = $ragService->generateEmbedding($userMessage);
            if (is_array($queryEmbedding)) {
                $relevantChunks = $ragService->retrieveRelevantChunks($queryEmbedding, 0.65, 3);
                
                if (count($relevantChunks) > 0) {
                    $referenceText = "";
                    foreach ($relevantChunks as $chunk) {
                        $referenceText .= "[Sumber Dokumen: " . $chunk['file_name'] . "]\n" . $chunk['content'] . "\n\n";
                    }
                    
                    // Modifikasi pesan terakhir di context (pesan user yang baru saja dikirim)
                    $lastIndex = count($context) - 1;
                    if ($lastIndex >= 0 && $context[$lastIndex]['role'] === 'user') {
                        $originalMessage = $context[$lastIndex]['content'];
                        $context[$lastIndex]['content'] = "Gunakan informasi referensi dokumen berikut untuk menjawab pertanyaan di bagian bawah. Jawablah secara natural berdasarkan dokumen tersebut. Jika informasi tidak terdapat pada dokumen referensi, katakan bahwa Anda tidak mengetahuinya berdasarkan dokumen resmi.\n\n[REFERENSI DOKUMEN]\n" . trim($referenceText) . "\n[AKHIR REFERENSI]\n\nPertanyaan Pengguna: " . $originalMessage;
                    }
                }
            }
        }
        // --- END RAG INTEGRATION ---

        // Call AI Agent Loop (Tool Calling loop)
        $aiResponse = $this->executeAgentLoop($context);

        // Save AI's response
        DB::table('tb_ai_chat_messages')->insert([
            'session_id' => $sessionId,
            'role'       => 'assistant',
            'message'    => $aiResponse,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'status'     => 'success',
            'sessionId'  => $sessionId,
            'userMessage'=> $userMessage,
            'aiResponse' => $aiResponse
        ]);
    }

    public function postStream(Request $request)
    {
        $userId = session('uid');
        $sessionId = $request->input('session_id');
        $userMessage = $request->input('message');

        if (!$userMessage || trim($userMessage) === '') {
            return response()->json(['status' => 'error', 'message' => 'Message is empty'], 400);
        }

        // If no session exists, create one
        if (!$sessionId) {
            $sessionId = DB::table('tb_ai_chat_sessions')->insertGetId([
                'user_id'    => $userId,
                'title'      => substr($userMessage, 0, 40) . (strlen($userMessage) > 40 ? '...' : ''),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $this->ensureSessionOwnership($sessionId, $userId);
        }

        // Save user's message
        DB::table('tb_ai_chat_messages')->insert([
            'session_id' => $sessionId,
            'role'       => 'user',
            'message'    => $userMessage,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Update session timestamp
        DB::table('tb_ai_chat_sessions')
            ->where('id', $sessionId)
            ->update(['updated_at' => now()]);

        // Retrieve chat history
        $chatLogs = DB::table('tb_ai_chat_messages')
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get();

        $context = [];
        foreach ($chatLogs as $log) {
            $context[] = [
                'role'    => $log->role,
                'content' => $log->message
            ];
        }

        // --- RAG RETRIEVAL ---
        $ragService = new \App\Library\RagService();
        $hasDocs = DB::table('tb_rag_documents')->exists();
        if ($hasDocs) {
            $queryEmbedding = $ragService->generateEmbedding($userMessage);
            if (is_array($queryEmbedding)) {
                $relevantChunks = $ragService->retrieveRelevantChunks($queryEmbedding, 0.65, 3);
                if (count($relevantChunks) > 0) {
                    $referenceText = "";
                    foreach ($relevantChunks as $chunk) {
                        $referenceText .= "[Sumber Dokumen: " . $chunk['file_name'] . "]\n" . $chunk['content'] . "\n\n";
                    }
                    $lastIndex = count($context) - 1;
                    if ($lastIndex >= 0 && $context[$lastIndex]['role'] === 'user') {
                        $originalMessage = $context[$lastIndex]['content'];
                        $context[$lastIndex]['content'] = "Gunakan informasi referensi dokumen berikut untuk menjawab pertanyaan di bagian bawah. Jawablah secara natural berdasarkan dokumen tersebut. Jika informasi tidak terdapat pada dokumen referensi, katakan bahwa Anda tidak mengetahuinya berdasarkan dokumen resmi.\n\n[REFERENSI DOKUMEN]\n" . trim($referenceText) . "\n[AKHIR REFERENSI]\n\nPertanyaan Pengguna: " . $originalMessage;
                    }
                }
            }
        }
        // --- END RAG ---

        $provider = config('ai.ai_provider') ?: env('AI_PROVIDER', '');
        $apiKey = config('ai.ai_api_key') ?: env('AI_API_KEY', '');
        $endpoint = config('ai.ai_api_endpoint') ?: env('AI_API_ENDPOINT', '');
        $model = config('ai.ai_model') ?: env('AI_MODEL', '');

        if (!$provider || !$apiKey) {
            return response()->json([
                'status'  => 'error',
                'message' => 'AI Provider settings are incomplete. Please configure AI Settings first.'
            ], 400);
        }

        $chartInstruction = "\n\n=== CHART GENERATION GUIDELINE ===\n" .
            "Jika pengguna meminta grafik, chart, diagram, atau visualisasi data (seperti perbandingan, tren, atau proporsi), Anda HARUS menyajikan data tersebut dalam bentuk grafik interaktif dengan menghasilkan sebuah blok kode bertipe 'chart' berisi konfigurasi JSON untuk Chart.js (versi 3/4). Format blok kode harus tepat seperti ini:\n" .
            "```chart\n" .
            "{\n" .
            "  \"type\": \"bar\",\n" .
            "  \"data\": {\n" .
            "    \"labels\": [\"Label A\", \"Label B\", \"Label C\"],\n" .
            "    \"datasets\": [{\n" .
            "      \"label\": \"Judul Dataset\",\n" .
            "      \"data\": [10, 20, 15]\n" .
            "    }]\n" .
            "  },\n" .
            "  \"options\": {\n" .
            "    \"plugins\": {\n" .
            "      \"title\": {\n" .
            "        \"display\": true,\n" .
            "        \"text\": \"Judul Grafik\"\n" .
            "      }\n" .
            "    }\n" .
            "  }\n" .
            "}\n" .
            "```\n" .
            "Catatan penting:\n" .
            "1. Jangan menyisipkan elemen HTML atau CSS kustom untuk merender chart secara langsung. Cukup gunakan format markdown ```chart untuk membungkus objek JSON konfigurasi Chart.js tersebut.\n" .
            "2. JSON harus benar-benar valid. JANGAN gunakan komentar (seperti // atau /* */) di dalam objek JSON.\n" .
            "3. Pilihan jenis chart ('type') yang valid adalah: \"bar\", \"line\", \"pie\", \"doughnut\", \"polarArea\", \"radar\".\n" .
            "4. Anda juga dapat menyajikan penjelasan teks atau tabel markdown biasa sebelum atau sesudah grafik tersebut.";

        $baseSysInstruction = config('ai.ai_system_instruction') ?? env('AI_SYSTEM_INSTRUCTION', '');
        $sysInstruction = $baseSysInstruction ? $baseSysInstruction . $chartInstruction : trim($chartInstruction);

        return response()->stream(function () use ($provider, $apiKey, $endpoint, $model, $sysInstruction, $context, $sessionId) {
            // Disable output buffering
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            // Send start event
            echo "data: " . json_encode([
                'event'     => 'start',
                'sessionId' => $sessionId
            ]) . "\n\n";
            flush();

            $fullResponse = '';

            // Handle Streaming per provider
            if ($provider === 'gemini') {
                $geminiModel = $model ?: 'gemini-2.5-flash';
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:streamGenerateContent?alt=sse&key={$apiKey}";

                $contents = [];
                foreach ($context as $msg) {
                    $contents[] = [
                        'role'  => $msg['role'] == 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $msg['content']]]
                    ];
                }

                $payload = ['contents' => $contents];
                if ($sysInstruction) {
                    $payload['systemInstruction'] = [
                        'parts' => [['text' => $sysInstruction]]
                    ];
                }

                $buffer = '';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$fullResponse, &$buffer) {
                    $buffer .= $chunk;
                    $lines = explode("\n", $buffer);
                    $buffer = array_pop($lines); // hold incomplete line in buffer

                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (str_starts_with($trimmed, 'data: ')) {
                            $jsonStr = substr($trimmed, 6);
                            $data = json_decode($jsonStr, true);
                            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                                $token = $data['candidates'][0]['content']['parts'][0]['text'];
                                $fullResponse .= $token;
                                echo "data: " . json_encode(['event' => 'token', 'text' => $token]) . "\n\n";
                                flush();
                            }
                        }
                    }
                    return strlen($chunk);
                });
                curl_exec($ch);
                curl_close($ch);

            } elseif ($provider === 'claude') {
                $claudeModel = $model ?: 'claude-3-5-sonnet-20240620';
                $url = $endpoint ?: "https://api.anthropic.com/v1/messages";

                $headers = [
                    "x-api-key: {$apiKey}",
                    "anthropic-version: 2023-06-01",
                    "Content-Type: application/json"
                ];

                $payload = [
                    'model'      => $claudeModel,
                    'max_tokens' => 2048,
                    'stream'     => true,
                    'messages'   => $context
                ];
                if ($sysInstruction) {
                    $payload['system'] = $sysInstruction;
                }

                $buffer = '';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$fullResponse, &$buffer) {
                    $buffer .= $chunk;
                    $lines = explode("\n", $buffer);
                    $buffer = array_pop($lines);

                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (str_starts_with($trimmed, 'data: ')) {
                            $jsonStr = substr($trimmed, 6);
                            $data = json_decode($jsonStr, true);
                            if (isset($data['delta']['text'])) {
                                $token = $data['delta']['text'];
                                $fullResponse .= $token;
                                echo "data: " . json_encode(['event' => 'token', 'text' => $token]) . "\n\n";
                                flush();
                            }
                        }
                    }
                    return strlen($chunk);
                });
                curl_exec($ch);
                curl_close($ch);

            } else {
                // OpenAI / DeepSeek / Ollama / Local
                $url = $endpoint ? rtrim($endpoint, '/') . '/chat/completions' : "https://api.openai.com/v1/chat/completions";
                if ($provider === 'deepseek') {
                    $url = $endpoint ?: "https://api.deepseek.com/chat/completions";
                    $model = $model ?: "deepseek-chat";
                } elseif ($provider === 'openai') {
                    $model = $model ?: "gpt-4o";
                } else {
                    $url = $endpoint ?: "http://localhost:11434/v1/chat/completions";
                    $model = $model ?: "llama3";
                }

                $headers = [
                    "Authorization: Bearer {$apiKey}",
                    "Content-Type: application/json"
                ];

                $formattedMessages = [];
                if ($sysInstruction) {
                    $formattedMessages[] = [
                        'role'    => 'system',
                        'content' => $sysInstruction
                    ];
                }
                foreach ($context as $msg) {
                    $formattedMessages[] = [
                        'role'    => $msg['role'],
                        'content' => $msg['content']
                    ];
                }

                $payload = [
                    'model'    => $model,
                    'stream'   => true,
                    'messages' => $formattedMessages
                ];

                $buffer = '';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$fullResponse, &$buffer) {
                    $buffer .= $chunk;
                    $lines = explode("\n", $buffer);
                    $buffer = array_pop($lines);

                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (str_starts_with($trimmed, 'data: ')) {
                            $jsonStr = substr($trimmed, 6);
                            if ($jsonStr === '[DONE]') {
                                continue;
                            }
                            $data = json_decode($jsonStr, true);
                            if (isset($data['choices'][0]['delta']['content'])) {
                                $token = $data['choices'][0]['delta']['content'];
                                $fullResponse .= $token;
                                echo "data: " . json_encode(['event' => 'token', 'text' => $token]) . "\n\n";
                                flush();
                            }
                        }
                    }
                    return strlen($chunk);
                });
                curl_exec($ch);
                curl_close($ch);
            }

            // Save assistant message to database
            if (!empty($fullResponse)) {
                DB::table('tb_ai_chat_messages')->insert([
                    'session_id' => $sessionId,
                    'role'       => 'assistant',
                    'message'    => $fullResponse,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Send done event
            echo "data: " . json_encode([
                'event'       => 'done',
                'sessionId'   => $sessionId,
                'fullMessage' => $fullResponse
            ]) . "\n\n";
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function postNewsession(Request $request)
    {
        $userId = session('uid');
        $title = $request->input('title', 'New Chat Session');

        $sessionId = DB::table('tb_ai_chat_sessions')->insertGetId([
            'user_id' => $userId,
            'title'   => $title,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return redirect('sximo/aichat/' . $sessionId);
    }

    public function getDeletesession($id)
    {
        $userId = session('uid');
        
        // Delete session and let foreign key constraints cascade delete messages
        DB::table('tb_ai_chat_sessions')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return redirect('sximo/aichat');
    }

    /**
     * Ensure a chat session belongs to the currently authenticated user.
     */
    private function ensureSessionOwnership($sessionId, $userId)
    {
        $owned = DB::table('tb_ai_chat_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($owned, 404);
    }

    public function runMigration()
    {
        // Require Administrator access (session('gid') == 1) to run migrations!
        if (session('gid') != '1') {
            return "Unauthorized access. Only administrators can run migrations.";
        }
        
        try {
            \Artisan::call('migrate');
            $output = \Artisan::output();
            return "AI Chatbot Migration completed successfully!<br><pre>{$output}</pre><br><a href='" . url('sximo/aichat') . "'>Click here to open AI Chatbot</a>";
        } catch (\Exception $e) {
            return "Migration failed: " . $e->getMessage();
        }
    }

    private function callAi($messages)
    {
        $provider = config('ai.ai_provider') ?: env('AI_PROVIDER', '');
        $apiKey = config('ai.ai_api_key') ?: env('AI_API_KEY', '');
        $endpoint = config('ai.ai_api_endpoint') ?: env('AI_API_ENDPOINT', '');
        $model = config('ai.ai_model') ?: env('AI_MODEL', '');

        if (!$provider || !$apiKey) {
            return "AI settings are incomplete. Please configure AI service provider and API Key in Admin > Configuration > AI Settings.";
        }

        // Instruction to guide AI to generate Chart.js structures inside code blocks
        $chartInstruction = "\n\n=== CHART GENERATION GUIDELINE ===\n" .
            "Jika pengguna meminta grafik, chart, diagram, atau visualisasi data (seperti perbandingan, tren, atau proporsi), Anda HARUS menyajikan data tersebut dalam bentuk grafik interaktif dengan menghasilkan sebuah blok kode bertipe 'chart' berisi konfigurasi JSON untuk Chart.js (versi 3/4). Format blok kode harus tepat seperti ini:\n" .
            "```chart\n" .
            "{\n" .
            "  \"type\": \"bar\",\n" .
            "  \"data\": {\n" .
            "    \"labels\": [\"Label A\", \"Label B\", \"Label C\"],\n" .
            "    \"datasets\": [{\n" .
            "      \"label\": \"Judul Dataset\",\n" .
            "      \"data\": [10, 20, 15]\n" .
            "    }]\n" .
            "  },\n" .
            "  \"options\": {\n" .
            "    \"plugins\": {\n" .
            "      \"title\": {\n" .
            "        \"display\": true,\n" .
            "        \"text\": \"Judul Grafik\"\n" .
            "      }\n" .
            "    }\n" .
            "  }\n" .
            "}\n" .
            "```\n" .
            "Catatan penting:\n" .
            "1. Jangan menyisipkan elemen HTML atau CSS kustom untuk merender chart secara langsung. Cukup gunakan format markdown ```chart untuk membungkus objek JSON konfigurasi Chart.js tersebut.\n" .
            "2. JSON harus benar-benar valid. JANGAN gunakan komentar (seperti // atau /* */) di dalam objek JSON.\n" .
            "3. Pilihan jenis chart ('type') yang valid adalah: \"bar\", \"line\", \"pie\", \"doughnut\", \"polarArea\", \"radar\".\n" .
            "4. Anda juga dapat menyajikan penjelasan teks atau tabel markdown biasa sebelum atau sesudah grafik tersebut.";

        $baseSysInstruction = config('ai.ai_system_instruction') ?? env('AI_SYSTEM_INSTRUCTION', '');
        $sysInstruction = $baseSysInstruction ? $baseSysInstruction . $chartInstruction : trim($chartInstruction);

        if ($provider == 'gemini') {
            $model = $model ?: 'gemini-2.5-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $contents = [];
            foreach ($messages as $msg) {
                $contents[] = [
                    'role' => $msg['role'] == 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $msg['content']]]
                ];
            }

            $payload = ['contents' => $contents];
            if ($sysInstruction) {
                $payload['systemInstruction'] = [
                    'parts' => [['text' => $sysInstruction]]
                ];
            }

            $response = $this->curlPost($url, $payload);
            if (isset($response['error'])) {
                return $this->handleApiError($response);
            }
            if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return $response['candidates'][0]['content']['parts'][0]['text'];
            }
            return 'Error: No response from Gemini. ' . json_encode($response);
        } 
        
        if ($provider == 'claude') {
            $url = $endpoint ?: "https://api.anthropic.com/v1/messages";
            $model = $model ?: "claude-3-5-sonnet-20240620";
            
            $headers = [
                "x-api-key: {$apiKey}",
                "anthropic-version: 2023-06-01",
                "Content-Type: application/json"
            ];

            // Claude API uses 'user' and 'assistant' roles just like our DB
            $payload = [
                'model' => $model,
                'max_tokens' => 2048,
                'messages' => $messages
            ];
            if ($sysInstruction) {
                $payload['system'] = $sysInstruction;
            }

            $response = $this->curlPost($url, $payload, $headers);
            if (isset($response['error'])) {
                return $this->handleApiError($response);
            }
            if (isset($response['content'][0]['text'])) {
                return $response['content'][0]['text'];
            }
            return 'Error: No response from Claude. ' . json_encode($response);
        }

        // Default to OpenAI / DeepSeek / Local Ollama (which use OpenAI API format)
        $url = $endpoint ? rtrim($endpoint, '/') . '/chat/completions' : "https://api.openai.com/v1/chat/completions";
        if ($provider == 'deepseek') {
            $url = $endpoint ?: "https://api.deepseek.com/chat/completions";
            $model = $model ?: "deepseek-chat";
        } elseif ($provider == 'openai') {
            $model = $model ?: "gpt-4o";
        } else {
            // Local/Ollama
            $url = $endpoint ?: "http://localhost:11434/v1/chat/completions";
            $model = $model ?: "llama3";
        }

        $headers = [
            "Authorization: Bearer {$apiKey}",
            "Content-Type: application/json"
        ];

        // Format for OpenAI chat completions
        $formattedMessages = [];
        if ($sysInstruction) {
            $formattedMessages[] = [
                'role'    => 'system',
                'content' => $sysInstruction
            ];
        }
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role'    => $msg['role'],
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $formattedMessages
        ];

        $response = $this->curlPost($url, $payload, $headers);
        if (isset($response['error'])) {
            return $this->handleApiError($response);
        }
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }
        return 'Error: No response from AI. ' . json_encode($response);
    }

    private function handleApiError($response)
    {
        $rawMessage = $response['error']['message'] ?? json_encode($response);
        $code = $response['error']['code'] ?? null;
        $status = $response['error']['status'] ?? '';

        // Check if rate limit or quota exceeded
        if ($code == 429 || $status === 'RESOURCE_EXHAUSTED' || stripos($rawMessage, 'quota') !== false || stripos($rawMessage, 'rate limit') !== false || stripos($rawMessage, 'exceeded') !== false) {
            
            // Try to extract retry time
            if (preg_match('/retry in ([\d\.]+)s/i', $rawMessage, $matches)) {
                $seconds = round((float)$matches[1], 1);
                return "Mohon maaf, batas panggilan API (Rate Limit) gratis saat ini sedang terlampaui. Silakan kirim pesan kembali dalam waktu sekitar {$seconds} detik. 🙏";
            }
            
            return "Mohon maaf, kuota atau batas panggilan API (Rate Limit) gratis Anda telah habis untuk saat ini. Silakan tunggu sekitar 1 menit sebelum mencoba mengirim pesan kembali. 🙏";
        }

        return "Terjadi kendala pada API AI: " . $rawMessage;
    }

    private function executeAgentLoop($initialMessages)
    {
        $provider = config('ai.ai_provider') ?: env('AI_PROVIDER', '');
        
        // Native tool-calling loop for Gemini.
        // For other providers, fall back to normal callAi.
        if ($provider !== 'gemini') {
            $simplifiedContext = [];
            foreach ($initialMessages as $msg) {
                $simplifiedContext[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
            return $this->callAi($simplifiedContext);
        }

        // Initialize Gemini-compatible context
        $geminiMessages = [];
        foreach ($initialMessages as $msg) {
            $geminiMessages[] = [
                'role' => $msg['role'] == 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $maxIterations = 5; 
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;
            
            // Call Gemini API with tool schema
            $response = $this->callGeminiApiRaw($geminiMessages);

            if (isset($response['error'])) {
                return $this->handleApiError($response);
            }

            if (!isset($response['candidates'][0]['content']['parts'])) {
                return 'Error: No response parts from AI model. ' . json_encode($response);
            }

            $parts = $response['candidates'][0]['content']['parts'];
            
            $hasFunctionCall = false;
            $functionCalls = [];
            $textParts = [];

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $hasFunctionCall = true;
                    $functionCalls[] = $part['functionCall'];
                }
                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }

            if ($hasFunctionCall) {
                // Ensure all functionCalls in parts have 'args' formatted as an object (not PHP array [])
                foreach ($parts as &$part) {
                    if (isset($part['functionCall'])) {
                        if (empty($part['functionCall']['args'])) {
                            $part['functionCall']['args'] = (object)[];
                        } else {
                            $part['functionCall']['args'] = (object)$part['functionCall']['args'];
                        }
                    }
                }
                unset($part);

                // Record the model's message (exactly as returned by Gemini with all parts)
                $geminiMessages[] = [
                    'role' => 'model',
                    'parts' => $parts
                ];

                // Prepare function responses
                $responseParts = [];
                foreach ($functionCalls as $funcCall) {
                    $funcName = $funcCall['name'];
                    $funcArgs = $funcCall['args'] ?? [];

                    // Execute the function
                    $observation = $this->executeTool($funcName, $funcArgs);

                    // Add function response part
                    $responseParts[] = [
                        'functionResponse' => [
                            'name' => $funcName,
                            'response' => (object)['result' => $observation]
                        ]
                    ];
                }

                // Record the function responses as a single message with role 'function'
                $geminiMessages[] = [
                    'role' => 'function',
                    'parts' => $responseParts
                ];

                // Continue execution loop to send function responses back to Gemini
                continue;
            } else {
                // If there are no function calls, return the text parts joined together
                if (count($textParts) > 0) {
                    return implode("\n", $textParts);
                }
            }

            break;
        }

        return 'Error: Max tool execution iterations reached without a text response.';
    }

    private function executeTool($name, $args)
    {
        try {
            if ($name === 'get_database_schema') {
                $tables = DB::select('SHOW TABLES');
                $dbName = DB::connection()->getDatabaseName(); // aman saat config di-cache (env() bernilai null)
                $keyName = 'Tables_in_' . $dbName;
                
                $schemaInfo = [];
                
                foreach ($tables as $table) {
                    $tableName = $table->{$keyName} ?? current((array)$table);
                    
                    // Skip migrations, sessions, and AI system tables
                    if (in_array($tableName, ['migrations', 'sessions', 'tb_ai_chat_sessions', 'tb_ai_chat_messages', 'tb_rag_documents', 'tb_rag_document_chunks'])) {
                        continue;
                    }
                    
                    $columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
                    $colsList = [];
                    foreach ($columns as $col) {
                        $colsList[] = "{$col->Field} ({$col->Type})";
                    }
                    
                    $schemaInfo[] = [
                        'table' => $tableName,
                        'columns' => implode(', ', $colsList)
                    ];
                }
                
                return [
                    'status' => 'success',
                    'database' => $dbName,
                    'tables' => $schemaInfo
                ];
            }
            
            if ($name === 'run_read_query') {
                $query = $args['query'] ?? '';
                if (empty($query)) {
                    return ['status' => 'error', 'message' => 'Query is empty'];
                }

                // SECURITY CHECK: Read-only check
                $trimmed = trim(strtolower($query));
                if (strpos($trimmed, 'select') !== 0) {
                    return [
                        'status' => 'error', 
                        'message' => 'Hanya query SELECT yang diperbolehkan untuk keamanan database.'
                    ];
                }

                // Block dangerous SQL keywords
                $dangerousKeywords = ['insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'replace', 'rename', 'create', 'grant'];
                foreach ($dangerousKeywords as $kw) {
                    if (preg_match('/\b' . $kw . '\b/i', $trimmed)) {
                        return [
                            'status' => 'error', 
                            'message' => "Query mengandung perintah berbahaya: {$kw}. Hanya diperbolehkan membaca data."
                        ];
                    }
                }

                // Execute query with automatic row limit if not present
                if (strpos(strtolower($trimmed), 'limit') === false) {
                    $query = rtrim($query, ';') . ' LIMIT 20';
                }

                $results = DB::select($query);
                
                return [
                    'status' => 'success',
                    'query_executed' => $query,
                    'row_count' => count($results),
                    'rows' => $results
                ];
            }
            
            return ['status' => 'error', 'message' => "Unknown tool: {$name}"];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    private function callGeminiApiRaw($geminiMessages)
    {
        $apiKey = config('ai.ai_api_key') ?: env('AI_API_KEY', '');
        $model = (config('ai.ai_model') ?: env('AI_MODEL', '')) ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        // Define tools schema
        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'get_database_schema',
                        'description' => 'Mendapatkan daftar semua nama tabel yang ada di dalam database beserta nama-nama kolom dan tipenya.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => (object)[],
                            'required' => []
                        ]
                    ],
                    [
                        'name' => 'run_read_query',
                        'description' => 'Menjalankan query SQL SELECT yang aman untuk membaca data dari database.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type' => 'STRING',
                                    'description' => 'Query SQL SELECT lengkap. Contoh: SELECT id, username, email FROM tb_users LIMIT 5'
                                ]
                            ],
                            'required' => ['query']
                        ]
                    ]
                ]
            ]
        ];

        $payload = [
            'contents' => $geminiMessages,
            'tools' => $tools
        ];

        if ($sysInstruction = (config('ai.ai_system_instruction') ?? env('AI_SYSTEM_INSTRUCTION'))) {
            $chartInstruction = "\n\n=== CHART GENERATION GUIDELINE ===\n" .
                "Jika pengguna meminta grafik, chart, diagram, atau visualisasi data (seperti perbandingan, tren, atau proporsi), Anda HARUS menyajikan data tersebut dalam bentuk grafik interaktif dengan menghasilkan sebuah blok kode bertipe 'chart' berisi konfigurasi JSON untuk Chart.js (versi 3/4). Format blok kode harus tepat seperti ini:\n" .
                "```chart\n" .
                "{\n" .
                "  \"type\": \"bar\",\n" .
                "  \"data\": {\n" .
                "    \"labels\": [\"Label A\", \"Label B\", \"Label C\"],\n" .
                "    \"datasets\": [{\n" .
                "      \"label\": \"Judul Dataset\",\n" .
                "      \"data\": [10, 20, 15]\n" .
                "    }]\n" .
                "  },\n" .
                "  \"options\": {\n" .
                "    \"plugins\": {\n" .
                "      \"title\": {\n" .
                "        \"display\": true,\n" .
                "        \"text\": \"Judul Grafik\"\n" .
                "      }\n" .
                "    }\n" .
                "  }\n" .
                "}\n" .
                "```\n" .
                "Catatan penting:\n" .
                "1. Jangan menyisipkan elemen HTML atau CSS kustom untuk merender chart secara langsung. Cukup gunakan format markdown ```chart untuk membungkus objek JSON konfigurasi Chart.js tersebut.\n" .
                "2. JSON harus benar-benar valid. JANGAN gunakan komentar (seperti // atau /* */) di dalam objek JSON.\n" .
                "3. Pilihan jenis chart ('type') yang valid adalah: \"bar\", \"line\", \"pie\", \"doughnut\", \"polarArea\", \"radar\".\n" .
                "4. Anda juga dapat menyajikan penjelasan teks atau tabel markdown biasa sebelum atau sesudah grafik tersebut.";

            $payload['systemInstruction'] = [
                'parts' => [['text' => $sysInstruction . $chartInstruction]]
            ];
        }

        return $this->curlPost($url, $payload);
    }

    private function curlPost($url, $payload, $headers = ["Content-Type: application/json"])
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
        
        return json_decode($response, true);
    }
}
