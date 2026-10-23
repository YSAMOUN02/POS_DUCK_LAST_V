<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductImages;
use Illuminate\Console\Command;

/**
 * Builds the picker thumbnails for every product picture in one go.
 *
 * Optional: ProductImages::url() fills missing thumbnails in on its own, a few
 * per request. Running this once after deploying just gets every terminal onto
 * the small images immediately instead of over the first few page loads.
 */
class GenerateProductThumbnails extends Command
{
    protected $signature = 'images:thumbnails {--force : Rebuild thumbnails that already exist}';

    protected $description = 'Build the small WebP thumbnails shown in the product pickers';

    public function handle(): int
    {
        $images = Product::query()
            ->whereNotNull('image')
            ->where('image', '<>', '')
            ->distinct()
            ->pluck('image');

        $force = (bool) $this->option('force');
        $made = $skipped = $failed = 0;
        $failures = [];

        $bar = $this->output->createProgressBar($images->count());
        $bar->start();

        foreach ($images as $image) {
            // url() with lazy=false only REPORTS: non-null means a fresh
            // thumbnail is already on disk.
            if (! $force && ProductImages::url($image, false) !== null) {
                $skipped++;
            } elseif (ProductImages::make($image)) {
                $made++;
            } else {
                $failed++;
                $failures[] = $image;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Built', 'Already up to date', 'Could not build'], [[$made, $skipped, $failed]]);

        if ($failures) {
            // Usually a picture the database names but the disk does not have.
            // Those products keep showing the placeholder, as they already did.
            $this->warn('Could not build (missing or unreadable image):');
            foreach (array_slice($failures, 0, 20) as $name) {
                $this->line('  - ' . $name);
            }
            if (count($failures) > 20) {
                $this->line('  … and ' . (count($failures) - 20) . ' more');
            }
        }

        return self::SUCCESS;
    }
}
