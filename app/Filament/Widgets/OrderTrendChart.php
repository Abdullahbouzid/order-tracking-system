<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderTrendChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?string $heading = 'اتجاه الطلبات بمرور الوقت';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $query = Order::query()->forUser(Auth::id());

        if (!empty($this->filters['status'])) {
            $query->where('currentStatus', $this->filters['status']);
        }

        // تحديد نطاق التاريخ
        $start = $this->filters['date_from'] ?? now()->subDays(30);
        $end = $this->filters['date_to'] ?? now();

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        $data = $query->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = $data->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m/Y'));
        $counts = $data->pluck('total');

        return [
            'datasets' => [
                [
                    'label' => 'الطلبات المنشأة',
                    'data' => $counts,
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249,115,22,0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}