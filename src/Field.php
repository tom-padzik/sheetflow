<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow;

use Tpadzik\Sheetflow\Enums\InvalidAction;

class Field
{
    /**
     * @param  list<string>  $rules
     * @param  list<string>  $guesses
     * @param  list<InvalidAction>  $invalidChoices
     */
    private function __construct(
        private readonly string $name,
        private array $rules = [],
        private array $guesses = [],
        private bool $matchesOn = false,
        private ?string $label = null,
        private bool $uniqueInFile = false,
        private InvalidAction $invalidAction = InvalidAction::SkipRow,
        private array $invalidChoices = [],
    ) {}

    public static function make(string $name): self
    {
        return new self(name: $name);
    }

    /**
     * @param  list<string>  $rules
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @param  list<string>  $guesses
     */
    public function guess(array $guesses): self
    {
        $this->guesses = $guesses;

        return $this;
    }

    public function matchOn(): self
    {
        $this->matchesOn = true;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function uniqueInFile(): self
    {
        $this->uniqueInFile = true;

        return $this;
    }

    /**
     * @param  list<InvalidAction>  $allowed
     */
    public function onInvalid(InvalidAction $action, array $allowed = []): self
    {
        $this->invalidAction = $action;
        $this->invalidChoices = $allowed;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function displayLabel(): string
    {
        if (null !== $this->label && '' !== $this->label) {
            return $this->label;
        }

        return str_replace('_', ' ', $this->name);
    }

    /**
     * @return list<string>
     */
    public function rulesList(): array
    {
        return $this->rules;
    }

    /**
     * @return list<string>
     */
    public function guesses(): array
    {
        return $this->guesses;
    }

    public function isMatchOn(): bool
    {
        return $this->matchesOn;
    }

    public function isRequired(): bool
    {
        return in_array('required', $this->rules, true);
    }

    public function isUniqueInFile(): bool
    {
        return $this->uniqueInFile;
    }

    public function invalidAction(): InvalidAction
    {
        $allowed = $this->allowedInvalidActions();

        if (in_array($this->invalidAction, $allowed, true)) {
            return $this->invalidAction;
        }

        return $allowed[0];
    }

    /**
     * @return list<InvalidAction>
     */
    public function allowedInvalidActions(): array
    {
        $choices = [] === $this->invalidChoices ? [$this->invalidAction] : $this->invalidChoices;

        if ($this->isRequired()) {
            $choices = array_values(array_filter(
                $choices,
                fn (InvalidAction $action): bool => InvalidAction::Null !== $action,
            ));
        }

        if ([] === $choices) {
            return [InvalidAction::SkipRow];
        }

        return array_values($choices);
    }
}
