<?php

namespace App\Filament\Resources\StoryGroups\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'stories';

    protected static ?string $title = 'Сторисы';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Медиа')
                    ->columns(2)
                    ->schema([
                        Select::make('media_type')
                            ->label('Тип')
                            ->options([
                                'image' => 'Фото',
                                'video' => 'Видео',
                            ])
                            ->required()
                            ->live()
                            ->default('image'),
                        TextInput::make('duration_ms')
                            ->label('Длительность, мс')
                            ->numeric()
                            ->minValue(1000)
                            ->maxValue(60000)
                            ->default(5000)
                            ->required()
                            ->helperText('Для фото — сколько показывать. Для видео — игнорируется, берётся длительность ролика.'),
                        FileUpload::make('media_path')
                            ->label('Файл')
                            ->disk('public')
                            ->directory('stories')
                            ->visibility('public')
                            ->required()
                            ->maxSize(51200)
                            ->acceptedFileTypes(fn (callable $get) => $get('media_type') === 'video'
                                ? ['video/mp4', 'video/webm', 'video/quicktime']
                                : ['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Фото: JPG/PNG/WebP до 5 МБ. Видео: MP4/WebM до 50 МБ.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Действие (необязательно)')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('cta_label')
                            ->label('Текст кнопки')
                            ->maxLength(80),
                        TextInput::make('cta_url')
                            ->label('Ссылка')
                            ->url()
                            ->maxLength(255),
                    ]),

                Section::make('Статус')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('media_path')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('media_path')
                    ->label('Превью')
                    ->disk('public')
                    ->square(),
                TextColumn::make('media_type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'video' ? 'Видео' : 'Фото'),
                TextColumn::make('duration_ms')
                    ->label('Длительность')
                    ->formatStateUsing(fn (int $state) => round($state / 1000, 1).' с'),
                TextColumn::make('cta_label')
                    ->label('Кнопка')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
