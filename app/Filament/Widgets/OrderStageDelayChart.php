<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class OrderStageDelayChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 2;

    public function getHeading(): string // ✅ changed from protected to public
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

        $stages = [
            'طلب موافقة' => 'orderForApprove',
            'موافقة' => 'orderApproved',
            'طلب دفع' => 'orderForPayment',
            'تحصيل الدفع' => 'collectPayment',
            'موافقة البيع' => 'sellApprove',
            'إفراج' => 'releaseApprove',
            'بدء التجهيز' => 'startPreparation',
            'جاهز للتسليم' => 'readyToDeliver',
            'خرج للتسليم' => 'outForDeliver',
            'تسليم' => 'delivered',
        ];

        $orders = $query->get();
        $stageDurations = [];
        foreach ($stages as $label => $field) {
            $stageDurations[$label] = [];
        }

        foreach ($orders as $order) {
            $prev = $order->created_at;
            foreach ($stages as $label => $field) {
                $current = $order->$field;
                if ($current && $prev) {
                    $minutes = $prev->diffInMinutes($current);
                    $stageDurations[$label][] = $minutes;
                }
                if ($current) $prev = $current;
            }
        }

        $maxAvg = 0;
        $maxLabel = '';
        foreach ($stageDurations as $label => $durations) {
            $avg = count($durations) ? round(array_sum($durations) / count($durations) / 60, 1) : 0;
            if ($avg > $maxAvg) {
                $maxAvg = $avg;
                $maxLabel = $label;
            }
        }

        if ($maxAvg > 0) {
            return "متوسط وقت التأخير لكل مرحلة (أكثر مرحلة تأخيراً: {$maxLabel} - {$maxAvg} ساعة)";
        }
        return 'متوسط وقت التأخير لكل مرحلة (لا توجد بيانات كافية)';
    }

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

        $stages = [
            'طلب موافقة' => 'orderForApprove',
            'موافقة' => 'orderApproved',
            'طلب دفع' => 'orderForPayment',
            'تحصيل الدفع' => 'collectPayment',
            'موافقة البيع' => 'sellApprove',
            'إفراج' => 'releaseApprove',
            'بدء التجهيز' => 'startPreparation',
            'جاهز للتسليم' => 'readyToDeliver',
            'خرج للتسليم' => 'outForDeliver',
            'تسليم' => 'delivered',
        ];

        $orders = $query->get();
        $stageDurations = [];
        foreach ($stages as $label => $field) {
            $stageDurations[$label] = [];
        }

        foreach ($orders as $order) {
            $prev = $order->created_at;
            foreach ($stages as $label => $field) {
                $current = $order->$field;
                if ($current && $prev) {
                    $minutes = $prev->diffInMinutes($current);
                    $stageDurations[$label][] = $minutes;
                }
                if ($current) $prev = $current;
            }
        }

        $labels = [];
        $data = [];
        $maxAvg = 0;
        foreach ($stageDurations as $label => $durations) {
            $avg = count($durations) ? round(array_sum($durations) / count($durations) / 60, 1) : 0;
            $labels[] = $label;
            $data[] = $avg;
            if ($avg > $maxAvg) $maxAvg = $avg;
        }

        return [
            'datasets' => [
                [
                    'label' => 'متوسط الوقت (ساعات)',
                    'data' => $data,
                    'backgroundColor' => array_map(function ($value) use ($maxAvg) {
                        return $value == $maxAvg ? 'rgba(255, 99, 132, 0.7)' : 'rgba(54, 162, 235, 0.5)';
                    }, $data),
                    'borderColor' => array_map(function ($value) use ($maxAvg) {
                        return $value == $maxAvg ? 'rgb(255, 99, 132)' : 'rgb(54, 162, 235)';
                    }, $data),
                    'borderWidth' => 2,
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