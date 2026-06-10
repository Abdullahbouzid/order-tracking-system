<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class EmployeePerformanceChart extends ChartWidget
{
    protected static ?int $sort = 5;

    public function getHeading(): string
    {
        return 'أداء الموظفين (متوسط وقت التسليم بالساعات) - جميع الطلبات المكتملة';
    }

    protected function getData(): array
    {
        // استعلام مباشر بدون أي شروط إضافية
        $data = Order::query()
            ->whereNotNull('delivered')
            ->whereNotNull('employeeName')
            ->where('employeeName', '!=', '')
            ->select(
                'employeeName',
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, orderDate, delivered)) as avg_hours')
            )
            ->groupBy('employeeName')
            ->orderBy('avg_hours', 'asc')
            ->get();

        // إذا لم تكن هناك بيانات، نعيد مصفوفة فارغة مع رسالة في العنوان
        if ($data->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'لا توجد بيانات',
                        'data' => [],
                        'backgroundColor' => '#ccc',
                    ]
                ],
                'labels' => [],
            ];
        }

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