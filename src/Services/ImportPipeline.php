<?php

declare(strict_types=1);

namespace Tpadzik\Sheetflow\Services;

use Illuminate\Bus\Batch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Exception\OpenSpoutException;
use Throwable;
use Tpadzik\Sheetflow\Contracts\SpreadsheetReaderInterface;
use Tpadzik\Sheetflow\Enums\RowStatus;
use Tpadzik\Sheetflow\Enums\RunStatus;
use Tpadzik\Sheetflow\Exceptions\InvalidSpreadsheetException;
use Tpadzik\Sheetflow\Field;
use Tpadzik\Sheetflow\ImportDefinition;
use Tpadzik\Sheetflow\Jobs\ImportRowsChunk;
use Tpadzik\Sheetflow\Jobs\StageImportRows;
use Tpadzik\Sheetflow\Jobs\ValidateImportChunk;
use Tpadzik\Sheetflow\Models\ImportFailure;
use Tpadzik\Sheetflow\Models\ImportRow;
use Tpadzik\Sheetflow\Models\ImportRun;
use Tpadzik\Sheetflow\Models\MappingTemplate;
use Tpadzik\Sheetflow\Sheetflow;
use Tpadzik\Sheetflow\Support\ColumnGuesser;
use Tpadzik\Sheetflow\Support\DefinitionRegistry;
use Tpadzik\Sheetflow\Support\HeaderSignature;
use Tpadzik\Sheetflow\Support\RowMapper;
use Tpadzik\Sheetflow\Support\RowValidator;

class ImportPipeline
{
    public function __construct(
        private readonly DefinitionRegistry $definitions,
        private readonly SpreadsheetReaderInterface $reader,
        private readonly ColumnGuesser $guesser,
        private readonly HeaderSignature $signatures,
        private readonly RowMapper $mapper,
        private readonly RowValidator $validator,
    ) {}

