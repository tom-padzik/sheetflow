<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

use Illuminate\Contracts\Container\Container;
use Tpadzik\Sheetflow\Exceptions\UnknownDefinitionException;
use Tpadzik\Sheetflow\ImportDefinition;

class DefinitionRegistry
{
    /**
     * @var array<string, class-string<ImportDefinition>>
     */
    private array $definitions = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  class-string<ImportDefinition>  $definition
     */
    public function register(string $key, string $definition): void
    {
        $this->definitions[$key] = $definition;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitions);
    }

    /**
     * @throws UnknownDefinitionException
     */
    public function resolve(string $key): ImportDefinition
    {
        if (! $this->has($key)) {
            throw UnknownDefinitionException::forKey($key);
        }

        $definition = $this->container->make($this->definitions[$key]);

        if (! $definition instanceof ImportDefinition) {
            throw UnknownDefinitionException::forKey($key);
        }

        return $definition;
    }
}
