<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotification;
use App\Models\PushSubscription;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function key(PushService $push): JsonResponse
    {
        return response()->json(['publicKey' => $push->publicKey()]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string']]);
        PushSubscription::where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Zkušební notifikace sám sobě */
    public function test(Request $request): JsonResponse
    {
        $user = $request->user();
        SendPushNotification::dispatch($user->id, [
            'title' => 'PAP Service',
            'body' => $user->locale === 'pl' ? 'Powiadomienia działają ✔' : 'Upozornění fungují ✔',
            'url' => '/technik/',
            'tag' => 'test',
        ]);

        return response()->json(['ok' => true, 'devices' => PushSubscription::where('user_id', $user->id)->count()]);
    }
}
