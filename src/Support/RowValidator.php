<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Support;

use Illuminate\Support\Facades\Validator;
use Tpadzik\Sheetflow\Field;
use Tpadzik\Sheetflow\MappedRow;

class RowValidator
{
    /**
     * @param  list<Field>  $fields
     * @return array<string, list<string>>|null
     */
    public function validate(MappedRow $row, array $fields): ?array
    {
        $rules = [];

        foreach ($fields as $field) {
            $fieldRules = $field->rulesList();

            if (! $field->isRequired() && ! in_array('nullable', $fieldRules, true)) {
                array_unshift($fieldRules, 'nullable');
            }

            $rules[$field->name()] = $fieldRules;
        }

        $validator = Validator::make($row->attributes(), $rules);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->toArray();

            return $messages;
        }

        return null;
    }
}
