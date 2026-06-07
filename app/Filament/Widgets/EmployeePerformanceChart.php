<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeePerformanceChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 5;
    protected static ?string $heading = 'أداء الموظفين (متوسط وقت التسليم بالساعات)';

    public function getHeading(): string
    {
        return 'أداء الموظفين (متوسط وقت التسليم بالساعات)';
    }

    protected function getData(): array
    {
        $query = Order::query()
            ->forUser(Auth::id())
            ->whereNotNull('delivered')
            ->whereNotNull('employeeName')
            ->where('employeeName', '!=', '');

        if (!empty($this->filters['status'])) {
            $query->where('currentStatus', $this->filters['status']);
        }
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        $data = $query->select('employeeName', DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, delivered)) as avg_hours'))
            ->groupBy('employeeName')
            ->orderBy('avg_hours', 'asc')
            ->get();

        $labels = $data->pluck('employeeName')->toArray();
        $values = $data->pluck('avg_hours')->map(fn($v) => round($v, 1))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'متوسط وقت التسليم (ساعات)',
                    'data' => $values,
                    'backgroundColor' => 'rgba(75, 192, 192, 0.5)',
                    'borderColor' => 'rgb(75, 192, 192)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}