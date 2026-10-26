<x-filament-panels::page>
    <form wire:submit="import" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                Загрузить меню
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
