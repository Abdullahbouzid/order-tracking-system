<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderStatsOverview extends BaseWidget
{
    public ?array $filters = [];

    protected function getStats(): array
    {
        $query = Order::query()->forUser(Auth::id());

        // تطبيق الفلاتر (الحالة، نطاق التاريخ)
        if (!empty($this->filters['status'])) {
            $query->where('currentStatus', $this->filters['status']);
        }
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        // إجمالي الطلبات
        $totalOrders = $query->count();

        // إجمالي الإيرادات
        $totalRevenue = $query->sum('total');

        // متوسط وقت التسليم (للمكتملة فقط)
        $avgDeliveryTime = $query->where('orderStatus', 'completed')
            ->whereNotNull('delivered')
            ->whereRaw('delivered > created_at')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered)) as avg_time'))
            ->value('avg_time');
        $avgDeliveryTimeFormatted = $avgDeliveryTime ? round($avgDeliveryTime / 60, 1) . ' ساعة' : 'غير متوفر';

        // ✅ عدد الطلبات قيد التنفيذ (WHERE orderStatus = 'in_progress')
        // يتم تطبيق نفس الفلاتر (التاريخ، الحالة، المستخدم) تلقائياً لأن $query يحملها
   $inProgressOrders = Order::query()->forUser(Auth::id())
    ->where('orderStatus', 'in_progress')
    ->count();
        $stats = [
            Stat::make('إجمالي الطلبات', $totalOrders)
                ->description('جميع الطلبات')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),
            Stat::make('إجمالي الإيرادات', number_format($totalRevenue, 2) . ' $')
                ->description('مجموع أسعار الطلبات')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('متوسط وقت التسليم', $avgDeliveryTimeFormatted)
                ->description('للطلبات المكتملة فقط (من الإنشاء إلى التسليم)')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('الطلبات قيد التنفيذ', $inProgressOrders)
                ->description('حالة الطلبية = قيد التنفيذ (in_progress)')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('danger'),
        ];

        return $stats;
    }

    protected function getStageAverages($baseQuery): array
    {
        // جلب الطلبات المكتملة فقط مع احترام الفلاتر
        $orders = (clone $baseQuery)
            ->where('orderStatus', 'completed')
            ->get();

        $stages = [
            'طلب موافقة' => ['start' => 'created_at', 'end' => 'orderForApprove'],
            'موافقة' => ['start' => 'orderForApprove', 'end' => 'orderApproved'],
            'طلب دفع' => ['start' => 'orderApproved', 'end' => 'orderForPayment'],
            'تحصيل الدفع' => ['start' => 'orderForPayment', 'end' => 'collectPayment'],
            'موافقة البيع' => ['start' => 'collectPayment', 'end' => 'sellApprove'],
            'إفراج' => ['start' => 'sellApprove', 'end' => 'releaseApprove'],
            'بدء التجهيز' => ['start' => 'releaseApprove', 'end' => 'startPreparation'],
            'جاهز للتسليم' => ['start' => 'startPreparation', 'end' => 'readyToDeliver'],
            'خرج للتسليم' => ['start' => 'readyToDeliver', 'end' => 'outForDeliver'],
            'تم التسليم' => ['start' => 'outForDeliver', 'end' => 'delivered'],
        ];

        $averages = [];
        foreach ($stages as $label => $fields) {
            $totalMinutes = 0;
            $count = 0;
            foreach ($orders as $order) {
                $start = $order->{$fields['start']};
                $end = $order->{$fields['end']};
                if ($start && $end && $end > $start) {
                    $totalMinutes += $start->diffInMinutes($end);
                    $count++;
                }
            }
            $averages[$label] = $count > 0 ? round($totalMinutes / $count, 2) : 0;
        }
        return $averages;
    }
}