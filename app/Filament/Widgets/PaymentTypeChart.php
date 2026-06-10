<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class PaymentTypeChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 6;
    protected static ?string $heading = 'توزيع الطلبات حسب نوع الدفع';

    protected function getData(): array
    {
        $query = Order::query()->forUser(Auth::id());

        // تطبيق الفلاتر
        if (!empty($this->filters['status'])) {
            $query->where('currentStatus', $this->filters['status']);
        }
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        // حساب عدد الطلبات لكل نوع دفع من حقل priceList (تجاهل القيم الفارغة)
        $counts = $query->whereNotNull('priceList')
            ->selectRaw('priceList, count(*) as total')
            ->groupBy('priceList')
            ->pluck('total', 'priceList')
            ->toArray();

        // ترجمة القيم المخزنة في priceList إلى أسماء عربية للعرض
        $mapping = [
            'cash'      => 'كاش',
            'hawala'    => 'حوالة',
            'half_half' => '50% 50%',
        ];

        $labels = [];
        $data = [];

        // ترتيب محدد: كاش، نصف نصف، حوالة (حسب رغبتك)
        $order = ['cash', 'half_half', 'hawala'];
        foreach ($order as $key) {
            if (isset($counts[$key]) && $counts[$key] > 0) {
                $labels[] = $mapping[$key] ?? $key; // في حالة وجود مفتاح غير متوقع
                $data[] = $counts[$key];
            }
        }

        // إضافة أي أنواع دفع أخرى غير متوقعة (إذا وجدت)
        foreach ($counts as $key => $count) {
            if (!in_array($key, $order) && $count > 0) {
                $labels[] = $key; // يمكن ترجمتها حسب الحاجة
                $data[] = $count;
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6'],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}