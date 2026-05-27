<?php

namespace App\Filament\Resources\StoryGroups\Pages;

use App\Filament\Resources\StoryGroups\StoryGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStoryGroup extends EditRecord
{
    protected static string $resource = StoryGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
