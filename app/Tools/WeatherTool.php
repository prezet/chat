<?php

namespace App\Tools;

use EchoLabs\Prism\Tool;
use Exception;
use Illuminate\Support\Facades\Http;

class WeatherTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('getWeather')
            ->for('Get the current weather at a location')
            ->withNumberParameter('latitude', 'The latitude of the location')
            ->withNumberParameter('longitude', 'The longitude of the location')
            ->using($this);
    }

    public function __invoke(int|float $latitude, int|float $longitude): string
    {
        // Validate coordinates
        if ($latitude < -90 || $latitude > 90) {
            throw new Exception('Latitude must be between -90 and 90 degrees');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new Exception('Longitude must be between -180 and 180 degrees');
        }

        $response = Http::get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m',
            'hourly' => 'temperature_2m',
            'daily' => 'sunrise,sunset',
            'timezone' => 'auto',
        ]);

        if (! $response->successful()) {
            throw new Exception('Failed to fetch weather data');
        }

        return $response->body();
    }
}
