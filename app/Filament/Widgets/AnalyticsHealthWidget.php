<?php

namespace App\Filament\Widgets;

use App\Support\AnalyticsHealth;
use Filament\Widgets\Widget;

class AnalyticsHealthWidget extends Widget
{
    protected static string $view = 'filament.widgets.analytics-health';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['problems' => AnalyticsHealth::check()];
    }
}
