<?php

namespace App\Console\Commands;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Foundation\Console\ViewCacheCommand;
use Illuminate\Support\Facades\Blade;

/**
 * TOdo: clean up
 */
class FlattenBladeFiles extends ViewCacheCommand
{
    const DEFAULT_ROUNDS = 3;

    protected $signature = 'view:flatten {--r|rounds=}';

    protected $description = 'Compile blades into 1 flat file';

    private static int $scope = 0;
    private $recusions = [];

    public function handle()
    {
        $this->comment("caching all views");
        $this->callSilent('view:cache');

        $rounds = (int)($this->option('rounds') ?? self::DEFAULT_ROUNDS);
        $this->overrideIncludeDirective();

        for ($i = 1; $i <= $rounds; $i++) {
            $this->comment("flattening all views $i times");
            $this->compileAllViews();
        }

        $this->newLine();
        $this->info('Blade templates flattened successfully.');
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
        $viewFactory = app()->make(ViewFactory::class);
        Blade::directive('include', function ($expression) use ($viewFactory) {
            $split = explode(",", $expression, 2);

            $path = $this->viewToPath($viewFactory, $split[0]);

            $renderFile = Blade::getCompiledPath($path);
            $content = @file_get_contents($renderFile);
            if (!$content) {
                $this->components->warn("Cached view was not found: $expression");
                return "";
            }
            $viewNameWithQuotations = $split[0];
            if (str_contains($content, $split[0])) {
                if (empty($this->recusions[$viewNameWithQuotations])) {
                    $this->warn("detected recursion: $viewNameWithQuotations");
                    $this->recusions[$viewNameWithQuotations] = true;
                }
                return $this->compileRegularInclude($expression);
            }
            $vars = trim($split[1] ?? '');
            $declaredVars = $this->getAllVariablesDeclaredInsideTheTemplate($content);
            [$startScope, $endScope] = $this->scopeVariables($vars, $declaredVars);
            return "$startScope $content $endScope";
        });
    }

    private function viewToPath(ViewFactory $factory, $viewName)
    {
        $viewName = trim(str_replace(['"', "'"], "", $viewName));
        return $factory->getFinder()->find($viewName);
    }

    /**
     * Here we are temporarily storing all variables that would be overwritten in a $__scop array
     * Afterward we are unsetting all variables passed to the view
     */
    private function scopeVariables(string $passedVars, string $declaredVars): array
    {
        self::$scope++;
        $scopeName = '$__scop' . self::$scope;

        if ($passedVars) {
            $startScope = '<?php foreach (' . $passedVars . ' as $k => $v){ ' . $scopeName . '[$k] = $$k ?? null; $$k = $v; } ?>';
            $endScope = '<?php foreach (' . $scopeName . ' as $var => $orig){ if($orig !== null){$$var=$orig;}else{unset($$var);} };unset(' . $scopeName . '); ?>';
        } else {
            $startScope = '';
            $endScope = '';
        }
        if ($declaredVars) {
            $startScope .= '<?php foreach ([' . $declaredVars . '] as $v){ ' . $scopeName . '[$v] = $$v ?? null; } ?>';
        }
        return [$startScope, $endScope];
    }

    /**
     * here we are using a regex to get all variables that are declared inside the template
     * aka $variable = x
     */
    private function getAllVariablesDeclaredInsideTheTemplate(string $viewContent): string
    {
        preg_match_all('/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*=/m', $viewContent, $matches);
        $variableOccurrences = array_unique($matches[1]);
        $declaredVars = '';
        foreach ($variableOccurrences as $variableName) {
            $declaredVars .= "'$variableName',";
        }
        return rtrim($declaredVars, ',');
    }

    private function compileRegularInclude($expression)
    {
        Blade::stripParentheses($expression);

        return "<?php echo \$__env->make({$expression}, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>";
    }

}
