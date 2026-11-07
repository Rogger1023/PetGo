<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportProductImages extends Command
{
    protected $signature = 'products:import-images
        {--path=/tmp/petgo-product-images}
        {--dry-run}
        {--replace}';

    protected $description = 'Importa imagens de produtos pelo slug.';

    public function handle(): int
    {
        $directory = rtrim(
            (string) $this->option('path'),
            '/'
        );

        if (!is_dir($directory)) {
            $this->error("Diretório não encontrado: {$directory}");

            return self::FAILURE;
        }

        $allowedMimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        $files = glob($directory . '/*') ?: [];
        sort($files);

        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $extension = strtolower(
                pathinfo($file, PATHINFO_EXTENSION)
            );

            $slug = pathinfo($file, PATHINFO_FILENAME);

            if (!isset($allowedMimeTypes[$extension])) {
                $this->warn("Formato ignorado: {$file}");
                $skipped++;
                continue;
            }

            $product = Product::where('slug', $slug)->first();

            if (!$product) {
                $this->warn("Produto não encontrado: {$slug}");
                $skipped++;
                continue;
            }

            if ($product->image_path && !$this->option('replace')) {
                $this->warn("Produto já possui imagem: {$slug}");
                $skipped++;
                continue;
            }

            $size = filesize($file);

            if ($size === false || $size === 0 || $size > 5 * 1024 * 1024) {
                $this->warn("Tamanho inválido: {$slug}");
                $skipped++;
                continue;
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);

            if ($mime !== $allowedMimeTypes[$extension]) {
                $this->warn("Tipo de arquivo inválido: {$slug}");
                $skipped++;
                continue;
            }

            $path = "products/{$product->id}/{$slug}.{$extension}";

            if ($this->option('dry-run')) {
                $this->line("Importaria: {$slug} => {$path}");
                $imported++;
                continue;
            }

            $stream = fopen($file, 'rb');

            if ($stream === false) {
                $this->error("Não foi possível ler: {$slug}");
                $skipped++;
                continue;
            }

            try {
                $saved = Storage::disk('public')->put($path, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (!$saved) {
                $this->error("Falha ao salvar: {$slug}");
                $skipped++;
                continue;
            }

            $product->update([
                'image_path' => $path,
            ]);

            $this->info("Imagem cadastrada: {$slug}");
            $imported++;
        }

        $this->info(
            "Processados: {$imported} | Ignorados: {$skipped}"
        );

        return self::SUCCESS;
    }
}
