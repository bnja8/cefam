<?php

namespace App\View\Presenters;

use Illuminate\Support\Collection;

class DashboardChartPresenter
{
    private const STOCK_COLORS = ['#0b5578', '#42856b', '#d6a13a', '#bd5a5a', '#8a9aa5'];

    private const CIRCLE_CIRCUMFERENCE = 339.292;

    public function present(
        Collection $dispensationActivity,
        Collection $stockByCategory,
        Collection $mostDispensed,
        float $stockCount
    ): array {
        $activityMax = max(1, (int) $dispensationActivity->max('total'));
        $activityPointCount = $dispensationActivity->count();
        $activityPoints = $dispensationActivity->values()->map(function ($day, $index) use ($activityMax, $activityPointCount): array {
            $lastIndex = $activityPointCount - 1;
            $labelInterval = $activityPointCount > 20 ? 7 : 1;

            return [
                'x' => $activityPointCount === 1 ? 321 : 45 + ($index * 552 / $lastIndex),
                'y' => 178 - (int) round(($day['total'] / $activityMax) * 126),
                'label' => $day['label'],
                'date' => $day['date'],
                'total' => $day['total'],
                'showLabel' => $index % $labelInterval === 0 || $index === $lastIndex,
            ];
        });
        $activityLine = $activityPoints->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
        $stockCategories = $stockByCategory->sortByDesc('total')->values();
        $stockTotal = (float) $stockCategories->sum('total');
        $stockLegendCategories = $stockCategories->take(4)->values()->map(fn ($category, $index) => [
            'name' => $category->nombre,
            'total' => (float) $category->total,
            'color' => self::STOCK_COLORS[$index],
        ]);
        $otherStockTotal = (float) $stockCategories->slice(4)->sum('total');

        if ($otherStockTotal > 0) {
            $stockLegendCategories->push([
                'name' => 'Otras categorías',
                'total' => $otherStockTotal,
                'color' => self::STOCK_COLORS[4],
            ]);
        }

        $donutOffset = 0;
        $donutSlices = $stockLegendCategories->map(function ($category) use ($stockTotal, &$donutOffset): array {
            $length = $stockTotal > 0
                ? ($category['total'] / $stockTotal) * self::CIRCLE_CIRCUMFERENCE
                : 0;
            $slice = [
                ...$category,
                'length' => $length,
                'offset' => $donutOffset,
                'percentage' => $stockTotal > 0 ? round(($category['total'] / $stockTotal) * 100) : 0,
            ];
            $donutOffset += $length;

            return $slice;
        });

        return [
            'activityPoints' => $activityPoints,
            'activityLine' => $activityLine,
            'activityArea' => 'M45,178 L'.$activityLine.' L597,178 Z',
            'activityPeriodTotal' => $dispensationActivity->sum('total'),
            'stockCategories' => $stockCategories,
            'circleCircumference' => self::CIRCLE_CIRCUMFERENCE,
            'donutSlices' => $donutSlices,
            'maxCategoryStock' => max(1, (float) $stockCategories->max('total')),
            'maxDispensed' => max(1, (float) $mostDispensed->max('cantidad')),
        ];
    }
}