    /**
     * @throws ValidationException
     */
    public function start(string $definitionKey, UploadedFile $file, ?string $sheet, ?string $delimiter): ImportRun
    {
        $definition = $this->definitions->resolve($definitionKey);
        $format = strtolower($file->getClientOriginalExtension());
        $disk = (string) config('sheetflow.disk');
        $storedName = Str::uuid()->toString().'.'.$format;
        $path = $file->storeAs((string) config('sheetflow.directory'), $storedName, $disk);

        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'file' => __('sheetflow::messages.errors.file_not_stored'),
            ]);
        }

        $absolutePath = Storage::disk($disk)->path($path);

        try {
            $preview = $this->reader->inspect(
                path: $absolutePath,
                format: $format,
                delimiter: $delimiter,
                sheet: $sheet,
            );
        } catch (InvalidSpreadsheetException|OpenSpoutException $exception) {
            Storage::disk($disk)->delete($path);

            throw ValidationException::withMessages([
                'file' => $this->readError(
                    filename: $this->sourceFilename($file->getClientOriginalName()),
                    exception: $exception,
                    storedPath: $absolutePath,
                ),
            ]);
        }

        $columnMap = $this->guesser->guess(
            fields: $definition->fields(),
            headers: $preview->headers,
        );

        $run = ImportRun::query()->create([
            'definition_key' => $definitionKey,
            'status' => RunStatus::staging,
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'format' => $format,
            'delimiter' => $preview->delimiter,
            'sheet' => $preview->sheet,
            'header_signature' => $this->signatures->make($preview->headers),
            'headers' => $preview->headers,
            'sheet_names' => $preview->sheetNames,
            'preview_rows' => $preview->previewRows,
            'column_map' => $columnMap,
            'user_id' => auth()->id(),
        ]);

        $this->dispatch(new StageImportRows(runId: $run->id));

        return $run->refresh();
    }

    public function stage(string $runId): void
    {
        $run = ImportRun::query()->find($runId);

        if (null === $run || RunStatus::cancelled === $run->status) {
            return;
        }

        $run->rows()->delete();
        $run->failures()->delete();

        $absolutePath = Storage::disk($run->disk)->path($run->path);
        $buffer = [];
        $now = now();
        $batchSize = (int) config('sheetflow.stage_batch_size', 500);
        $maxRows = (int) config('sheetflow.max_rows', 100000);
        $count = 0;

        foreach ($this->reader->rows(
            path: $absolutePath,
            format: $run->format,
            delimiter: $run->delimiter,
            sheet: $run->sheet,
        ) as $rowNumber => $values) {
            $count++;

            if ($maxRows > 0 && $count > $maxRows) {
                $run->rows()->delete();
                $run->forceFill([
                    'status' => RunStatus::failed,
                    'total_rows' => 0,
                ])->save();

                return;
            }

            $buffer[] = [
                'run_id' => $run->id,
                'row_number' => $rowNumber,
                'values' => json_encode($values, JSON_THROW_ON_ERROR),
                'status' => RowStatus::pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($buffer) >= $batchSize) {
                ImportRow::query()->insert($buffer);
                $buffer = [];
                $run->refresh();

                if (RunStatus::cancelled === $run->status) {
                    return;
                }
            }
        }

        if ([] !== $buffer) {
            ImportRow::query()->insert($buffer);
        }

        $run->refresh();

        if (RunStatus::cancelled === $run->status) {
            return;
        }

        $run->forceFill([
            'status' => RunStatus::staged,
            'total_rows' => $count,
            'processed_rows' => $count,
            'staged_at' => now(),
        ])->save();
    }

    /**
     * @throws ValidationException
     */
    public function restage(ImportRun $run, string $sheet): ImportRun
    {
        $this->assertStatus($run, RunStatus::staged, RunStatus::validated, RunStatus::failed);

        $absolutePath = Storage::disk($run->disk)->path($run->path);

        try {
            $preview = $this->reader->inspect(
                path: $absolutePath,
                format: $run->format,
                delimiter: $run->delimiter,
                sheet: $sheet,
            );
        } catch (InvalidSpreadsheetException|OpenSpoutException $exception) {
            throw ValidationException::withMessages([
                'sheet' => $this->readError(
                    filename: $this->sourceFilename($run->original_filename),
                    exception: $exception,
                    storedPath: $absolutePath,
                ),
            ]);
        }

        $definition = $this->definitions->resolve($run->definition_key);
        $run->rows()->delete();
        $run->failures()->delete();
        $this->forgetBatch($run);

        $run->forceFill([
            'status' => RunStatus::staging,
            'sheet' => $preview->sheet,
            'headers' => $preview->headers,
            'preview_rows' => $preview->previewRows,
            'header_signature' => $this->signatures->make($preview->headers),
            'column_map' => $this->guesser->guess(
                fields: $definition->fields(),
                headers: $preview->headers,
            ),
            'total_rows' => 0,
            'processed_rows' => 0,
            'valid_rows' => 0,
            'failed_rows' => 0,
            'imported_rows' => 0,
            'validated_at' => null,
            'completed_at' => null,
        ])->save();

        $this->dispatch(new StageImportRows(runId: $run->id));

        return $run->refresh();
    }

    /**
     * @param  array<string, string|null>  $columnMap
     *
     * @throws ValidationException
     */
    public function saveMapping(ImportRun $run, array $columnMap, bool $saveTemplate, ?string $templateName): ImportRun
    {
        $this->assertStatus($run, RunStatus::staged, RunStatus::validated);
        $definition = $this->definitions->resolve($run->definition_key);
        $normalized = $this->normalizeMap(
            definition: $definition,
            headers: $run->headers ?? [],
            columnMap: $columnMap,
        );

        if (RunStatus::validated === $run->status) {
            $run->rows()->update(['status' => RowStatus::pending->value]);
            $run->failures()->delete();
        }

        $run->forceFill([
            'column_map' => $normalized,
            'status' => RunStatus::staged,
            'valid_rows' => 0,
            'failed_rows' => 0,
            'imported_rows' => 0,
            'processed_rows' => 0,
            'validated_at' => null,
        ])->save();

        if ($saveTemplate && Sheetflow::savesTemplates(definition: $definition) && is_string($templateName) && '' !== $templateName) {
            $this->storeTemplate(
                run: $run,
                name: $templateName,
                columnMap: $normalized,
            );
        }

        return $run->refresh();
    }

    public function startValidation(ImportRun $run): ImportRun
    {
        $this->assertStatus($run, RunStatus::staged);
        $definition = $this->definitions->resolve($run->definition_key);
        $this->assertRequiredFieldsMapped(
            fields: $definition->fields(),
            columnMap: $run->column_map ?? [],
        );

        $run->failures()->delete();
        $run->rows()->update(['status' => RowStatus::pending->value]);
        $run->forceFill([
            'status' => RunStatus::validating,
            'processed_rows' => 0,
            'valid_rows' => 0,
            'failed_rows' => 0,
        ])->save();

        $this->dispatchChunks(
            run: $run,
            status: RowStatus::pending,
            jobClass: ValidateImportChunk::class,
            name: 'sheetflow-validate-'.$run->id,
            onFinish: 'finishValidation',
        );

        return $run->refresh();
    }

    /**
     * @param  list<int>  $rowIds
     */
    public function validateChunk(string $runId, array $rowIds): void
    {
        $run = $this->activeRun($runId);

        if (null === $run) {
            return;
        }

        $definition = $this->definitions->resolve($run->definition_key);
        $rows = ImportRow::query()
            ->where('run_id', $run->id)
            ->whereIn('id', $rowIds)
            ->orderBy('row_number')
            ->get();

        foreach ($rows as $row) {
            /** @var array<string, string|null> $raw */
            $raw = $row->values ?? [];
            $mapped = $this->mapper->map(
                rowNumber: $row->row_number,
                raw: $raw,
                fields: $definition->fields(),
                columnMap: $run->column_map ?? [],
            );
            $errors = $this->validator->validate(
                row: $mapped,
                fields: $definition->fields(),
            );

            if (null === $errors) {
                $row->forceFill(['status' => RowStatus::valid])->save();
                $run->increment('valid_rows');
            } else {
                $row->forceFill(['status' => RowStatus::invalid])->save();
                ImportFailure::query()->create([
                    'run_id' => $run->id,
                    'row_number' => $row->row_number,
                    'values' => $raw,
                    'errors' => $errors,
                ]);
                $run->increment('failed_rows');
            }

            $run->increment('processed_rows');
        }
    }

    public function finishValidation(string $runId): void
    {
        $run = ImportRun::query()->find($runId);

        if (null === $run || RunStatus::cancelled === $run->status || RunStatus::failed === $run->status) {
            return;
        }

        $valid = $run->rows()->where('status', RowStatus::valid)->count();
        $invalid = $run->rows()->where('status', RowStatus::invalid)->count();

        $run->forceFill([
            'status' => RunStatus::validated,
            'valid_rows' => $valid,
            'failed_rows' => $invalid,
            'processed_rows' => $valid + $invalid,
            'validated_at' => now(),
            'batch_id' => null,
        ])->save();
    }

    /**
     * @throws ValidationException
     */
    public function startImport(ImportRun $run): ImportRun
    {
        $this->assertStatus($run, RunStatus::validated);

        if (0 === (int) $run->valid_rows) {
            throw ValidationException::withMessages([
                'import' => 'There are no valid rows to import.',
            ]);
        }

        $run->forceFill([
            'status' => RunStatus::importing,
            'processed_rows' => 0,
            'imported_rows' => 0,
        ])->save();

        $this->dispatchChunks(
            run: $run,
            status: RowStatus::valid,
            jobClass: ImportRowsChunk::class,
            name: 'sheetflow-import-'.$run->id,
            onFinish: 'finishImport',
        );

        return $run->refresh();
    }

    /**
     * @param  list<int>  $rowIds
     */
    public function importChunk(string $runId, array $rowIds): void
    {
        $run = $this->activeRun($runId);

        if (null === $run) {
            return;
        }

        $definition = $this->definitions->resolve($run->definition_key);
        $rows = ImportRow::query()
            ->where('run_id', $run->id)
            ->whereIn('id', $rowIds)
            ->orderBy('row_number')
            ->get();

        foreach ($rows as $row) {
            /** @var array<string, string|null> $raw */
            $raw = $row->values ?? [];
            $mapped = $this->mapper->map(
                rowNumber: $row->row_number,
                raw: $raw,
                fields: $definition->fields(),
                columnMap: $run->column_map ?? [],
            );
            $errors = $this->validator->validate(
                row: $mapped,
                fields: $definition->fields(),
            );

            if (null !== $errors) {
                $row->forceFill(['status' => RowStatus::invalid])->save();
                ImportFailure::query()->create([
                    'run_id' => $run->id,
                    'row_number' => $row->row_number,
                    'values' => $raw,
                    'errors' => $errors,
                ]);
                $run->increment('failed_rows');
                $run->increment('processed_rows');

                continue;
            }

            try {
                $definition->handle($mapped);
            } catch (Throwable $exception) {
                $row->forceFill(['status' => RowStatus::invalid])->save();
                ImportFailure::query()->create([
                    'run_id' => $run->id,
                    'row_number' => $row->row_number,
                    'values' => $raw,
                    'errors' => ['import' => [$exception->getMessage()]],
                ]);
                $run->increment('failed_rows');
                $run->increment('processed_rows');

                continue;
            }

            $row->forceFill(['status' => RowStatus::imported])->save();
            $run->increment('imported_rows');
            $run->increment('processed_rows');
        }
    }

    public function finishImport(string $runId): void
    {
        $run = ImportRun::query()->find($runId);

        if (null === $run || RunStatus::cancelled === $run->status || RunStatus::failed === $run->status) {
            return;
        }

        $imported = $run->rows()->where('status', RowStatus::imported)->count();
        $processed = $run->rows()->where('status', '!=', RowStatus::pending)->count();

        $run->forceFill([
            'status' => RunStatus::completed,
            'imported_rows' => $imported,
            'processed_rows' => $processed,
            'completed_at' => now(),
            'batch_id' => null,
        ])->save();
    }

    public function markFailed(string $runId): void
    {
        $run = ImportRun::query()->find($runId);

        if (null === $run || RunStatus::cancelled === $run->status) {
            return;
        }

        $run->forceFill([
            'status' => RunStatus::failed,
            'batch_id' => null,
        ])->save();
    }

    public function cancel(ImportRun $run): ImportRun
    {
        $this->assertStatus(
            $run,
            RunStatus::staging,
            RunStatus::validating,
            RunStatus::importing,
        );

        $batchId = $run->batch_id;
        $run->forceFill([
            'status' => RunStatus::cancelled,
            'cancelled_at' => now(),
        ])->save();

        if (is_string($batchId) && '' !== $batchId) {
            Bus::findBatch($batchId)?->cancel();
        }

        return $run->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ImportRun $run): array
    {
        $definition = $this->definitions->resolve($run->definition_key);
        $saveTemplates = Sheetflow::savesTemplates(definition: $definition);
        $total = (int) $run->total_rows;
        $processed = (int) $run->processed_rows;

        return [
            'id' => $run->id,
            'definition' => $run->definition_key,
            'status' => $run->status->value,
            'filename' => $run->original_filename,
            'format' => $run->format,
            'delimiter' => $run->delimiter,
            'sheet' => $run->sheet,
            'headers' => $run->headers ?? [],
            'sheet_names' => $run->sheet_names ?? [],
            'preview_rows' => $run->preview_rows ?? [],
            'column_map' => $run->column_map ?? [],
            'fields' => array_map(
                fn (Field $field): array => [
                    'name' => $field->name(),
                    'label' => $field->displayLabel(),
                    'required' => $field->isRequired(),
                ],
                $definition->fields(),
            ),
            'save_templates' => $saveTemplates,
            'templates' => $saveTemplates ? $this->templatesFor($run) : [],
            'total_rows' => $total,
            'processed_rows' => $processed,
            'valid_rows' => (int) $run->valid_rows,
            'failed_rows' => (int) $run->failed_rows,
            'imported_rows' => (int) $run->imported_rows,
            'percent' => $total > 0 ? (int) floor(($processed / $total) * 100) : 0,
        ];
    }

    /**
     * @param  class-string  $jobClass
     */
    private function dispatchChunks(ImportRun $run, RowStatus $status, string $jobClass, string $name, string $onFinish): void
    {
        $chunkSize = max(1, (int) config('sheetflow.chunk_size', 100));
        $jobs = [];

        $run->rows()
            ->where('status', $status)
            ->orderBy('id')
            ->select('id')
            ->chunkById($chunkSize, function ($rows) use (&$jobs, $jobClass, $run): void {
                $ids = $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
                $jobs[] = new $jobClass(runId: $run->id, rowIds: $ids);
            });

        if ([] === $jobs) {
            if ('finishValidation' === $onFinish) {
                $this->finishValidation($run->id);
            }

            if ('finishImport' === $onFinish) {
                $this->finishImport($run->id);
            }

            return;
        }

        $runId = $run->id;
        $pending = Bus::batch($jobs)
            ->name($name)
            ->catch(function (Batch $batch, Throwable $exception) use ($runId): void {
                app(self::class)->markFailed($runId);
            })
            ->finally(function (Batch $batch) use ($runId, $onFinish): void {
                $pipeline = app(self::class);

                if ('finishValidation' === $onFinish) {
                    $pipeline->finishValidation($runId);

                    return;
                }

                $pipeline->finishImport($runId);
            });

        $connection = config('sheetflow.connection');
        $queue = config('sheetflow.queue');

        if (is_string($connection) && '' !== $connection) {
            $pending->onConnection($connection);
        }

        if (is_string($queue) && '' !== $queue) {
            $pending->onQueue($queue);
        }

        $batch = $pending->dispatch();
        $run->refresh();

        if (RunStatus::validating === $run->status || RunStatus::importing === $run->status) {
            $run->forceFill(['batch_id' => $batch->id])->save();
        }
    }

    private function dispatch(object $job): void
    {
        $pending = dispatch($job);
        $connection = config('sheetflow.connection');
        $queue = config('sheetflow.queue');

        if (is_string($connection) && '' !== $connection) {
            $pending->onConnection($connection);
        }

        if (is_string($queue) && '' !== $queue) {
            $pending->onQueue($queue);
        }
    }

    private function activeRun(string $runId): ?ImportRun
    {
        $run = ImportRun::query()->find($runId);

        if (null === $run || RunStatus::cancelled === $run->status) {
            return null;
        }

        return $run;
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, string|null>  $columnMap
     *
     * @throws ValidationException
     */
    private function assertRequiredFieldsMapped(array $fields, array $columnMap): void
    {
        $missing = [];

        foreach ($fields as $field) {
            if (! $field->isRequired()) {
                continue;
            }

            $header = $columnMap[$field->name()] ?? null;

            if (! is_string($header) || '' === $header) {
                $missing[$field->name()] = sprintf('Map a column to %s.', $field->displayLabel());
            }
        }

        if ([] !== $missing) {
            throw ValidationException::withMessages($missing);
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, string|null>  $columnMap
     * @return array<string, string|null>
     *
     * @throws ValidationException
     */
    private function normalizeMap(ImportDefinition $definition, array $headers, array $columnMap): array
    {
        $known = [];

        foreach ($definition->fields() as $field) {
            $known[$field->name()] = true;
        }

        $normalized = [];
        $errors = [];

        foreach ($columnMap as $fieldName => $header) {
            if (! is_string($fieldName) || ! array_key_exists($fieldName, $known)) {
                $errors['column_map'] = __('sheetflow::messages.errors.unknown_field');

                continue;
            }

            if (null === $header || '' === $header) {
                $normalized[$fieldName] = null;

                continue;
            }

            if (! in_array($header, $headers, true)) {
                $errors[$fieldName] = __('sheetflow::messages.errors.missing_column', [
                    'column' => $header,
                ]);

                continue;
            }

            $normalized[$fieldName] = $header;
        }

        foreach (array_keys($known) as $fieldName) {
            if (! array_key_exists($fieldName, $normalized)) {
                $normalized[$fieldName] = null;
            }
        }

        if ([] !== $errors) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * @param  array<string, string|null>  $columnMap
     */
    private function storeTemplate(ImportRun $run, string $name, array $columnMap): void
    {
        $query = MappingTemplate::query()
            ->where('definition_key', $run->definition_key)
            ->where('name', $name);

        if (null === $run->user_id) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $run->user_id);
        }

        $template = $query->first() ?? new MappingTemplate;

        $template->fill([
            'definition_key' => $run->definition_key,
            'name' => $name,
            'header_signature' => $run->header_signature,
            'column_map' => $columnMap,
            'user_id' => $run->user_id,
        ])->save();
    }

    /**
     * @return list<array{id: int, name: string, column_map: array<string, string|null>}>
     */
    private function templatesFor(ImportRun $run): array
    {
        $query = MappingTemplate::query()
            ->where('definition_key', $run->definition_key)
            ->where('header_signature', $run->header_signature);

        if (null === $run->user_id) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $run->user_id);
        }

        return $query->orderBy('name')->get()->map(function (MappingTemplate $template): array {
            /** @var array<string, string|null> $map */
            $map = $template->column_map ?? [];

            return [
                'id' => (int) $template->id,
                'name' => $template->name,
                'column_map' => $map,
            ];
        })->all();
    }

    private function forgetBatch(ImportRun $run): void
    {
        if (! is_string($run->batch_id) || '' === $run->batch_id) {
            return;
        }

        Bus::findBatch($run->batch_id)?->cancel();
        $run->forceFill(['batch_id' => null])->save();
    }

    private function sourceFilename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));

        if ('' === $base || '.' === $base || '..' === $base) {
            return 'file';
        }

        return $base;
    }

    private function readError(string $filename, Throwable $exception, string $storedPath): string
    {
        $reason = $exception->getMessage();

        if ('' !== $storedPath) {
            $reason = str_replace($storedPath, $filename, $reason);
        }

        if (1 === preg_match('/for reading: (.+)\.$/', $reason, $matches)) {
            $reason = $matches[1];
        }

        return __('sheetflow::messages.errors.unreadable_file', [
            'filename' => $filename,
            'reason' => $reason,
        ]);
    }

    private function assertStatus(ImportRun $run, RunStatus ...$allowed): void
    {
        foreach ($allowed as $status) {
            if ($status === $run->status) {
                return;
            }
        }

        abort(409, __('sheetflow::messages.errors.invalid_status', [
            'status' => __('sheetflow::messages.statuses.'.$run->status->value),
        ]));
    }
}
