<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * FCM rejects the whole message — a 400, before delivery is attempted — if any
 * object-typed field arrives as a JSON list. An empty PHP array encodes as `[]`,
 * so a key built with array_filter() that filters everything out takes the entire
 * push down with it. That is exactly how `apns.fcm_options` broke every
 * notification that had no image.
 */
class FirebasePushPayloadTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function assertNoEmptyArraysAnywhere(array $payload, string $path = 'message'): void
    {
        foreach ($payload as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $this->assertNotSame([], $value, "{$path}.{$key} is an empty array and encodes as a JSON list, which FCM refuses.");
            $this->assertNoEmptyArraysAnywhere($value, "{$path}.{$key}");
        }
    }

    public function test_a_push_without_an_image_carries_no_empty_objects(): void
    {
        $payload = stylebite_firebase_message_payload('token-abc', 'Title', 'Body', ['type' => 'follow']);

        $this->assertNoEmptyArraysAnywhere($payload);
        $this->assertArrayNotHasKey('fcm_options', $payload['apns']);
    }

    public function test_a_push_with_an_image_sends_fcm_options_as_an_object(): void
    {
        $payload = stylebite_firebase_message_payload('token-abc', 'Title', 'Body', [], 'https://cdn.example.com/a.jpg');

        $this->assertNoEmptyArraysAnywhere($payload);
        $this->assertSame(['image' => 'https://cdn.example.com/a.jpg'], $payload['apns']['fcm_options']);
        $this->assertSame('https://cdn.example.com/a.jpg', $payload['android']['notification']['image']);
        $this->assertSame('https://cdn.example.com/a.jpg', $payload['notification']['image']);
    }

    public function test_an_empty_string_image_is_treated_as_no_image(): void
    {
        $payload = stylebite_firebase_message_payload('token-abc', 'Title', 'Body', [], '');

        $this->assertNoEmptyArraysAnywhere($payload);
        $this->assertArrayNotHasKey('fcm_options', $payload['apns']);
    }

    public function test_a_data_only_push_still_encodes_as_json_objects(): void
    {
        $payload = stylebite_firebase_message_payload('token-abc', null, null, ['type' => 'silent']);

        $this->assertNoEmptyArraysAnywhere($payload);
        $this->assertArrayNotHasKey('notification', $payload);

        // The real guard: what actually goes on the wire.
        $this->assertStringNotContainsString('[]', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
