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

        $totalOrders = $query->count();
        $totalRevenue = $query->sum('total');
        $avgTotalTime = $query->whereNotNull('delivered')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered)) as avg_time'))
            ->value('avg_time');
        $avgTotalTimeFormatted = $avgTotalTime ? round($avgTotalTime / 60, 1) . ' ساعة' : 'غير متوفر';

        $pendingOrders = $query->whereNotIn('currentStatus', ['delivered'])->count();

        return [
            Stat::make('إجمالي الطلبات', $totalOrders)
                ->description('جميع الطلبات')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),
            Stat::make('إجمالي الإيرادات', number_format($totalRevenue, 2) . ' $')
                ->description('مجموع أسعار الطلبات')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('متوسط وقت التسليم', $avgTotalTimeFormatted)
                ->description('من الإنشاء إلى التسليم')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('الطلبات قيد التنفيذ', $pendingOrders)
                ->description('لم تسلم بعد')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('danger'),
        ];
    }
}