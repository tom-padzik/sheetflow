<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

abstract class ImportDefinition
{
    /**
     * @return class-string<Model>
     */
    abstract public function model(): string;

    /**
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * Return true to allow this import, false to refuse it, or null when this definition has no extra rule.
     */
    public function authorize(?Authenticatable $user): ?bool
    {
        return null;
    }

    /**
     * Return true to offer saved maps, false to hide them, or null to follow sheetflow.save_templates.
     */
    public function saveTemplates(): ?bool
    {
        return null;
    }

    final public function handle(MappedRow $row): void
    {
        $this->beforePersist($row);
        $this->persist($row);
        $this->afterPersist($row);
    }

    protected function beforePersist(MappedRow $row): void {}

    protected function afterPersist(MappedRow $row): void {}

    protected function persist(MappedRow $row): void
    {
        $model = $this->model();
        $attributes = $row->attributes();
        $match = $this->matchAttributes($attributes);

        if ([] === $match) {
            $model::query()->create($attributes);

            return;
        }

        $model::query()->updateOrCreate($match, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function matchAttributes(array $attributes): array
    {
        $match = [];

        foreach ($this->fields() as $field) {
            if (! $field->isMatchOn()) {
                continue;
            }

            if (! array_key_exists($field->name(), $attributes)) {
                continue;
            }

            $match[$field->name()] = $attributes[$field->name()];
        }

        return $match;
    }
}
