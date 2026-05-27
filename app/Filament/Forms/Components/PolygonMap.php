<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class PolygonMap extends Field
{
    protected string $view = 'filament.forms.components.polygon-map';

    protected float $defaultLatitude = 55.7558;

    protected float $defaultLongitude = 37.6173;

    protected int $defaultZoom = 11;

    protected int $height = 420;

    public function defaultLocation(float $lat, float $lng, int $zoom = 11): static
    {
        $this->defaultLatitude = $lat;
        $this->defaultLongitude = $lng;
        $this->defaultZoom = $zoom;

        return $this;
    }

    public function height(int $pixels): static
    {
        $this->height = $pixels;

        return $this;
    }

    public function getDefaultLatitude(): float
    {
        return $this->defaultLatitude;
    }

    public function getDefaultLongitude(): float
    {
        return $this->defaultLongitude;
    }

    public function getDefaultZoom(): int
    {
        return $this->defaultZoom;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}
