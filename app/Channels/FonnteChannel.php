<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toFonnte($notifiable);
        $token = env('FONNTE_TOKEN');

        if (!$token) {
            Log::warning('Fonnte token is not set. WhatsApp message not sent.');
            return;
        }

        // Determine the phone number to send to.
        $target = null;
        if (method_exists($notifiable, 'routeNotificationForFonnte')) {
            $target = $notifiable->routeNotificationForFonnte($notification);
        } else {
            $target = $notifiable->phone_number;
        }

        if (!$target) {
            Log::warning('No phone number found for user ' . $notifiable->id);
            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token
            ])->post('https://api.fonnte.com/send', [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ]);

            $result = $response->json();

            if (!isset($result['status']) || $result['status'] == false) {
                Log::error('Fonnte API Error: ' . json_encode($result));
            }
        } catch (\Exception $e) {
            Log::error('Fonnte Exception: ' . $e->getMessage());
        }
    }
}
