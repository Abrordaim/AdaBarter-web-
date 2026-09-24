<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SendMessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\OfferResource;
use App\Services\ChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends BaseApiController
{
    public function __construct(
        protected ChatService $chatService
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $conversations = $this->chatService->getUserConversations($request->user());

        return $this->sendResponse(
            OfferResource::collection($conversations),
            'Daftar obrolan negosiasi berhasil diambil.'
        );
    }

    public function messages(Request $request, int $offerId): JsonResponse
    {
        try {
            $messages = $this->chatService->getOfferMessages($request->user(), $offerId);

            return $this->sendResponse(
                ChatResource::collection($messages),
                'Pesan obrolan berhasil diambil.'
            );
        } catch (AuthorizationException $e) {
            return $this->sendError($e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->sendError('Gagal memuat pesan obrolan: ' . $e->getMessage(), [], 400);
        }
    }

    public function send(SendMessageRequest $request, int $offerId): JsonResponse
    {
        try {
            $chat = $this->chatService->sendMessage(
                $request->user(),
                $offerId,
                $request->input('message'),
                $request->input('type', 'text')
            );

            return $this->sendResponse(
                new ChatResource($chat),
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
