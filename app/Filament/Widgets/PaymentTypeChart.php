<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class PaymentTypeChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 6;
    protected static ?string $heading = 'نوع الدفع';

    protected function getData(): array
    {
        $query = Order::query()->forUser(Auth::id());

        if (!empty($this->filters['status'])) {
            $query->where('currentStatus', $this->filters['status']);
        }
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        $counts = $query->selectRaw('priceList, count(*) as total')
            ->groupBy('priceList')
            ->pluck('total', 'priceList');

        $labels = [
            'cash' => 'كاش',
            'half_half' => '50% 50%',
            'hawala' => 'حوالة',
        ];
        $data = [
            $labels['cash'] => $counts['cash'] ?? 0,
            $labels['half_half'] => $counts['half_half'] ?? 0,
            $labels['hawala'] => $counts['hawala'] ?? 0,
        ];

        return [
            'datasets' => [
                [
                    'data' => array_values($data),
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}