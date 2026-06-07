<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderStatusChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?string $heading = 'توزيع الطلبات حسب الحالة';
    protected static ?int $sort = 3;

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

        $statusCounts = $query->select('currentStatus', DB::raw('count(*) as total'))
            ->groupBy('currentStatus')
            ->pluck('total', 'currentStatus');

        $labels = [
            'pending' => 'قيد الانتظار',
            'orderForApprove' => 'طلب موافقة',
            'orderApproved' => 'تمت الموافقة',
            'orderForPayment' => 'طلب دفع',
            'collectPayment' => 'تم تحصيل الدفع',
            'sellApprove' => 'موافقة البيع',
            'releaseApprove' => 'موافقة الإفراج',
            'startPreparation' => 'بدء التجهيز',
            'readyToDeliver' => 'جاهز للتسليم',
            'outForDeliver' => 'خرج للتسليم',
            'delivered' => 'تم التسليم',
        ];

        $chartLabels = [];
        $data = [];
        foreach ($labels as $key => $label) {
            $chartLabels[] = $label;
            $data[] = $statusCounts[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'عدد الطلبات',
                    'data' => $data,
                    'backgroundColor' => [
                        '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6',
                        '#ec489a', '#14b8a6', '#f97316', '#06b6d4', '#6366f1', '#22c55e'
                    ],
                ],
            ],
            'labels' => $chartLabels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}