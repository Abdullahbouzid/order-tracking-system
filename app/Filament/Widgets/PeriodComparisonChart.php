<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeriodComparisonChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 1;

    public function getHeading(): string
    {
        return 'مقارنة الأداء (الفترة الحالية مقابل الفترة السابقة)';
    }

    protected function getData(): array
    {
        $currentStart = $this->filters['date_from'] ?? now()->startOfMonth();
        $currentEnd = $this->filters['date_to'] ?? now()->endOfMonth();

        $comparisonType = $this->filters['comparison_period'] ?? 'none';

        // حساب الفترة المقارنة
        $comparisonStart = null;
        $comparisonEnd = null;
        if ($comparisonType === 'previous_period') {
            $duration = \Carbon\Carbon::parse($currentStart)->diffInDays(\Carbon\Carbon::parse($currentEnd));
            $comparisonStart = \Carbon\Carbon::parse($currentStart)->subDays($duration + 1)->startOfDay();
            $comparisonEnd = \Carbon\Carbon::parse($currentStart)->subDay()->endOfDay();
        } elseif ($comparisonType === 'previous_year') {
            $comparisonStart = \Carbon\Carbon::parse($currentStart)->subYear();
            $comparisonEnd = \Carbon\Carbon::parse($currentEnd)->subYear();
        }

        // بيانات الفترة الحالية
        $currentQuery = Order::query()->forUser(Auth::id())
            ->whereBetween('created_at', [$currentStart, $currentEnd]);
        if (!empty($this->filters['status'])) {
            $currentQuery->where('currentStatus', $this->filters['status']);
        }
        $currentTotalOrders = $currentQuery->count();
        $currentRevenue = $currentQuery->sum('total');

        $comparisonTotalOrders = 0;
        $comparisonRevenue = 0;
        if ($comparisonStart && $comparisonEnd) {
            $comparisonQuery = Order::query()->forUser(Auth::id())
                ->whereBetween('created_at', [$comparisonStart, $comparisonEnd]);
            if (!empty($this->filters['status'])) {
                $comparisonQuery->where('currentStatus', $this->filters['status']);
            }
            $comparisonTotalOrders = $comparisonQuery->count();
            $comparisonRevenue = $comparisonQuery->sum('total');
        }

        return [
            'datasets' => [
                [
                    'label' => 'الفترة الحالية',
                    'data' => [$currentTotalOrders, $currentRevenue],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.5)',
                ],
                [
                    'label' => 'الفترة المقارنة',
                    'data' => [$comparisonTotalOrders, $comparisonRevenue],
                    'backgroundColor' => 'rgba(255, 99, 132, 0.5)',
                ],
            ],
            'labels' => ['عدد الطلبات', 'الإيرادات ($)'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}