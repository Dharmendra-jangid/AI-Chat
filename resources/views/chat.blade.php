<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat (Laravel + Gemini)</title>
    <style>
        :root {
            --bg: #070d1b;
            --panel: #0f172a;
            --panel-soft: #182338;
            --surface: #0b1325;
            --text: #e6edf7;
            --text-muted: #93a4bf;
            --primary: #22c55e;
            --primary-soft: #16a34a;
            --border: #243148;
            --danger: #f87171;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: radial-gradient(circle at top, #112041 0%, var(--bg) 45%);
            color: var(--text);
            min-height: 100vh;
        }

        .app {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 100vh;
            gap: 0;
        }

        .sidebar {
            border-right: 1px solid var(--border);
            background: linear-gradient(180deg, #101a32 0%, #0a1326 100%);
            padding: 16px;
        }

        .new-chat-form { margin: 0 0 12px 0; }

        .new-chat-btn {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 11px 12px;
            background: var(--primary);
            color: #052e16;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 18px rgba(34, 197, 94, 0.2);
        }

        .new-chat-btn:hover { background: var(--primary-soft); }

        .conversation-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .conversation-link {
            display: block;
            margin-bottom: 10px;
            padding: 11px;
            border-radius: 10px;
            border: 1px solid transparent;
            color: var(--text);
            text-decoration: none;
            background: rgba(8, 14, 30, 0.45);
        }

        .conversation-link:hover {
            background: #101c34;
            border-color: #2a3c5b;
        }

        .conversation-link.active {
            border-color: var(--primary);
            background: rgba(20, 83, 45, 0.18);
        }

        .conversation-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .conversation-time {
            font-size: 12px;
            color: var(--text-muted);
        }

        .main {
            display: grid;
            grid-template-rows: auto 1fr auto;
            min-height: 100vh;
            background: linear-gradient(180deg, #060c1a 0%, #050914 100%);
        }

        .main-header {
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
            font-size: 14px;
            color: var(--text-muted);
            background: rgba(10, 18, 35, 0.8);
            backdrop-filter: blur(5px);
        }

        .alert {
            margin: 10px 18px 0;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13px;
        }

        .alert-error {
            background: #3b0d0d;
            color: #fecaca;
            border: 1px solid #7f1d1d;
        }

        .messages {
            padding: 26px 24px;
            overflow-y: auto;
        }

        .empty {
            color: var(--text-muted);
            text-align: center;
            margin-top: 48px;
            font-size: 15px;
        }

        .bubble {
            max-width: min(760px, 95%);
            width: fit-content;
            border-radius: 14px;
            padding: 12px 15px;
            margin-bottom: 14px;
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.45;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
        }

        .bubble.user {
            margin-left: auto;
            background: #14532d;
            color: #dcfce7;
            border-bottom-right-radius: 6px;
        }

        .bubble.assistant {
            margin-right: auto;
            background: var(--panel-soft);
            border: 1px solid #2b3647;
            border-bottom-left-radius: 6px;
        }

        .composer {
            border-top: 1px solid var(--border);
            background: rgba(10, 17, 32, 0.96);
            padding: 14px 16px;
        }

        .composer-form {
            display: flex;
            gap: 8px;
        }

        .composer-input {
            flex: 1;
            min-height: 52px;
            resize: vertical;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            background: var(--surface);
            color: var(--text);
            font-size: 14px;
            font-family: Arial, sans-serif;
        }

        .composer-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .send-btn {
            min-width: 108px;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: #052e16;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .send-btn:hover {
            transform: translateY(-1px);
        }

        .send-btn[disabled] {
            opacity: 0.65;
            cursor: not-allowed;
        }

        @media (max-width: 900px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { border-right: 0; border-bottom: 1px solid var(--border); }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <form class="new-chat-form" method="POST" action="{{ route('conversations.store') }}">
                @csrf
                <button class="new-chat-btn" type="submit">+ New Chat</button>
            </form>

            <ul class="conversation-list">
                @forelse($conversations as $conversation)
                    <li>
                        <a class="conversation-link {{ $activeConversation && $activeConversation->id === $conversation->id ? 'active' : '' }}"
                           href="{{ route('chat.index', ['conversation' => $conversation->id]) }}">
                            <div class="conversation-title">{{ $conversation->title ?: ('Chat #'.$conversation->id) }}</div>
                            <div class="conversation-time">{{ optional($conversation->updated_at)->format('d M Y, h:i A') }}</div>
                        </a>
                    </li>
                @empty
                    <li class="conversation-time">No chats yet.</li>
                @endforelse
            </ul>
        </aside>

        <main class="main">
            <div class="main-header">
                {{ $activeConversation ? ($activeConversation->title ?: ('Chat #'.$activeConversation->id)) : 'Select or create a chat' }}
            </div>

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <section class="messages">
                @if($messages->isEmpty())
                    <div class="empty">No messages yet. Send a message to begin.</div>
                @else
                    @foreach($messages as $message)
                        <div class="bubble {{ $message->role === 'user' ? 'user' : 'assistant' }}">{{ $message->content }}</div>
                    @endforeach
                @endif
            </section>

            <div class="composer">
                @if($activeConversation)
                    <form class="composer-form" method="POST" action="{{ route('conversations.messages.store', $activeConversation) }}">
                        @csrf
                        <textarea class="composer-input" name="content" placeholder="Type your message..." required>{{ old('content') }}</textarea>
                        <button class="send-btn" type="submit">Send</button>
                    </form>
                @else
                    <form class="composer-form" method="POST" action="#">
                        <textarea class="composer-input" placeholder="Create a new chat to start messaging..." disabled></textarea>
                        <button class="send-btn" type="button" disabled>Send</button>
                    </form>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
