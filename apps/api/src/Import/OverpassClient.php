<?php
declare(strict_types=1);

namespace ParkingZones\Import;

use JsonException;
use RuntimeException;
use Throwable;

final class OverpassClient
{
    private const DEFAULT_OVERPASS_URLS = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
    ];

    public function fetchParkingElements(): array
    {
        $overpassQuery = '[out:json][timeout:30];
(
  node["amenity"="parking"](60.10,24.45,60.38,25.25);
  way["amenity"="parking"](60.10,24.45,60.38,25.25);
);
out center 1500;';

        $errors = [];

        foreach ($this->overpassUrls() as $url) {
            echo "Trying Overpass endpoint {$url}...\n";

            try {
                return $this->fetchParkingElementsFromUrl($url, $overpassQuery);
            } catch (Throwable $exception) {
                $errors[] = "{$url}: {$exception->getMessage()}";
                fwrite(STDERR, "Overpass endpoint failed: {$exception->getMessage()}\n");
            }
        }

        throw new RuntimeException(
            'Error fetching data from Overpass API endpoints. ' . implode(' ', $errors)
        );
    }

    /**
     * @return list<string>
     */
    private function overpassUrls(): array
    {
        $configured = getenv('PARKING_ZONES_OVERPASS_URLS');
        if ($configured === false || trim($configured) === '') {
            return self::DEFAULT_OVERPASS_URLS;
        }

        $urls = [];
        foreach (explode(',', $configured) as $url) {
            $url = trim($url);

            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls !== [] ? $urls : self::DEFAULT_OVERPASS_URLS;
    }

    private function fetchParkingElementsFromUrl(string $url, string $overpassQuery): array
    {
        $context = stream_context_create([
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: QParkingZones/1.0\r\n",
                'method' => 'POST',
                'content' => 'data=' . urlencode($overpassQuery),
                'timeout' => 35,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $statusLine = $this->httpStatusLine($http_response_header ?? []);
            $message = 'Request failed';

            if ($statusLine !== null) {
                $message .= " ({$statusLine})";
            }

            throw new RuntimeException($message . '.');
        }

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Overpass API returned invalid JSON.', 0, $exception);
        }

        if (!is_array($data) || !isset($data['elements']) || !is_array($data['elements'])) {
            throw new RuntimeException('Overpass API returned an unexpected payload.');
        }

        return $data['elements'];
    }

    /**
     * @param list<string>|null $headers
     */
    private function httpStatusLine(?array $headers): ?string
    {
        foreach ($headers ?? [] as $header) {
            if (is_string($header) && str_starts_with($header, 'HTTP/')) {
                return $header;
            }
        }

        return null;
    }
}
