<?php

use App\Models\Category;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::create([
        'name' => 'Elektronik & Gadget',
        'slug' => 'gadget',
        'icon' => 'smartphone',
    ]);

    $this->userA = User::factory()->create([
        'name' => 'Ahmad Calon Pembeli',
        'email' => 'ahmad@adabarter.com',
        'city' => 'Jakarta Selatan',
    ]);

    $this->userB = User::factory()->create([
        'name' => 'Bambang Pemilik Barang',
        'email' => 'bambang@adabarter.com',
        'city' => 'Jakarta Timur',
    ]);

    $this->userC = User::factory()->create([
        'name' => 'Charlie Orang Lain',
        'email' => 'charlie@adabarter.com',
        'city' => 'Bandung',
    ]);

    $this->itemB = Item::create([
        'user_id' => $this->userB->id,
        'category_id' => $this->category->id,
        'title' => 'MacBook Pro M1',
        'description' => 'Kondisi mulus, jarang dipakai',
        'condition' => 'bekas_seperti_baru',
        'estimated_price' => 12000000,
        'city' => 'Jakarta Timur',
        'status' => 'active',
        'images' => ['https://example.com/macbook.jpg'],
    ]);

    $this->tokenA = $this->userA->createToken('testA')->plainTextToken;
    $this->tokenB = $this->userB->createToken('testB')->plainTextToken;
    $this->tokenC = $this->userC->createToken('testC')->plainTextToken;
});

test('user can start direct chat for another user item', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/items/{$this->itemB->id}/direct-chat");

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.initiator_id', $this->userA->id)
        ->assertJsonPath('data.receiver_id', $this->userB->id)
        ->assertJsonPath('data.item_id', $this->itemB->id)
        ->assertJsonPath('data.item.title', 'MacBook Pro M1');

    $this->assertDatabaseHas('conversations', [
        'initiator_id' => $this->userA->id,
        'receiver_id'  => $this->userB->id,
        'item_id'      => $this->itemB->id,
    ]);
});

test('starting direct chat multiple times is idempotent and returns existing conversation', function () {
    // First call
    $first = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/items/{$this->itemB->id}/direct-chat");

    $first->assertStatus(201);
    $convId1 = $first->json('data.id');

    // Second call
    $second = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->postJson("/api/items/{$this->itemB->id}/direct-chat");

    $second->assertStatus(201);
    $convId2 = $second->json('data.id');

    expect($convId1)->toBe($convId2);
    expect(Conversation::where('initiator_id', $this->userA->id)->where('item_id', $this->itemB->id)->count())->toBe(1);
});

test('user cannot start direct chat with own item', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
        ->postJson("/api/items/{$this->itemB->id}/direct-chat");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['item_id']);
});

test('participants can send and view direct messages', function () {
    // 1. Ahmad starts conversation
    $conv = Conversation::create([
        'initiator_id'    => $this->userA->id,
        'receiver_id'     => $this->userB->id,
        'item_id'         => $this->itemB->id,
        'last_message_at' => now(),
    ]);

    // 2. Ahmad sends message
    $sendA = $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/direct-chats/{$conv->id}/messages", [
            'message' => 'Halo gan, barangnya masih ready?',
        ]);

    $sendA->assertStatus(201)
        ->assertJsonPath('data.message', 'Halo gan, barangnya masih ready?')
        ->assertJsonPath('data.sender_id', $this->userA->id);

    // 3. Bambang views messages and sees it
    $viewB = $this->actingAs($this->userB, 'sanctum')
        ->getJson("/api/direct-chats/{$conv->id}");

    $viewB->assertStatus(200)
        ->assertJsonPath('data.messages.0.message', 'Halo gan, barangnya masih ready?');

    // 4. Verify message was marked as read by Bambang
    $msgId = $sendA->json('data.id');
    $msg = DirectMessage::find($msgId);
    expect($msg->read_at)->not->toBeNull();

    // 5. Bambang replies
    $sendB = $this->actingAs($this->userB, 'sanctum')
        ->postJson("/api/direct-chats/{$conv->id}/messages", [
            'message' => 'Masih ready gan, minat barter atau tanya-tanya?',
        ]);

    $sendB->assertStatus(201);
});

