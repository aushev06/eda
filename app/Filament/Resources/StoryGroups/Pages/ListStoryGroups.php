<?php

namespace App\Filament\Resources\StoryGroups\Pages;

use App\Filament\Resources\StoryGroups\StoryGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStoryGroups extends ListRecords
{
    protected static string $resource = StoryGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
