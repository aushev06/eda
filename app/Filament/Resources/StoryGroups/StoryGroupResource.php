<?php

namespace App\Filament\Resources\StoryGroups;

use App\Filament\Resources\StoryGroups\Pages\CreateStoryGroup;
use App\Filament\Resources\StoryGroups\Pages\EditStoryGroup;
use App\Filament\Resources\StoryGroups\Pages\ListStoryGroups;
use App\Filament\Resources\StoryGroups\RelationManagers\StoriesRelationManager;
use App\Filament\Resources\StoryGroups\Schemas\StoryGroupForm;
use App\Filament\Resources\StoryGroups\Tables\StoryGroupsTable;
use App\Models\StoryGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StoryGroupResource extends Resource
{
    protected static ?string $model = StoryGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?string $navigationLabel = 'Сторисы';

    protected static ?string $modelLabel = 'Группа сторисов';

    protected static ?string $pluralModelLabel = 'Группы сторисов';

    protected static string|\UnitEnum|null $navigationGroup = 'Меню';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return StoryGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoryGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoryGroups::route('/'),
            'create' => CreateStoryGroup::route('/create'),
            'edit' => EditStoryGroup::route('/{record}/edit'),
        ];
    }
}
