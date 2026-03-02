<?php

namespace App\Filament\Widgets;

use App\Models\Blog;
use App\Models\Project;
use App\Models\Share;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmbeddingHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Embedding Health';

    protected static ?int $sort = 10;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            $this->buildStat('Blogs', Blog::class),
            $this->buildStat('Projects', Project::class),
            $this->buildStat('Shares', Share::class),
        ];
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function buildStat(string $label, string $modelClass): Stat
    {
        $total = $modelClass::query()->count();
        $missing = $modelClass::query()->whereNull('embedding_generated_at')->count();

        $color = match (true) {
            $missing === 0 => 'success',
            $missing <= 3 => 'warning',
            default => 'danger',
        };

        return Stat::make("{$label} Missing Embeddings", "{$missing}/{$total}")
            ->description($missing === 0 ? 'All embeddings generated' : "{$missing} missing")
            ->color($color);
    }
}
