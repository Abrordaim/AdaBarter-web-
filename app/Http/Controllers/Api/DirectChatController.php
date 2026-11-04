<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SendDirectMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\DirectMessageResource;
use App\Services\DirectChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DirectChatController extends BaseApiController
{
    public function __construct(
        protected DirectChatService $directChatService
    ) {}

    /**
     * Memulai atau membuka percakapan langsung untuk suatu barang.
     */
    public function start(Request $request, int $itemId): JsonResponse
    {
        try {
            $conversation = $this->directChatService->getOrCreateConversation($request->user(), $itemId);

            return $this->sendResponse(
                new ConversationResource($conversation),
                'Ruang obrolan berhasil dibuka.',
                201
            );
        } catch (ValidationException $e) {
            return $this->sendError($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Gagal membuka percakapan: ' . $e->getMessage(), [], 400);
        }
    }

    /**
     * Membuka atau mendapatkan ruang obrolan untuk penawaran barter tertentu.
     */
    public function forOffer(Request $request, int $offerId): JsonResponse
    {
        try {
            $conversation = $this->directChatService->getOrCreateForOffer($request->user(), $offerId);

            return $this->sendResponse(
                new ConversationResource($conversation),
                'Ruang obrolan barter berhasil dibuka.'
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal membuka percakapan: ' . $e->getMessage(), [], 400);
        }
    }

    /**
     * Mengambil seluruh daftar percakapan langsung milik user.
     */
    public function conversations(Request $request): JsonResponse
    {
        try {
            $conversations = $this->directChatService->getUserConversations($request->user());

            return $this->sendResponse(
                ConversationResource::collection($conversations),
                'Daftar obrolan berhasil dimuat.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Gagal memuat daftar obrolan: ' . $e->getMessage(), [], 400);
        }
    }

    /**
     * Mengambil detail percakapan dan seluruh pesan di dalamnya.
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        try {
            $result = $this->directChatService->getMessages($request->user(), $conversationId);

            return $this->sendResponse([
                'conversation' => new ConversationResource($result['conversation']),
                'messages'     => DirectMessageResource::collection($result['messages']),
            ], 'Pesan obrolan berhasil dimuat.');
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal memuat pesan obrolan: ' . $e->getMessage(), [], 400);
        }
    }

    /**
     * Mengirim pesan dalam percakapan langsung.
     */
    public function send(SendDirectMessageRequest $request, int $conversationId): JsonResponse
    {
        try {
            $message = $this->directChatService->sendMessage(
                $request->user(),
                $conversationId,
                $request->input('message'),
                $request->input('type', 'text')
            );

            return $this->sendResponse(
                new DirectMessageResource($message),
                'Pesan berhasil dikirim.',
                201
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mengirim pesan: ' . $e->getMessage(), [], 400);
        }
    }
}
