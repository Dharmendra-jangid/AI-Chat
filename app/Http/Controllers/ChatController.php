<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = Conversation::query()
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $selectedId = $request->integer('conversation');
        $activeConversation = $selectedId
            ? $conversations->firstWhere('id', $selectedId)
            : $conversations->first();

        $messages = $activeConversation
            ? $activeConversation->messages()->get()
            : collect();

        return view('chat', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
        ]);
    }

    public function conversations(): JsonResponse
    {
        $items = Conversation::query()
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title ?? 'Chat #'.$c->id,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return response()->json(['conversations' => $items]);
    }

    public function storeConversation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation = Conversation::create([
            'user_id' => $request->user()?->id,
            'title' => $data['title'] ?? null,
        ]);

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function messages(Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()->get()->map(fn ($m) => [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'created_at' => $m->created_at?->toIso8601String(),
        ]);

        return response()->json(['messages' => $messages]);
    }

    public function storeMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:100000'],
        ]);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['content'],
        ]);

        if (blank($conversation->title)) {
            $conversation->title = Str::limit($data['content'], 48);
            $conversation->save();
        }

        try {
            $assistantBody = $this->generateGeminiReply($conversation);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('chat.index', ['conversation' => $conversation->id])
                ->with('error', 'Could not get a response from Gemini. Please check API key and model settings.');
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $assistantBody,
            'model' => (string) config('services.gemini.model'),
        ]);

        $conversation->touch();

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    private function generateGeminiReply(Conversation $conversation): string
    {
        $apiKey = (string) config('services.gemini.api_key');
        $model = (string) config('services.gemini.model');

        if ($apiKey === '' || $model === '') {
            throw new \RuntimeException('Gemini credentials missing.');
        }

        $chatHistory = $conversation->messages()
            ->latest('id')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(function ($message) {
                $role = $message->role === 'assistant' ? 'model' : 'user';

                return [
                    'role' => $role,
                    'parts' => [
                        ['text' => $message->content],
                    ],
                ];
            })
            ->values()
            ->all();

        $response = Http::timeout(60)
            ->acceptJson()
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => $chatHistory,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1024,
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini HTTP error: '.$response->status());
        }

        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        $text = collect($parts)
            ->pluck('text')
            ->filter()
            ->implode("\n");

        if ($text === '') {
            throw new \RuntimeException('Gemini empty response.');
        }

        return $text;
    }
}
