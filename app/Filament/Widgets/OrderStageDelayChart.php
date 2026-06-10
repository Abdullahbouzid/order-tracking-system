<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class OrderStageDelayChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        $maxAvg = $this->calculateMaxAverage();
        if ($maxAvg['avg'] > 0) {
            return "متوسط وقت التأخير لكل مرحلة (أكثر مرحلة تأخيراً: {$maxAvg['label']} - {$maxAvg['avg']} ساعة)";
        }
        return 'متوسط وقت التأخير لكل مرحلة (لا توجد بيانات كافية)';
    }

    protected function getData(): array
    {
        $query = Order::query()->forUser(Auth::id())
            ->whereNotNull('delivered'); // فقط الطلبات المكتملة

        // تطبيق فلاتر التاريخ فقط (تم إزالة فلتر الحالة)
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('orderDate', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('orderDate', '<=', $this->filters['date_to']);
        }

        // تعريف المراحل بالترتيب المنطقي
        $stages = [
            'طلب موافقة' => 'orderForApprove',
            'موافقة'      => 'orderApproved',
            'طلب دفع'     => 'orderForPayment',
            'تحصيل الدفع' => 'collectPayment',
            'موافقة البيع' => 'sellApprove',
            'إفراج'        => 'releaseApprove',
            'بدء التجهيز'  => 'startPreparation',
            'جاهز للتسليم' => 'readyToDeliver',
            'خرج للتسليم'  => 'outForDeliver',
            'تسليم'        => 'delivered',
        ];

        // جلب الحقول المطلوبة فقط
        $orders = $query->get(array_merge(['orderDate'], array_values($stages)));

        // تهيئة مصفوفة لتجميع فترات كل مرحلة
        $stageDurations = [];
        foreach ($stages as $label => $field) {
            $stageDurations[$label] = [];
        }

        foreach ($orders as $order) {
            $prev = $order->orderDate;
            foreach ($stages as $label => $field) {
                $current = $order->$field;

                // التحقق من صحة التسلسل: المرحلة الحالية موجودة وتأتي بعد المرحلة السابقة
                if ($current && $prev && $current->greaterThan($prev)) {
                    $minutes = $prev->diffInMinutes($current);
                    // تجاهل الفروق الكبيرة جداً (أكثر من 30 يوماً) لأنها غالباً أخطاء
                    if ($minutes <= 43200) {
                        $stageDurations[$label][] = $minutes;
                    }
                    // تحديث المرحلة السابقة فقط إذا كان التسلسل صحيحاً
                    $prev = $current;
                } elseif ($current) {
                    // إذا كانت المرحلة الحالية موجودة لكنها لا تأتي بعد السابقة (أي التسلسل خاطئ)
                    // لا نضيف الفترة إلى الإحصائيات، ونبقي $prev كما هو لعدم تكسير التسلسل
                    // أو يمكن تحديثه حسب متطلبات العمل، لكن الأفضل عدم تحديثه.
                    // هنا نقرر عدم تحديث $prev لأن الطلب ربما يكون فاسد الترتيب.
                    // يمكنك أيضاً تسجيل تحذير أو تخطي الطلب بالكامل إذا أردت.
                }
            }
        }

        // حساب المتوسطات وتحويلها إلى ساعات
        $labels = [];
        $data = [];
        foreach ($stageDurations as $label => $durations) {
            if (count($durations) > 0) {
                $avgMinutes = array_sum($durations) / count($durations);
                $avgHours = round($avgMinutes / 60, 1);
                $labels[] = $label;
                $data[] = $avgHours;
            } else {
                $labels[] = $label;
                $data[] = 0;
            }
        }

        // إيجاد أعلى متوسط لتمييزه بالأحمر
        $maxAvg = !empty($data) ? max($data) : 0;

        return [
            'datasets' => [
                [
                    'label' => 'متوسط الوقت (ساعات)',
                    'data' => $data,
                    'backgroundColor' => array_map(function ($value) use ($maxAvg) {
                        return ($value == $maxAvg && $maxAvg > 0) ? 'rgba(255, 99, 132, 0.7)' : 'rgba(54, 162, 235, 0.5)';
                    }, $data),
                    'borderColor' => array_map(function ($value) use ($maxAvg) {
                        return ($value == $maxAvg && $maxAvg > 0) ? 'rgb(255, 99, 132)' : 'rgb(54, 162, 235)';
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

    /**
     * حساب أعلى متوسط للاستخدام في العنوان
     */
    private function calculateMaxAverage(): array
    {
        $query = Order::query()->forUser(Auth::id())
            ->whereNotNull('delivered');

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('orderDate', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('orderDate', '<=', $this->filters['date_to']);
        }

        $stages = [
            'طلب موافقة' => 'orderForApprove',
            'موافقة'      => 'orderApproved',
            'طلب دفع'     => 'orderForPayment',
            'تحصيل الدفع' => 'collectPayment',
            'موافقة البيع' => 'sellApprove',
            'إفراج'        => 'releaseApprove',
            'بدء التجهيز'  => 'startPreparation',
            'جاهز للتسليم' => 'readyToDeliver',
            'خرج للتسليم'  => 'outForDeliver',
            'تسليم'        => 'delivered',
        ];

        $orders = $query->get(array_merge(['orderDate'], array_values($stages)));
        $stageDurations = [];
        foreach ($stages as $label => $field) {
            $stageDurations[$label] = [];
        }

        foreach ($orders as $order) {
            $prev = $order->orderDate;
            foreach ($stages as $label => $field) {
                $current = $order->$field;
                if ($current && $prev && $current->greaterThan($prev)) {
                    $minutes = $prev->diffInMinutes($current);
                    if ($minutes <= 43200) {
                        $stageDurations[$label][] = $minutes;
                    }
                    $prev = $current;
                }
                // إذا كان التسلسل غير صحيح، لا نقوم بتحديث $prev
            }
        }

        $maxAvg = 0;
        $maxLabel = '';
        foreach ($stageDurations as $label => $durations) {
            if (count($durations) > 0) {
                $avg = round((array_sum($durations) / count($durations)) / 60, 1);
                if ($avg > $maxAvg) {
                    $maxAvg = $avg;
                    $maxLabel = $label;
                }
            }
        }

        return ['avg' => $maxAvg, 'label' => $maxLabel];
    }
}