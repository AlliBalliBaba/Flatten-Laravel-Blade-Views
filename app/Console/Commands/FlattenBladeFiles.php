<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ViewCacheCommand;
use Illuminate\Support\Facades\Blade;

class FlattenBladeFiles extends ViewCacheCommand
{
    const DEFAULT_ROUNDS = 3;

    protected $signature = 'view:flatten {--r|rounds=}';

    protected $description = 'Compile blades into 1 flat file';

    private static int $scope = 0;

    public function handle()
    {
        echo "caching all views\n";
        $this->callSilent('view:cache');

        $rounds = (int)($this->option('rounds') ?? self::DEFAULT_ROUNDS);
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
            $vars = trim($split[1] ?? '');
            $path = $this->viewToPath($split[0]);
            $renderFile = Blade::getCompiledPath($path);
            $content = @file_get_contents($renderFile);
            if (!$content) {
                $this->components->warn("Cached view was not found: $view");
                return "";
            }
            [$declaration, $unset] = $this->scopeVariables($vars);
            return "$declaration $content $unset";
        });
    }

    /**
     * Laravel probably has something built in that dos this and I just haven't found it yet:
     */
    private function viewToPath($view)
    {
        $path = str_replace(".", "/", $view);
        $path = str_replace(['"', "'"], "", $path);
        $path = trim($path);
        return resource_path("views/$path.blade.php");
    }

    /**
     * Here we are temporarily storing all variables that would be overwritten in a $__scop array
     * Afterward we are unsetting all variables passed to the view
     */
    private function scopeVariables(string $vars): array
    {
        $vars = trim($vars);
        if (!$vars) {
            return ['', ''];
        }
        self::$scope++;
        $scopeName = '$__scop' . self::$scope;
        $declaration = '<?php foreach (' . $vars . ' as $k => $v){ ' . $scopeName . '[$k] = $$k ?? null; $$k = $v; } ?>';
        $unset = '<?php foreach (' . $scopeName . ' as $var => $orig){ if($orig !== null){$$var=$orig;}else{unset($$var);} };unset(' . $scopeName . '); ?>';

        return [$declaration, $unset];
    }

}
