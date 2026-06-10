<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PeriodComparisonChart extends ChartWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 1;

    /**
     * عنوان المخطط مع تفاصيل الفترات المقارنة ونسبة التغيير (بناءً على الإيرادات)
     */
    public function getHeading(): string
    {
        $currentStart = $this->getCurrentStart();
        $currentEnd   = $this->getCurrentEnd();
        $compStart    = $this->getComparisonStart();
        $compEnd      = $this->getComparisonEnd();
        $compType     = $this->getComparisonType();

        $currentRevenue = $this->getRevenueSum($currentStart, $currentEnd);
        $compRevenue    = ($compStart && $compEnd) ? $this->getRevenueSum($compStart, $compEnd) : 0;
        $changeText     = $this->getChangeText($currentRevenue, $compRevenue);

        $currentRange = $currentStart->format('Y-m-d') . ' إلى ' . $currentEnd->format('Y-m-d');
        $compRange    = ($compStart && $compEnd) ? $compStart->format('Y-m-d') . ' إلى ' . $compEnd->format('Y-m-d') : 'لا توجد فترة مقارنة';
        
        $typeText = ($compType === 'previous_year') ? '(نفس الفترة من العام الماضي)' : '(الفترة المباشرة السابقة)';
        
        return "مقارنة الإيرادات: {$currentRange} مقابل {$compRange} {$typeText}{$changeText}";
    }

    /**
     * البيانات المعروضة في الرسم البياني (الإيرادات فقط)
     */
    protected function getData(): array
    {
        $currentStart = $this->getCurrentStart()->startOfDay();
        $currentEnd   = $this->getCurrentEnd()->endOfDay();
        
        $compStart = $this->getComparisonStart();
        $compEnd   = $this->getComparisonEnd();
        
        $currentRevenue = $this->getRevenueSum($currentStart, $currentEnd);
        
        $compRevenue = 0;
        if ($compStart && $compEnd) {
            $compRevenue = $this->getRevenueSum($compStart, $compEnd);
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'الفترة الحالية',
                    'data' => [$currentRevenue],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'الفترة المقارنة',
                    'data' => [$compRevenue],
                    'backgroundColor' => 'rgba(255, 99, 132, 0.7)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => ['الإيرادات (LYD)'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    
    /**
     * إعدادات إضافية للمخطط (إظهار الأرقام على الأعمدة)
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw;
                            return label + ': ' + value.toFixed(2) + ' LYD';
                        }",
                    ],
                ],
                'datalabels' => [
                    'anchor' => 'end',
                    'align' => 'top',
                    'formatter' => "function(value) {
                        return value.toFixed(2) + ' LYD';
                    }",
                    'font' => ['weight' => 'bold'],
                    'color' => '#000',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'الإيرادات (LYD)',
                    ],
                ],
            ],
        ];
    }

    /* ---------------------- التوابع المساعدة ---------------------- */
    
    private function getCurrentStart(): Carbon
    {
        return isset($this->filters['date_from']) 
            ? Carbon::parse($this->filters['date_from']) 
            : Carbon::now()->startOfMonth();
    }
    
    private function getCurrentEnd(): Carbon
    {
        return isset($this->filters['date_to']) 
            ? Carbon::parse($this->filters['date_to']) 
            : Carbon::now()->endOfMonth();
    }
    
    private function getComparisonType(): string
    {
        return $this->filters['comparison_period'] ?? 'previous_period';
    }
    
    private function getComparisonStart(): ?Carbon
    {
        $currentStart = $this->getCurrentStart();
        $currentEnd   = $this->getCurrentEnd();
        $type = $this->getComparisonType();
        
        if ($type === 'previous_period') {
            $duration = $currentStart->diffInDays($currentEnd);
            return (clone $currentStart)->subDays($duration + 1)->startOfDay();
        }
        if ($type === 'previous_year') {
            return (clone $currentStart)->subYear();
        }
        return null;
    }
    
    private function getComparisonEnd(): ?Carbon
    {
        $currentStart = $this->getCurrentStart();
        $currentEnd   = $this->getCurrentEnd();
        $type = $this->getComparisonType();
        
        if ($type === 'previous_period') {
            return (clone $currentStart)->subDay()->endOfDay();
        }
        if ($type === 'previous_year') {
            return (clone $currentEnd)->subYear();
        }
        return null;
    }
    
    private function baseQuery($start, $end)
    {
        $query = Order::query()->forUser(Auth::id())
            ->whereBetween('orderDate', [$start, $end]);
        
        if (!empty($this->filters['status'])) {
            $query->where('currentStatus', $this->filters['status']);
        }
        
        return $query;
    }
    
    /**
     * حساب مجموع الإيرادات في فترة معينة
     */
    private function getRevenueSum($start, $end): float
    {
        return round($this->baseQuery($start, $end)->sum('total'), 2);
    }
    
    /**
     * نص نسبة التغيير بناءً على الإيرادات
     */
    private function getChangeText(float $current, float $previous): string
    {
        if ($previous == 0 && $current == 0) {
            return '';
        }
        if ($previous == 0 && $current > 0) {
            return ' (زيادة 100% ▲ أخضر)';
        }
        $percent = round((($current - $previous) / $previous) * 100, 1);
        $arrow = $percent >= 0 ? '▲' : '▼';
        $color = $percent >= 0 ? 'أخضر' : 'أحمر';
        return " (تغيير {$arrow} {$percent}% {$color})";
    }
}