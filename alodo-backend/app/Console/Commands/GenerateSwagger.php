<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Generator;

#[Signature('swagger:generate')]
#[Description('Générer OpenAPI depuis les annotations @OA des contrôleurs')]
class GenerateSwagger extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $generator = new Generator;
        $generator->setVersion('3.0.3');
        $generator->setAnalyser(new ReflectionAnalyser([new DocBlockAnnotationFactory]));
        $openApi = $generator->generate([app_path('Http/Controllers')]);

        if (! $openApi || ! $openApi->validate()) {
            $this->error('Les annotations OpenAPI sont invalides.');

            return self::FAILURE;
        }

        $openApi->saveAs(public_path('openapi.json'));
        $this->info('Documentation générée : /swagger (JSON : /openapi.json).');

        return self::SUCCESS;
    }
}
