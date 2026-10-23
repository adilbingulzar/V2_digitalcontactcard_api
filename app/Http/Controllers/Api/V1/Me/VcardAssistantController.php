<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\AskCardAssistantRequest;
use App\Http\Requests\Me\ImportOwnCardRequest;
use App\Models\AppSetting;
use App\Models\User;
use App\Models\VcardAssistantMessage;
use App\Services\Ai\AiException;
use App\Services\Ai\Attachment;
use App\Services\Ai\CardAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The chat with the card builder's AI assistant. The chat is kept, so users can carry on later.
 *
 * The last file a user attached is kept on the private disk and shown to the
 * model again with the next few messages, so "that is not the same yet" works
 * without attaching it again.
 */
class VcardAssistantController extends Controller
{
    private const int SHOWN_MESSAGES = 50;

    /**
     * How many messages back an attachment is still shown again.
     */
    private const int REMEMBER_ATTACHMENT_FOR = 6;

    public function index(Request $request): JsonResponse
    {
        $messages = $request->user()->vcardAssistantMessages()->latest('id')->limit(self::SHOWN_MESSAGES)->get()->reverse()->values();

        return response()->json([
            'data' => [
                'available' => AppSetting::current()->isAiReady(),
                'messages' => $messages->map(self::message(...))->all(),
            ],
        ]);
    }

    public function store(AskCardAssistantRequest $request, CardAssistant $assistant): JsonResponse
    {
        $settings = AppSetting::current();
        abort_unless($settings->isAiReady(), 503, 'The assistant is not available right now.');

        $user = $request->user();
        $text = trim($request->string('message')->toString());

        $upload = $request->file('attachment');
        $attachment = $upload !== null ? Attachment::fromUpload($upload) : self::rememberedAttachment($user);

        try {
            $answer = $assistant->answer($user, $text, $request->card(), $attachment);
        } catch (AiException $exception) {
            Log::warning('Card assistant failed', ['user_id' => $user->id, 'provider' => $settings->ai_provider, 'error' => $exception->getMessage()]);
            abort(502, 'The assistant could not answer just now. Please try again in a moment.');
        }

        $stored = $upload === null ? null : self::keepAttachment($user, $upload);
        $reply = DB::transaction(function () use ($user, $text, $answer, $settings, $upload, $stored): VcardAssistantMessage {
            $user->vcardAssistantMessages()->create([
                'role' => VcardAssistantMessage::ROLE_USER,
                'content' => $text,
                'attachment' => $upload === null ? null : ['name' => mb_substr($upload->getClientOriginalName(), 0, 120), 'type' => $upload->getMimeType(), 'path' => $stored],
            ]);

            return $user->vcardAssistantMessages()->create([
                'role' => VcardAssistantMessage::ROLE_ASSISTANT,
                'content' => $answer['reply'],
                'changes' => $answer['changes'] === [] ? null : $answer['changes'],
                'provider' => $settings->ai_provider,
                'model' => $answer['completion']->model,
                'input_tokens' => $answer['completion']->inputTokens,
                'output_tokens' => $answer['completion']->outputTokens,
            ]);
        });

        return response()->json(['data' => self::message($reply)], 201);
    }

    /**
     * Fills in the card builder from pictures or a PDF of the user's own card. The
     * answer is added to the chat, so the user can carry on from there; the front
     * of the card is remembered like any attachment.
     */
    public function import(ImportOwnCardRequest $request, CardAssistant $assistant): JsonResponse
    {
        $settings = AppSetting::current();
        abort_unless($settings->isAiReady(), 503, 'The assistant is not available right now.');

        $user = $request->user();
        /** @var list<UploadedFile> $files */
        $files = array_values($request->file('files'));

        try {
            $answer = $assistant->fromOwnCard($user, array_map(Attachment::fromUpload(...), $files), $request->card());
        } catch (AiException $exception) {
            Log::warning('Card import failed', ['user_id' => $user->id, 'provider' => $settings->ai_provider, 'error' => $exception->getMessage()]);
            abort(502, 'Your card could not be read just now. Please try again in a moment.');
        }

        $front = $files[0];
        $stored = self::keepAttachment($user, $front);
        $reply = DB::transaction(function () use ($user, $answer, $settings, $front, $stored, $files): VcardAssistantMessage {
            $user->vcardAssistantMessages()->create([
                'role' => VcardAssistantMessage::ROLE_USER,
                'content' => count($files) === 2 ? 'Fill in my card from my own card (front and back).' : 'Fill in my card from my own card.',
                'attachment' => ['name' => mb_substr($front->getClientOriginalName(), 0, 120), 'type' => $front->getMimeType(), 'path' => $stored],
            ]);

            return $user->vcardAssistantMessages()->create([
                'role' => VcardAssistantMessage::ROLE_ASSISTANT,
                'content' => $answer['reply'],
                'changes' => $answer['changes'] === [] ? null : $answer['changes'],
                'provider' => $settings->ai_provider,
                'model' => $answer['completion']->model,
                'input_tokens' => $answer['completion']->inputTokens,
                'output_tokens' => $answer['completion']->outputTokens,
            ]);
        });

        return response()->json(['data' => self::message($reply)], 201);
    }

    /**
     * Starts the chat over. The daily message limit is not reset by this.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->vcardAssistantMessages()->delete();
        Storage::disk('local')->deleteDirectory(self::attachmentFolder($request->user()));

        return response()->json(null, 204);
    }

    /**
     * @return array{id: int, role: string, content: string, attachment: ?array<string, string>, changes: array<string, mixed>, created_at: ?string}
     */
    private static function message(VcardAssistantMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'attachment' => $message->attachment === null ? null : ['name' => $message->attachment['name'] ?? '', 'type' => $message->attachment['type'] ?? ''],
            'changes' => $message->changes ?? [],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private static function attachmentFolder(User $user): string
    {
        return 'assistant-attachments/'.$user->id;
    }

    /**
     * Keeps only the newest attachment of a user on the private disk.
     */
    private static function keepAttachment(User $user, UploadedFile $file): string
    {
        $disk = Storage::disk('local');
        $folder = self::attachmentFolder($user);
        $disk->delete($disk->files($folder));

        return $disk->putFileAs($folder, $file, Str::uuid().'.'.($file->guessExtension() ?? 'bin'));
    }

    /**
     * The file attached in one of the last few messages, if it is still kept.
     */
    private static function rememberedAttachment(User $user): ?Attachment
    {
        $message = $user->vcardAssistantMessages()
            ->latest('id')
            ->limit(self::REMEMBER_ATTACHMENT_FOR)
            ->get()
            ->first(fn (VcardAssistantMessage $message): bool => is_string($message->attachment['path'] ?? null));

        return $message === null ? null : Attachment::fromStorage($message->attachment['path'], (string) $message->attachment['name'], (string) $message->attachment['type']);
    }
}
