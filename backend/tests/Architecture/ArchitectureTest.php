<?php

namespace Tests\Architecture;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\Documents\Contracts\DocumentParserInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

class ArchitectureTest extends TestCase
{
    public function test_all_api_controllers_extend_base_controller(): void
    {
        $controllerFiles = glob(app_path('Http/Controllers/Api/V1/*.php'));

        foreach ($controllerFiles as $file) {
            $className = 'App\\Http\\Controllers\\Api\\V1\\' . basename($file, '.php');
            if (class_exists($className) && $className !== BaseApiController::class) {
                $reflector = new \ReflectionClass($className);
                $this->assertTrue(
                    $reflector->isSubclassOf(BaseApiController::class) || $reflector->isSubclassOf(Controller::class),
                    "{$className} must extend BaseApiController or Controller"
                );
            }
        }
    }

    public function test_all_models_extend_eloquent_model(): void
    {
        $modelFiles = glob(app_path('Models/*.php'));

        foreach ($modelFiles as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');
            if (class_exists($className)) {
                $reflector = new \ReflectionClass($className);
                $this->assertTrue(
                    $reflector->isSubclassOf(Model::class),
                    "{$className} must extend Illuminate\\Database\\Eloquent\\Model"
                );
            }
        }
    }

    public function test_all_ai_providers_implement_provider_interface(): void
    {
        $providerFiles = glob(app_path('Services/AI/Providers/*Provider.php'));

        foreach ($providerFiles as $file) {
            $baseName = basename($file, '.php');
            if ($baseName === 'BaseAIProvider') {
                continue;
            }

            $className = 'App\\Services\\AI\\Providers\\' . $baseName;
            if (class_exists($className)) {
                $reflector = new \ReflectionClass($className);
                $this->assertTrue(
                    $reflector->implementsInterface(AIProviderInterface::class),
                    "{$className} must implement AIProviderInterface"
                );
            }
        }
    }

    public function test_all_document_parsers_implement_parser_interface(): void
    {
        $parserFiles = glob(app_path('Services/Documents/Parsers/*Parser.php'));

        foreach ($parserFiles as $file) {
            $className = 'App\\Services\\Documents\\Parsers\\' . basename($file, '.php');
            if (class_exists($className)) {
                $reflector = new \ReflectionClass($className);
                $this->assertTrue(
                    $reflector->implementsInterface(DocumentParserInterface::class),
                    "{$className} must implement DocumentParserInterface"
                );
            }
        }
    }

    public function test_all_middleware_implement_handle_method(): void
    {
        $middlewareFiles = glob(app_path('Http/Middleware/*.php'));

        foreach ($middlewareFiles as $file) {
            $className = 'App\\Http\\Middleware\\' . basename($file, '.php');
            if (class_exists($className)) {
                $reflector = new \ReflectionClass($className);
                $this->assertTrue(
                    $reflector->hasMethod('handle'),
                    "Middleware {$className} must implement handle() method"
                );
            }
        }
    }
}
