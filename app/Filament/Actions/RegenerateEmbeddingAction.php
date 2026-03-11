<?php

namespace App\Filament\Actions;

use App\Jobs\GenerateEmbeddingJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class RegenerateEmbeddingAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'regenerateEmbedding';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $needsGeneration = fn ($record): bool => $record instanceof Model
            && $record->getAttribute('embedding_generated_at') === null;

        $this
            ->icon(Heroicon::OutlinedCpuChip)
            ->color(fn ($record): string => $needsGeneration($record) ? 'warning' : 'gray')
            ->label(fn ($record): string => $needsGeneration($record) ? 'Generate Embedding' : 'Regenerate Embedding')
            ->requiresConfirmation()
            ->modalHeading(fn ($record): string => $needsGeneration($record) ? 'Generate Embedding' : 'Regenerate Embedding')
            ->modalDescription('This will dispatch a job to generate the embedding for this record. Continue?')
            ->action(function ($record): void {
                try {
                    GenerateEmbeddingJob::dispatch($record);

                    Notification::make()
                        ->title('Embedding job dispatched')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to dispatch embedding job')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    Log::error('RegenerateEmbeddingAction: failed to dispatch job', [
                        'model' => $record::class,
                        'id' => $record->getKey(),
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            });
    }
}