test('non-participant cannot view or send message in direct chat (IDOR protection)', function () {
    $conv = Conversation::create([
        'initiator_id'    => $this->userA->id,
        'receiver_id'     => $this->userB->id,
        'item_id'         => $this->itemB->id,
        'last_message_at' => now(),
    ]);

    // Charlie tries to view
    $responseView = $this->withHeader('Authorization', "Bearer {$this->tokenC}")
        ->getJson("/api/direct-chats/{$conv->id}");
    $responseView->assertStatus(403);

    // Charlie tries to send
    $responseSend = $this->withHeader('Authorization', "Bearer {$this->tokenC}")
        ->postJson("/api/direct-chats/{$conv->id}/messages", [
            'message' => 'Saya mau bajak percakapan ini',
        ]);
    $responseSend->assertStatus(403);
});

test('user conversation list shows both as initiator and as receiver', function () {
    // Conversation 1: Ahmad is initiator for Bambang's item
    $conv1 = Conversation::create([
        'initiator_id'    => $this->userA->id,
        'receiver_id'     => $this->userB->id,
        'item_id'         => $this->itemB->id,
        'last_message_at' => now()->subMinutes(10),
    ]);

    // Conversation 2: Charlie is initiator for Ahmad's item
    $itemA = Item::create([
        'user_id' => $this->userA->id,
        'category_id' => $this->category->id,
        'title' => 'iPhone 13',
        'description' => 'Mulus seperti baru',
        'condition' => 'bekas_baik',
        'status' => 'active',
    ]);
    $conv2 = Conversation::create([
        'initiator_id'    => $this->userC->id,
        'receiver_id'     => $this->userA->id,
        'item_id'         => $itemA->id,
        'last_message_at' => now(),
    ]);

    // Ahmad should see both convs (as initiator in conv1, as receiver in conv2)
    $response = $this->withHeader('Authorization', "Bearer {$this->tokenA}")
        ->getJson('/api/direct-chats');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    // Ordered by latest message (conv2 is first)
    expect($response->json('data.0.id'))->toBe($conv2->id);
    expect($response->json('data.1.id'))->toBe($conv1->id);
});

test('conversation seamlessly unifies direct messages and matched offer chats', function () {
    // 1. Ahmad chats directly with Bambang about MacBook Pro (before barter offer)
    $conv = Conversation::create([
        'initiator_id'    => $this->userA->id,
        'receiver_id'     => $this->userB->id,
        'item_id'         => $this->itemB->id,
        'last_message_at' => now(),
    ]);

    $this->actingAs($this->userA, 'sanctum')
        ->postJson("/api/direct-chats/{$conv->id}/messages", [
            'message' => 'Barang normal ga bang?',
        ])->assertStatus(201);

    // 2. Later, a barter offer is matched between Ahmad and Bambang for this item
    $itemA = Item::create([
        'user_id' => $this->userA->id,
        'category_id' => $this->category->id,
        'title' => 'Keyboard Mechanical',
        'description' => 'Mulus switch biru',
        'condition' => 'bekas_seperti_baru',
        'status' => 'active',
    ]);

    $offer = \App\Models\Offer::create([
        'offerer_user_id' => $this->userA->id,
        'target_user_id'  => $this->userB->id,
        'offerer_item_id' => $itemA->id,
        'target_item_id'  => $this->itemB->id,
        'status'          => 'matched',
        'matched_at'      => now(),
    ]);

    // 3. User links or accesses via /offers/{id}/conversation
    $resOfferConv = $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/offers/{$offer->id}/conversation");

    $resOfferConv->assertStatus(200)
        ->assertJsonPath('data.id', $conv->id)
        ->assertJsonPath('data.offer_id', $offer->id)
        ->assertJsonPath('data.is_matched', true);

    // 4. Bambang sends message in the matched barter
    \App\Models\Chat::create([
        'offer_id'  => $offer->id,
        'sender_id' => $this->userB->id,
        'message'   => 'p',
        'type'      => 'text',
    ]);

    // 5. Fetch messages from unified conversation: both messages appear together!
    $messagesRes = $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/direct-chats/{$conv->id}");

    $messagesRes->assertStatus(200);
    $allMessages = $messagesRes->json('data.messages');

    expect(count($allMessages))->toBe(2);
    expect($allMessages[0]['message'])->toBe('Barang normal ga bang?');
    expect($allMessages[1]['message'])->toBe('p');
});

