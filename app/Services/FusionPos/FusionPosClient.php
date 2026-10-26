<?php

namespace App\Services\FusionPos;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the FusionPOS REST API.
 *
 * The base URL and bearer token are configured in the admin panel and stored
 * in settings (`fusionpos.url`, `fusionpos.token`).
 */
class FusionPosClient
{
    /**
     * Hard stop for pagination in case the API keeps returning pages.
     */
    private const MAX_PAGES = 500;

    public function isConfigured(): bool
    {
        return filled(Settings::get('fusionpos.url')) && filled(Settings::get('fusionpos.token'));
    }

    /**
     * Every non-deleted menu position: dishes and modifier options alike.
     *
     * @return array<int, array<string, mixed>>
     */
    public function menuItems(): array
    {
        return $this->paginate('/api/v1/menu');
    }

    /**
     * Every non-deleted menu category as a flat list. Categories flagged with
     * `is_modifier = yes` are modifier groups; the rest hold dishes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->paginate('/api/v1/menucategory');
    }

    /**
     * Modifier categories together with what they are attached to:
     * `modifierMenus` lists dishes, `modifierCategoryMenus` lists dish
     * categories whose every dish gets the modifier group.
     *
     * @return array<int, array<string, mixed>>
     */
    public function modifierLinks(): array
    {
        return $this->request()
            ->get('/api/v2/menu-categories', ['modifierIs' => 'yes', 'size' => 0])
            ->throw()
            ->json('items') ?? [];
    }

    /**
     * Walk through all pages of a v1 list endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    private function paginate(string $path): array
    {
        $items = [];
        $page = 1;

        do {
            $data = $this->request()
                ->get($path, [
                    'page' => $page,
                    'filter' => json_encode(['is_deleted' => 'no']),
                ])
                ->throw()
                ->json('data');

            $pageItems = $data['items'] ?? [];
            array_push($items, ...$pageItems);

            $pageCount = (int) ($data['_meta']['pageCount'] ?? 0);
            $page++;
        } while ($pageItems !== [] && $page <= $pageCount && $page <= self::MAX_PAGES);

        return $items;
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('FusionPOS не настроен: укажите адрес API и токен.');
        }

        return Http::baseUrl(rtrim((string) Settings::get('fusionpos.url'), '/'))
            ->withToken((string) Settings::get('fusionpos.token'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }
}
