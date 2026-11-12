<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\Item;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class DirectChatService
{
    /**
     * Start or get an existing conversation for an item, linking active offer if present.
     */
    public function getOrCreateConversation(User $initiator, int $itemId): Conversation
    {
        /** @var Item|null $item */
        $item = Item::with('user')->find($itemId);

        if (!$item) {
            throw new \Exception('Barang tidak ditemukan.');
        }

        if ($item->user_id === $initiator->id) {
            throw ValidationException::withMessages([
                'item_id' => ['Anda tidak dapat memulai obrolan untuk barang milik Anda sendiri.'],
            ]);
        }

        if ($initiator->hasRestriction('no_direct_chat')) {
            throw ValidationException::withMessages([
                'restriction' => ['Akun Anda sedang dibatasi dari memulai obrolan langsung baru karena akumulasi poin penalti.'],
            ]);
        }

        // Look for existing conversation between this initiator, item owner, and this item
        $conversation = Conversation::firstOrCreate(
            [
                'initiator_id' => $initiator->id,
                'receiver_id'  => $item->user_id,
                'item_id'      => $item->id,
            ],
            [
                'last_message_at' => now(),
            ]
        );

        // Link active offer between these two users involving this item if not linked yet
        if (!$conversation->offer_id) {
            $activeOffer = Offer::where(function ($q) use ($initiator, $item) {
                $q->where(function ($q2) use ($initiator, $item) {
                    $q2->where('offerer_user_id', $initiator->id)
                       ->where('target_user_id', $item->user_id);
                })->orWhere(function ($q2) use ($initiator, $item) {
                    $q2->where('offerer_user_id', $item->user_id)
                       ->where('target_user_id', $initiator->id);
                });
            })
            ->where(function ($q) use ($item) {
                $q->where('target_item_id', $item->id)
                  ->orWhere('offerer_item_id', $item->id);
            })
            ->whereIn('status', ['pending', 'matched', 'completed'])
            ->latest()
            ->first();

            if ($activeOffer) {
                $conversation->update(['offer_id' => $activeOffer->id]);
            }
        }

        return $conversation->load([
            'initiator',
            'receiver',
            'item',
            'offer.offererItem',
            'offer.targetItem',
            'offer.offererItems',
            'latestMessage.sender',
        ]);
    }

    /**
     * Get or create a conversation linked to a specific Offer.
     */
    public function getOrCreateForOffer(User $user, int $offerId): Conversation
    {
        /** @var Offer|null $offer */
        $offer = Offer::find($offerId);

        if (!$offer) {
            throw new \Exception('Penawaran barter tidak ditemukan.');
        }

        if ($offer->offerer_user_id !== $user->id && $offer->target_user_id !== $user->id) {
            throw new AuthorizationException('Anda tidak memiliki akses ke percakapan untuk barter ini.');
        }

        $itemId = $offer->target_item_id ?? $offer->offerer_item_id;

        $conversation = Conversation::firstOrCreate(
            [
                'initiator_id' => $offer->offerer_user_id,
                'receiver_id'  => $offer->target_user_id,
                'item_id'      => $itemId,
            ],
            [
                'offer_id'        => $offer->id,
                'last_message_at' => $offer->matched_at ?? $offer->created_at,
            ]
        );

        if ($conversation->offer_id !== $offer->id) {
            $conversation->update(['offer_id' => $offer->id]);
        }

        return $conversation->load([
            'initiator',
            'receiver',
            'item',
            'offer.offererItem',
            'offer.targetItem',
            'offer.offererItems',
            'latestMessage.sender',
        ]);
    }

    /**
     * Get all conversations for a user, unified with their barter offers.
     */
    public function getUserConversations(User $user): Collection
    {
        // 1. Sync any matched/completed offers into conversations so there are no orphan offers
        $matchedOffers = Offer::where(function ($q) use ($user) {
            $q->where('offerer_user_id', $user->id)
              ->orWhere('target_user_id', $user->id);
        })
        ->whereIn('status', ['matched', 'completed'])
        ->get();

        foreach ($matchedOffers as $mo) {
            $itemId = $mo->target_item_id ?? $mo->offerer_item_id;
            if ($itemId) {
                $conv = Conversation::firstOrCreate(
                    [
                        'initiator_id' => $mo->offerer_user_id,
                        'receiver_id'  => $mo->target_user_id,
                        'item_id'      => $itemId,
                    ],
                    [
                        'offer_id'        => $mo->id,
                        'last_message_at' => $mo->matched_at ?? $mo->created_at,
                    ]
                );

                if (!$conv->offer_id) {
                    $conv->update(['offer_id' => $mo->id]);
                }
            }
        }

        // 2. Return all conversations with eager-loaded relations
        return Conversation::with([
            'initiator',
            'receiver',
            'item',
            'offer.offererItem',
            'offer.targetItem',
            'offer.offererItems',
            'latestMessage.sender',
        ])
        ->where(function ($query) use ($user) {
            $query->where('initiator_id', $user->id)
                  ->orWhere('receiver_id', $user->id);
        })
        ->orderByDesc('last_message_at')
        ->orderByDesc('updated_at')
        ->get();
    }

    /**
     * Get all messages in a conversation (merging direct messages and offer chat messages) and mark unread as read.
     */
    public function getMessages(User $user, int $conversationId): array
    {
        /** @var Conversation|null $conversation */
        $conversation = Conversation::with([
            'initiator',
            'receiver',
            'item',
            'offer.offererItem',
            'offer.targetItem',
            'offer.offererItems',
            'latestMessage.sender',
        ])->find($conversationId);

        if (!$conversation) {
            throw new \Exception('Percakapan tidak ditemukan.');
        }

        if ($conversation->initiator_id !== $user->id && $conversation->receiver_id !== $user->id) {
            throw new AuthorizationException('Anda tidak memiliki akses ke percakapan ini.');
        }

        // Mark direct messages sent by the other party as read
        DirectMessage::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $directMessages = DirectMessage::with('sender')
            ->where('conversation_id', $conversationId)
            ->get();

        // If this conversation is linked to an offer, also fetch and mark read any offer chats
        $chatMessages = collect();
        if ($conversation->offer_id) {
            Chat::where('offer_id', $conversation->offer_id)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $existingDirectTexts = $directMessages->map(fn($m) => $m->sender_id . ':' . $m->message)->flip();

            $chatMessages = Chat::with('sender')
                ->where('offer_id', $conversation->offer_id)
                ->get()
                ->filter(function ($chat) use ($existingDirectTexts) {
                    // Prevent duplicate if identical message is already in direct_messages
                    return !isset($existingDirectTexts[$chat->sender_id . ':' . $chat->message]);
                })
                ->map(function ($chat) use ($conversationId) {
                    $dm = new DirectMessage([
                        'conversation_id' => $conversationId,
                        'sender_id'       => $chat->sender_id,
                        'message'         => $chat->message,
                        'type'            => $chat->type,
                        'read_at'         => $chat->read_at,
                    ]);
                    $dm->id = $chat->id;
                    $dm->created_at = $chat->created_at;
                    $dm->updated_at = $chat->updated_at;
                    $dm->setRelation('sender', $chat->sender);
                    return $dm;
                });
        }

        $allMessages = $directMessages->concat($chatMessages)->sortBy('created_at')->values();

        return [
            'conversation' => $conversation,
            'messages'     => $allMessages,
        ];
    }

    /**
     * Send a direct message in a conversation and sync to offer chats if linked.
     */
    public function sendMessage(User $user, int $conversationId, string $message, string $type = 'text'): DirectMessage
    {
        /** @var Conversation|null $conversation */
        $conversation = Conversation::find($conversationId);

        if (!$conversation) {
            throw new \Exception('Percakapan tidak ditemukan.');
        }

        if ($conversation->initiator_id !== $user->id && $conversation->receiver_id !== $user->id) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk mengirim pesan di percakapan ini.');
        }

        $directMessage = DirectMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $message,
            'type'            => $type,
        ]);

        // If linked to an offer, also record in chats for backward compatibility
        if ($conversation->offer_id) {
            Chat::create([
                'offer_id'  => $conversation->offer_id,
                'sender_id' => $user->id,
                'message'   => $message,
                'type'      => $type,
            ]);
        }

        $conversation->update(['last_message_at' => now()]);

        return $directMessage->load('sender');
    }
}

