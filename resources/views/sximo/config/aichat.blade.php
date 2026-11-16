@extends('layouts.app')

@section('content')
<div class="page-header">
    <h2> {{ $pageTitle }} <small> {{ $pageNote }} </small> </h2>
</div>

<div class="row">
    <!-- Left Sidebar: Chat Sessions -->
    <div class="col-md-3">
        <div class="card mb-4" style="border-radius: 8px;">
            <div class="card-body p-3">
                <form action="{{ url('sximo/aichat/newsession') }}" method="POST" class="mb-3">
                    @csrf
                    <button type="submit" class="btn btn-primary d-block w-100 text-start" style="display: flex; align-items: center; justify-content: space-between; font-weight: 500;">
                        <span><i class="fas fa-plus-circle me-2"></i> New Chat</span>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </form>

                <h5 class="text-muted mb-3" style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Chat History</h5>
                <div class="chat-sessions-list" style="max-height: 450px; overflow-y: auto;">
                    @if(count($sessions) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($sessions as $session)
                                <div class="list-group-item list-group-item-action p-2 d-flex justify-content-between align-items-center @if($session->id == $activeSessionId) active-session @endif" style="border-radius: 6px; margin-bottom: 4px; border: none; transition: background 0.2s;">
                                    <a href="{{ url('sximo/aichat/' . $session->id) }}" class="text-truncate flex-grow-1" style="font-weight: 500; font-size: 14px; text-decoration: none; color: inherit;">
                                        <i class="far fa-comments me-2"></i> {{ $session->title ?: 'Empty Chat Session' }}
                                    </a>
                                    <a href="{{ url('sximo/aichat/deletesession/' . $session->id) }}" class="btn-delete-session ms-2 text-danger" onclick="return confirm('Are you sure you want to delete this session?');" title="Delete Session" style="padding: 2px 6px;">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-muted py-4" style="font-size: 14px;">
                            <i class="fas fa-comments mb-2" style="font-size: 24px; opacity: 0.5;"></i>
                            <p>No chat history yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Right Pane: Active Chat Window -->
    <div class="col-md-9">
        <div class="card mb-4" style="border-radius: 8px; display: flex; flex-direction: column; min-height: 550px;">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center" style="border-bottom: 1px solid rgba(0,0,0,0.08); padding: 15px 20px;">
                <h4 class="mb-0" style="font-size: 16px; font-weight: 600; display: flex; align-items: center;">
                    @php
                        $aiAvatar = config('ai.ai_avatar') ?? env('AI_AVATAR');
                        $aiName = config('ai.ai_name') ?? env('AI_NAME', 'AI Assistant');
                    @endphp
                    @if($aiAvatar && file_exists(public_path('uploads/ai/' . $aiAvatar)))
                        <img src="{{ asset('uploads/ai/' . $aiAvatar) }}" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover; border: 1px solid rgba(0,0,0,0.08);" />
                    @else
                        <i class="fab fa-android me-2 text-primary"></i> 
                    @endif
                    <span>
                        @if($activeSessionId)
                            {{ $sessions->where('id', $activeSessionId)->first()->title ?? $aiName }}
                        @else
                            New Chat Session
                        @endif
                    </span>
                </h4>
            </div>

            <!-- Chat Messages Log -->
            <div class="card-body chat-messages-log p-4" id="chat-messages-container" style="flex-grow: 1; height: 380px; overflow-y: auto; display: flex; flex-direction: column;">
                @if($activeSessionId && count($messages) > 0)
                    @foreach($messages as $msg)
                        <div class="message-bubble-wrapper d-flex mb-3 @if($msg->role == 'user') justify-content-end @else justify-content-start @endif">
                            <div class="message-bubble p-3 @if($msg->role == 'user') bubble-user @else bubble-assistant @endif" style="max-width: 75%; border-radius: 12px; line-height: 1.5; font-size: 14px; position: relative;">
                                @if($msg->role == 'assistant')
                                    <div class="mb-1 text-primary" style="font-size: 11px; font-weight: bold; text-transform: uppercase;">{{ config('ai.ai_name') ?? env('AI_NAME', 'AI Assistant') }}</div>
                                @endif
                                <div class="message-text" data-role="{{ $msg->role }}" data-raw="{{ $msg->message }}">
                                    @if($msg->role == 'user')
                                        {{ $msg->message }}
                                    @endif
                                </div>
                                <div class="text-end mt-1 text-muted" style="font-size: 10px;">{{ date('H:i', strtotime($msg->created_at)) }}</div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center text-muted m-auto py-5" id="chat-welcome-placeholder">
                        @if($aiAvatar && file_exists(public_path('uploads/ai/' . $aiAvatar)))
                            <img src="{{ asset('uploads/ai/' . $aiAvatar) }}" class="rounded-circle mb-3 img-thumbnail" style="width: 70px; height: 70px; object-fit: cover; border: 1px solid rgba(0,0,0,0.08);" />
                        @else
                            <i class="fab fa-android mb-3 text-primary" style="font-size: 48px; opacity: 0.8;"></i>
                        @endif
                        <h4 style="font-weight: 600; margin-bottom: 10px;">Start a Conversation with {{ $aiName }}!</h4>
                        <p style="max-width: 400px; margin: 0 auto; font-size: 14px;">Ask me anything about project tasks, system records, translations, or design ideas. I am here to help.</p>
                    </div>
                @endif
            </div>

            <!-- Chat Input Footer -->
            <div class="card-footer bg-transparent" style="border-top: 1px solid rgba(0,0,0,0.08); padding: 15px 20px;">
                <form id="chat-input-form" onsubmit="submitChatMessage(event);">
                    <div class="input-group">
                        <input type="text" id="chat-message-input" autocomplete="off" class="form-control" placeholder="Type your message here..." style="border-radius: 20px 0 0 20px; padding: 10px 20px; font-size: 14px; border-right: none;" required />
                        <button type="submit" class="btn btn-primary" style="border-radius: 0 20px 20px 0; padding: 0 25px; display: flex; align-items: center;">
                            <span>Send</span> <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* Light Theme Bubble Styles */
    .bubble-user {
        background-color: #3b82f6 !important;
        color: #ffffff !important;
        border-bottom-right-radius: 2px !important;
    }
    .bubble-user .text-muted {
        color: rgba(255, 255, 255, 0.7) !important;
    }
    .bubble-assistant {
        background-color: #f3f4f6 !important;
        color: #1f2937 !important;
        border-bottom-left-radius: 2px !important;
        border: 1px solid #e5e7eb;
    }

    /* List Group Custom Styles */
    .active-session {
        background-color: rgba(59, 130, 246, 0.1) !important;
        color: #2563eb !important;
        border-left: 3px solid #2563eb !important;
        border-radius: 0 6px 6px 0 !important;
    }
    .list-group-item-action:hover {
        background-color: rgba(0, 0, 0, 0.03);
    }
    .btn-delete-session:hover {
        opacity: 0.8;
    }

    /* Premium Dark Theme Overrides */
    body.dark-theme .bubble-user {
        background-color: #1e3a8a !important;
        color: #ffffff !important;
        border-color: #1e40af !important;
    }
    body.dark-theme .bubble-assistant {
        background-color: #161b22 !important;
        color: #c9d1d9 !important;
        border-color: #30363d !important;
    }
    body.dark-theme .bubble-assistant .text-primary {
        color: #58a6ff !important;
    }
    body.dark-theme .active-session {
        background-color: rgba(88, 166, 255, 0.15) !important;
        color: #58a6ff !important;
        border-left-color: #58a6ff !important;
    }
    body.dark-theme .list-group-item-action:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    /* Markdown & Table styling inside message bubbles */
    .message-text table {
        width: 100%;
        margin-top: 10px;
        margin-bottom: 15px;
        border-collapse: collapse;
        font-size: 13px;
    }
    .message-text table th, .message-text table td {
        padding: 8px 12px;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    .message-text table th {
        background-color: rgba(0, 0, 0, 0.03);
        font-weight: 600;
    }
    .message-text table tr:nth-child(even) {
        background-color: rgba(0, 0, 0, 0.01);
    }

    /* Dark Mode table styles */
    body.dark-theme .message-text table th, body.dark-theme .message-text table td {
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    body.dark-theme .message-text table th {
        background-color: rgba(255, 255, 255, 0.05);
        color: #ffffff;
    }
    body.dark-theme .message-text table tr:nth-child(even) {
        background-color: rgba(255, 255, 255, 0.02);
    }

    /* Markdown Code Blocks */
    .message-text pre {
        background-color: rgba(0, 0, 0, 0.04);
        border-radius: 6px;
        padding: 10px;
        margin: 10px 0;
        overflow-x: auto;
    }
    .message-text code {
        font-family: SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
        font-size: 13px;
        color: #d63384;
        background-color: rgba(0, 0, 0, 0.04);
        padding: 2px 4px;
        border-radius: 4px;
    }
    .message-text pre code {
        color: inherit;
        background-color: transparent;
        padding: 0;
        border-radius: 0;
    }

    body.dark-theme .message-text pre {
        background-color: rgba(255, 255, 255, 0.05);
    }
    body.dark-theme .message-text code {
        color: #ff79c6;
        background-color: rgba(255, 255, 255, 0.05);
    }
    body.dark-theme .message-text pre code {
        color: #c9d1d9;
    }

    /* Markdown Lists & Paragraphs */
    .message-text p {
        margin-bottom: 8px;
    }
    .message-text p:last-child {
        margin-bottom: 0;
    }
    .message-text ul, .message-text ol {
        margin-top: 5px;
        margin-bottom: 10px;
        padding-left: 20px;
    }
</style>

<!-- Load Markdown parser & Charting Libraries -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    var activeSessionId = "{{ $activeSessionId }}";

    // Premium Modern Colors for Chart.js
    var chartColors = [
        { fill: 'rgba(59, 130, 246, 0.15)', border: '#3b82f6' }, // Blue
        { fill: 'rgba(16, 185, 129, 0.15)', border: '#10b981' }, // Emerald
        { fill: 'rgba(99, 102, 241, 0.15)', border: '#6366f1' }, // Indigo
        { fill: 'rgba(245, 158, 11, 0.15)',  border: '#f59e0b' }, // Amber
        { fill: 'rgba(239, 68, 68, 0.15)',   border: '#ef4444' }, // Rose
        { fill: 'rgba(6, 182, 212, 0.15)',   border: '#06b6d4' }, // Cyan
        { fill: 'rgba(139, 92, 246, 0.15)',  border: '#8b5cf6' }  // Purple
    ];

    function enhanceChartOptions(chartData) {
        if (!chartData.options) chartData.options = {};
        
        chartData.options.responsive = true;
        chartData.options.maintainAspectRatio = false;
        
        var isDark = $('body').hasClass('dark-theme');
        var textColor = isDark ? '#c9d1d9' : '#4b5563';
        var gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)';
        
        // Configure plugins/legend
        if (!chartData.options.plugins) chartData.options.plugins = {};
        if (!chartData.options.plugins.legend) chartData.options.plugins.legend = {};
        
        chartData.options.plugins.legend.labels = chartData.options.plugins.legend.labels || {};
        chartData.options.plugins.legend.labels.color = textColor;
        chartData.options.plugins.legend.labels.font = {
            family: 'Poppins, Inter, sans-serif',
            size: 11
        };

        // Title
        if (chartData.options.plugins.title) {
            chartData.options.plugins.title.color = isDark ? '#ffffff' : '#1f2937';
            chartData.options.plugins.title.font = {
                family: 'Poppins, Inter, sans-serif',
                size: 13,
                weight: '600'
            };
        }

        // Grid / Axis configuration for Cartesian charts
        var type = chartData.type ? chartData.type.toLowerCase() : 'bar';
        if (type !== 'pie' && type !== 'doughnut' && type !== 'polararea' && type !== 'radar') {
            if (!chartData.options.scales) chartData.options.scales = {};
            
            // X Axis
            if (!chartData.options.scales.x) chartData.options.scales.x = {};
            chartData.options.scales.x.grid = chartData.options.scales.x.grid || {};
            chartData.options.scales.x.grid.color = gridColor;
            chartData.options.scales.x.grid.drawBorder = false;
            chartData.options.scales.x.ticks = chartData.options.scales.x.ticks || {};
            chartData.options.scales.x.ticks.color = textColor;
            chartData.options.scales.x.ticks.font = { family: 'Poppins, Inter, sans-serif', size: 10 };
            
            // Y Axis
            if (!chartData.options.scales.y) chartData.options.scales.y = {};
            chartData.options.scales.y.grid = chartData.options.scales.y.grid || {};
            chartData.options.scales.y.grid.color = gridColor;
            chartData.options.scales.y.grid.drawBorder = false;
            chartData.options.scales.y.ticks = chartData.options.scales.y.ticks || {};
            chartData.options.scales.y.ticks.color = textColor;
            chartData.options.scales.y.ticks.font = { family: 'Poppins, Inter, sans-serif', size: 10 };
        }

        // Apply nice color palettes and options if not provided
        if (chartData.data && chartData.data.datasets) {
            chartData.data.datasets.forEach(function(dataset, dsIndex) {
                if (type === 'pie' || type === 'doughnut') {
                    if (!dataset.backgroundColor) {
                        dataset.backgroundColor = chartColors.map(function(c) { return c.border; });
                        dataset.borderColor = isDark ? '#0b0e11' : '#ffffff';
                        dataset.borderWidth = 2;
                    }
                } else {
                    var colorIndex = dsIndex % chartColors.length;
                    if (!dataset.backgroundColor) {
                        dataset.backgroundColor = chartColors[colorIndex].fill;
                    }
                    if (!dataset.borderColor) {
                        dataset.borderColor = chartColors[colorIndex].border;
                    }
                    if (dataset.borderWidth === undefined) {
                        dataset.borderWidth = type === 'line' ? 2 : 1;
                    }
                    if (type === 'line') {
                        if (dataset.tension === undefined) dataset.tension = 0.35; // smooth bezier curve
                        if (dataset.fill === undefined) dataset.fill = true; // Area under curve filled
                    }
                }
            });
        }
    }

    function renderMessageContent(element) {
        var rawText = $(element).attr('data-raw');
        if (rawText === undefined) return;

        var role = $(element).attr('data-role');
        if (role === 'user') {
            $(element).text(rawText);
            return;
        }

        // Process assistant markdown
        try {
            var renderedHtml = marked.parse(rawText);
            $(element).html(renderedHtml);
        } catch (e) {
            console.error('Failed to parse Markdown:', e);
            $(element).text(rawText); // fallback
            return;
        }

        // Locate and instantiate chart configurations
        $(element).find('pre code.language-chart').each(function() {
            var codeTag = $(this);
            var preTag = codeTag.parent();
            var jsonText = codeTag.text().trim();

            try {
                var chartData;
                var cleanJson = "";
                
                // Try lenient JavaScript evaluation first (handles single quotes, comments, unquoted keys, trailing commas)
                try {
                    chartData = (new Function("return (" + jsonText + ")"))();
                } catch(evalErr) {
                    // Fallback to strict JSON parsing after sanitization
                    cleanJson = jsonText.replace(/("([^"\\]|\\.)*")|(\/\/.*|\/\*[\s\S]*?\*\/)/g, function(m, g1) {
                        return g1 ? g1 : "";
                    });
                    cleanJson = cleanJson.replace(/,\s*([\]}])/g, '$1');
                    chartData = JSON.parse(cleanJson);
                }
                
                // Verify we got a valid object
                if (!chartData || typeof chartData !== 'object') {
                    throw new Error("Parsed data is not an object");
                }
                
                // Create canvas container
                var canvasId = 'chart-' + Math.random().toString(36).substr(2, 9);
                var containerHtml = `
                    <div class="chart-wrapper-box" style="position: relative; height: 260px; width: 100%; margin: 15px 0; padding: 10px; background: rgba(0,0,0,0.02); border-radius: 8px; border: 1px dashed rgba(0,0,0,0.1);">
                        <canvas id="${canvasId}"></canvas>
                    </div>
                `;
                
                var $container = $(containerHtml);
                if ($('body').hasClass('dark-theme')) {
                    $container.css({
                        'background': 'rgba(255,255,255,0.02)',
                        'border-color': 'rgba(255,255,255,0.1)'
                    });
                }
                
                preTag.replaceWith($container);
                
                // Initialize Chart.js
                enhanceChartOptions(chartData);
                var ctx = document.getElementById(canvasId).getContext('2d');
                new Chart(ctx, chartData);
                
            } catch(jsonErr) {
                console.error('Failed to parse chart configuration:', jsonErr);
                console.error('Raw JSON/JS text was:', jsonText);
                if (cleanJson) {
                    console.error('Cleaned JSON text was:', cleanJson);
                }
                preTag.before(`<div class="alert alert-xs alert-warning p-1 mb-1" style="font-size: 11px; display: inline-block;"><i class="fa fa-exclamation-triangle"></i> Failed to parse chart configuration</div>`);
            }
        });
    }

    function scrollToBottom() {
        var container = document.getElementById('chat-messages-container');
        container.scrollTop = container.scrollHeight;
    }

    // Run on load
    $(document).ready(function() {
        if (activeSessionId) {
            scrollToBottom();
        }

        // Render all existing messages with markdown/charts
        $('.message-text').each(function() {
            renderMessageContent(this);
        });
    });

    async function submitChatMessage(event) {
        event.preventDefault();
        
        var messageInput = document.getElementById('chat-message-input');
        var message = messageInput.value.trim();
        if (!message) return;

        // Clear input
        messageInput.value = '';

        // Remove welcome placeholder if present
        var placeholder = document.getElementById('chat-welcome-placeholder');
        if (placeholder) {
            placeholder.remove();
        }

        // Generate clean user message HTML bubble
        var container = document.getElementById('chat-messages-container');
        var now = new Date();
        var timeStr = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
        
        var $userBubbleObj = $(`
            <div class="message-bubble-wrapper d-flex mb-3 justify-content-end">
                <div class="message-bubble p-3 bubble-user" style="max-width: 75%; border-radius: 12px; line-height: 1.5; font-size: 14px; position: relative;">
                    <div class="message-text" data-role="user"></div>
                    <div class="text-end mt-1 text-muted" style="font-size: 10px; color: rgba(255,255,255,0.7) !important;">${timeStr}</div>
                </div>
            </div>
        `);
        $userBubbleObj.find('.message-text').attr('data-raw', message);
        $(container).append($userBubbleObj);
        renderMessageContent($userBubbleObj.find('.message-text')[0]);
        scrollToBottom();

        // Create Assistant Streaming Bubble
        var bubbleId = 'stream-ai-' + Date.now();
        var $aiBubbleObj = $(`
            <div class="message-bubble-wrapper d-flex mb-3 justify-content-start" id="${bubbleId}">
                <div class="message-bubble p-3 bubble-assistant" style="max-width: 75%; border-radius: 12px; line-height: 1.5; font-size: 14px; position: relative;">
                    <div class="mb-1 text-primary" style="font-size: 11px; font-weight: bold; text-transform: uppercase;">{{ config('ai.ai_name') ?? env('AI_NAME', 'AI Assistant') }}</div>
                    <div class="message-text" data-role="assistant"><i class="fas fa-spinner fa-spin me-1"></i> Sedang mengetik...</div>
                    <div class="text-end mt-1 text-muted ai-timestamp" style="font-size: 10px;">${timeStr}</div>
                </div>
            </div>
        `);
        $(container).append($aiBubbleObj);
        scrollToBottom();

        var messageElement = $aiBubbleObj.find('.message-text')[0];
        var accumulatedText = '';
        var isFirstToken = true;
        var isNewSession = !activeSessionId;

        try {
            const response = await fetch("{{ url('sximo/aichat/stream') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "text/event-stream"
                },
                body: JSON.stringify({
                    message: message,
                    session_id: activeSessionId
                })
            });

            if (!response.ok) {
                const errorJson = await response.json().catch(() => ({}));
                throw new Error(errorJson.message || 'HTTP error ' + response.status);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder("utf-8");
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split("\n");
                buffer = lines.pop(); // keep partial line in buffer

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed.startsWith('data: ')) {
                        const jsonStr = trimmed.slice(6).trim();
                        if (!jsonStr) continue;

                        try {
                            const data = JSON.parse(jsonStr);

                            if (data.event === 'start') {
                                if (!activeSessionId && data.sessionId) {
                                    activeSessionId = data.sessionId;
                                }
                            } else if (data.event === 'token') {
                                if (isFirstToken) {
                                    $(messageElement).empty();
                                    isFirstToken = false;
                                }
                                accumulatedText += data.text;
                                $(messageElement).attr('data-raw', accumulatedText);
                                try {
                                    $(messageElement).html(marked.parse(accumulatedText));
                                } catch(e) {
                                    $(messageElement).text(accumulatedText);
                                }
                                scrollToBottom();
                            } else if (data.event === 'done') {
                                if (data.fullMessage) {
                                    accumulatedText = data.fullMessage;
                                }
                                $(messageElement).attr('data-raw', accumulatedText);
                                renderMessageContent(messageElement);
                                scrollToBottom();

                                if (isNewSession && activeSessionId) {
                                    window.location.href = "{{ url('sximo/aichat') }}/" + activeSessionId;
                                }
                            }
                        } catch(err) {
                            console.error('SSE parse error:', err, jsonStr);
                        }
                    }
                }
            }

            // Final render pass in case done event was omitted
            if (accumulatedText) {
                $(messageElement).attr('data-raw', accumulatedText);
                renderMessageContent(messageElement);
            }

        } catch (err) {
            console.error('Stream request error:', err);
            $aiBubbleObj.remove();
            appendErrorBubble(container, err.message || 'Gagal terhubung ke AI Streaming API.');
        }
    }

    function appendErrorBubble(container, errorMsg) {
        var errorBubble = `
            <div class="message-bubble-wrapper d-flex mb-3 justify-content-start">
                <div class="message-bubble p-3 bubble-assistant" style="max-width: 75%; border-radius: 12px; line-height: 1.5; font-size: 14px; position: relative; border-color: #f87171 !important;">
                    <div class="mb-1 text-danger" style="font-size: 11px; font-weight: bold; text-transform: uppercase;">System Connection Error</div>
                    <div class="message-text text-danger"><i class="fas fa-exclamation-triangle me-1"></i> ${escapeHtml(errorMsg)}</div>
                </div>
            </div>
        `;
        $(container).append(errorBubble);
        scrollToBottom();
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
</script>
@stop
