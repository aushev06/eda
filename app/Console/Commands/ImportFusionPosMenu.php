<?php

namespace App\Console\Commands;

use App\Support\FusionPosMenuImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fusionpos:import-menu {--activate : Сразу показывать новые позиции на сайте}')]
#[Description('Загрузить меню из FusionPOS: категории, блюда, модификаторы и их привязку')]
class ImportFusionPosMenu extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FusionPosMenuImporter $importer): int
    {
        $result = $importer->import((bool) $this->option('activate'));

        foreach ($result->errors as $error) {
            $this->warn($error);
        }

        $this->info("Блюд создано: {$result->created}, обновлено: {$result->updated}, скрыто: {$result->deactivated}, пропущено: {$result->skipped}.");
        $this->info("Групп модификаторов: {$result->modifierGroups}, вариантов: {$result->modifiers}.");

        return self::SUCCESS;
    }
}
