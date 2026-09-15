<?php

namespace App\Tests\Controller;

use JsonException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReadingControllerTest extends WebTestCase
{
    private const TOKEN =
        'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.'
        . 'eyJzdWIiOiJwdWxzZS1jYW5kaWRhdGUiLCJpYXQiOjE3ODg1MzQ0NjMs'
        . 'ImV4cCI6NDEwMjQ0NDgwMH0.'
        . 'zAE8ojECzgvxIYVE1IQs4gM0Tj0YW5PRVhqb5sjyFVE';

    /**
     * @throws JsonException
     */
    public function testHealthIsPublic(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('OK', $data['status']);
    }

    public function testReadingsRequireAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/devices/test-device/readings');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @throws JsonException
     */
    public function testCreateListAndDeleteReading(): void
    {
        $client = static::createClient();

        $headers = [
            'HTTP_AUTHORIZATION' => 'Bearer ' . self::TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ];

        $client->request(
            'POST',
            '/devices/test-device/readings',
            server: $headers,
            content: json_encode([
                'type' => 'oxygen_saturation',
                'value' => 89,
                'timestamp' => '2026-09-15T10:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_CREATED
        );

        $created = json_decode($client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('test-device', $created['device_id']);
        self::assertSame('oxygen_saturation', $created['type']);
        self::assertSame(89, $created['value']);
        self::assertTrue($created['critical']);

        $readingId = $created['id'];

        $client->request(
            'GET',
            '/devices/test-device/readings',
            server: $headers
        );

        self::assertResponseIsSuccessful();

        $readings = json_decode($client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertNotEmpty($readings);

        $ids = array_column($readings, 'id');

        self::assertContains($readingId, $ids);

        $client->request(
            'DELETE',
            '/devices/test-device/readings/' . $readingId,
            server: $headers
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request(
            'GET',
            '/devices/test-device/readings',
            server: $headers
        );

        $remainingReadings = json_decode($client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $remainingIds = array_column($remainingReadings, 'id');

        self::assertNotContains($readingId, $remainingIds);
    }
}
