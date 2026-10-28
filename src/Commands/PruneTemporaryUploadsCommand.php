<?php

namespace Xelqun\Forms\Commands;

use Illuminate\Console\Command;
use Xelqun\Forms\Uploads\TemporaryUploadStore;

class PruneTemporaryUploadsCommand extends Command
{
    protected $signature = 'xelqun-forms:prune-uploads';

    protected $description = 'Delete temporary form uploads that were never saved';

    public function handle(TemporaryUploadStore $store): int
    {
        $this->components->info("Pruned {$store->prune()} temporary upload(s).");

        return self::SUCCESS;
    }
}
