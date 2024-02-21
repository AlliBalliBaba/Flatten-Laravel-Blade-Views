<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ViewCacheCommand;
use Illuminate\Support\Facades\Blade;

class FlattenBladeFiles extends ViewCacheCommand
{

    protected $signature = 'view:flatten {--r|rounds=}';

    protected $description = 'Compile blades into 1 flat file';

    public function handle()
    {
        echo "caching all views\n";
        $this->callSilent('view:cache');

        $rounds = (int)($this->option('rounds') ?? 4);
        $this->overrideIncludeDirective();

        for ($i = 1; $i <= $rounds; $i++) {
            echo "flattening all views $i times\n";
            $this->compileAllViews();
        }

        $this->newLine();
        $this->components->info('Blade templates flattened successfully.');
    }

    /**
     * We are shuffling and recompiling views to flatten nested includes
     * We are not doing this forever though to avoid endless loops with recursive templates
     */
    private function compileAllViews(): void
    {
        $this->paths()->each(function ($path) {
            $files = $this->bladeFilesIn([$path]);
            $compiler = $this->laravel['view']->getEngineResolver()->resolve('blade')->getCompiler();
            foreach ($files->shuffle() as $file) {
                $compiler->compile($file->getRealPath());
            }
        });
    }

    /**
     * Here we are overriding the default Blade include
     * Instead we are injecting the cached file directly
     * into the flattened file
     */
    private function overrideIncludeDirective(): void
    {
        Blade::directive('include', function ($view) {
            $split = explode(",", $view, 2);
            $vars = trim($split[1] ?? null);
            $vars = $vars ? "<?php extract($vars) ?>" : "";
            $path = $this->viewToPath($split[0]);
            $renderFile = Blade::getCompiledPath($path);
            $content = @file_get_contents($renderFile);
            if (!$content) {
                throw new \Exception("View was not found: $renderFile");
            }
            return "$vars $content";
        });
    }

    /**
     * Laravel probably has something built in that dos this and I just haven't found it yet:
     */
    private function viewToPath($view)
    {
        $path = str_replace(".", "/", $view);
        $path = str_replace(['"', "'"], "", $path);
        return resource_path("views/$path.blade.php");
    }

}
