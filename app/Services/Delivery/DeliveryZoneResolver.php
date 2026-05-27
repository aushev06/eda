<?php

namespace App\Services\Delivery;

use App\Models\DeliveryZone;

class DeliveryZoneResolver
{
    /**
     * Find the active zone containing the given point. The first match by
     * sort_order wins, so overlapping zones can be prioritised by ordering.
     */
    public function resolve(float $lat, float $lng): ?DeliveryZone
    {
        return DeliveryZone::query()
            ->active()
            ->ordered()
            ->get()
            ->first(fn (DeliveryZone $zone) => $zone->containsPoint($lat, $lng));
    }
}
