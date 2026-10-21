<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use InvalidArgumentException;
use Tpadzik\Sheetflow\Http\Controllers\WizardController;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;
use Tpadzik\Sheetflow\Support\ImportGate;

class Sheetflow
{
    /**
     * @param  class-string<ImportDefinition>  $definition
     */
    public static function definition(string $key, string $definition): void
    {
        app(DefinitionRegistry::class)->register(
            key: $key,
            definition: $definition,
        );
    }

    /**
     * Register the rule that decides who may open an import.
     *
     * The callable receives the current user, or null for a guest, and the definition key.
     * Return true to allow that model. A class name must be invokable with the same arguments.
     */
    public static function authorize(callable|string $authorizer): void
    {
        app(ImportGate::class)->set($authorizer);
    }

    public static function wizard(string $definition): View
    {
        return app(WizardController::class)->show($definition);
    }

    /**
     * Register the JSON API, and the default wizard when $wizard is true.
     *
     * Call this again with a different prefix and route name to mount another copy.
     * Set sheetflow.register_routes to false first when the copy should replace the default group.
     *
     * @param  list<string>|string|null  $middleware
     */
    public static function routes(
        ?string $prefix = null,
        array|string|null $middleware = null,
        ?string $name = null,
        ?bool $wizard = null,
    ): void {
        $prefix ??= (string) config('sheetflow.prefix', 'sheetflow');
        $middleware ??= config('sheetflow.middleware', ['web', 'auth']);
        $name ??= 'sheetflow.';
        $wizard ??= (bool) config('sheetflow.register_wizard', true);

        Router::middleware($middleware)
            ->prefix($prefix)
            ->name($name)
            ->group(function () use ($wizard): void {
                require __DIR__.'/../routes/web.php';

                if ($wizard) {
                    Router::get('{definition}', [WizardController::class, 'show'])
                        ->where('definition', '^(?!api$|assets$).+')
                        ->name('wizard');
                }
            });
    }

    /**
     * Point one URI at one import definition.
     *
     * The returned route can take a name and extra middleware. Middleware is omitted
     * when null so a route inside an existing group is not wrapped twice.
     *
     * @param  list<string>|string|null  $middleware
     */
    public static function wizardRoute(
        string $uri,
        string $definition,
        array|string|null $middleware = null,
        ?string $name = null,
    ): Route {
        $route = Router::get($uri, [WizardController::class, 'show'])
            ->defaults('definition', $definition);

        if (null !== $middleware) {
            $route->middleware($middleware);
        }

        if (null !== $name && '' !== $name) {
            if (Router::has($name)) {
                throw new InvalidArgumentException(sprintf('Sheetflow wizard route name [%s] is already registered.', $name));
            }

            $route->name($name);
        }

        return $route;
    }

    /**
     * Register every wizard listed in sheetflow.wizards.
     *
     * A value is either a definition key or an array with definition, and optional name and middleware.
     *
     * @throws InvalidArgumentException when an entry has no definition key, or its route name is already used
     */
    public static function registerConfiguredWizards(): void
    {
        $wizards = config('sheetflow.wizards', []);

        if (! is_array($wizards)) {
            throw new InvalidArgumentException('Sheetflow wizard routes must be an array of URI to definition.');
        }

        foreach ($wizards as $uri => $wizard) {
            if (! is_string($uri) || '' === $uri) {
                throw new InvalidArgumentException('A Sheetflow wizard route needs a URI.');
            }

            $definition = self::configuredDefinition($wizard);
            $middleware = config('sheetflow.middleware', ['web', 'auth']);
            $name = 'sheetflow.wizards.'.$definition;

            if (is_array($wizard)) {
                if (array_key_exists('middleware', $wizard)) {
                    $middleware = $wizard['middleware'];
                }

                if (isset($wizard['name']) && is_string($wizard['name']) && '' !== $wizard['name']) {
                    $name = $wizard['name'];
                }
            }

            if (! is_array($middleware) && ! is_string($middleware)) {
                throw new InvalidArgumentException(sprintf('Sheetflow wizard route [%s] has invalid middleware.', $uri));
            }

            self::wizardRoute(
                uri: $uri,
                definition: $definition,
                middleware: $middleware,
                name: $name,
            );
        }
    }

    /**
     * @throws InvalidArgumentException when the entry does not name a definition
     */
    private static function configuredDefinition(mixed $wizard): string
    {
        if (is_string($wizard) && '' !== $wizard) {
            return $wizard;
        }

        if (is_array($wizard) && isset($wizard['definition']) && is_string($wizard['definition']) && '' !== $wizard['definition']) {
            return $wizard['definition'];
        }

        throw new InvalidArgumentException('A Sheetflow wizard route needs a definition key.');
    }
}
