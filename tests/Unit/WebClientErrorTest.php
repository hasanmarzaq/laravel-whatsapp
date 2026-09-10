<?php

namespace Kstmostofa\LaravelWhatsApp\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use Kstmostofa\LaravelWhatsApp\Exceptions\SidecarException;
use Kstmostofa\LaravelWhatsApp\Tests\Support\MocksGuzzle;
use Kstmostofa\LaravelWhatsApp\Tests\TestCase;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;

class WebClientErrorTest extends TestCase
{
    use MocksGuzzle;

    /**
     * WhatsApp Web's bundle is minified, so an exception thrown inside it arrives
     * as a stray identifier — getChats() currently fails with the single letter
     * "r". Passed through untouched it reaches the admin UI as a lone letter in a
     * red banner, with nothing for the operator to act on.
     */
    public function test_it_explains_a_minified_error_from_whatsapp_web(): void
    {
        $client = $this->app->make(WebClient::class);
        $this->mockGuzzleOn($client, [new Response(500, [], json_encode(['error' => 'r']))]);

        try {
            $client->request('GET', 'sessions/wa-notif/chats');
            $this->fail('Expected a SidecarException.');
        } catch (SidecarException $e) {
            $this->assertStringContainsString('GET sessions/wa-notif/chats', $e->getMessage());
            $this->assertStringContainsString('"r"', $e->getMessage());
            $this->assertStringContainsString('no usable detail', $e->getMessage());
            $this->assertSame(500, $e->getCode());
        }
    }

    public function test_it_keeps_a_meaningful_error_but_names_the_call(): void
    {
        $client = $this->app->make(WebClient::class);
        $this->mockGuzzleOn($client, [new Response(404, [], json_encode(['error' => 'session not found']))]);

        try {
            $client->request('POST', 'sessions/main/start');
            $this->fail('Expected a SidecarException.');
        } catch (SidecarException $e) {
            $this->assertStringContainsString('session not found', $e->getMessage());
            $this->assertStringContainsString('POST sessions/main/start', $e->getMessage());
        }
    }

    public function test_it_names_the_call_when_the_body_carries_no_error(): void
    {
        $client = $this->app->make(WebClient::class);
        $this->mockGuzzleOn($client, [new Response(502, [], '')]);

        try {
            $client->request('GET', 'sessions/wa-notif/groups');
            $this->fail('Expected a SidecarException.');
        } catch (SidecarException $e) {
            $this->assertSame('Sidecar HTTP 502 on GET sessions/wa-notif/groups', $e->getMessage());
        }
    }
}
