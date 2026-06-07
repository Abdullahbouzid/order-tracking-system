<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use App\Filament\Widgets\OrderStatsOverview;
use App\Filament\Widgets\OrderStageDelayChart;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\OrderTrendChart;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use App\Filament\Widgets\EmployeePerformanceChart;
use App\Filament\Widgets\PaymentTypeChart;
use App\Filament\Widgets\TopCustomersTable;
use App\Filament\Widgets\PeriodComparisonChart;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'لوحة التحكم';
    protected static ?int $navigationSort = -2;

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('status')
                    ->label('الحالة')
                    ->options([
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
                    ])
                    ->placeholder('جميع الحالات')
                    ->nullable(),
                DatePicker::make('date_from')
                    ->label('من تاريخ الإنشاء')
                    ->displayFormat('d/m/Y'),
                DatePicker::make('date_to')
                    ->label('إلى تاريخ الإنشاء')
                    ->displayFormat('d/m/Y'),
            ])
            ->columns(3);
    }

public function getWidgets(): array
{
    $filters = $this->filters;
    return [
        OrderStatsOverview::make(['filters' => $filters]),
        PeriodComparisonChart::make(['filters' => $filters]), // جديد
        OrderStageDelayChart::make(['filters' => $filters]),
        OrderStatusChart::make(['filters' => $filters]),
        OrderTrendChart::make(['filters' => $filters]),
        EmployeePerformanceChart::make(['filters' => $filters]),  // جديد
        PaymentTypeChart::make(['filters' => $filters]),         // جديد
        TopCustomersTable::make(['filters' => $filters]),        // جديد
    ];
}

protected function getHeaderActions(): array
{
    return [
        Action::make('export_pdf')
            ->label('تصدير تقرير PDF')
            ->icon('heroicon-o-document-text')
            ->action(function () {
                $filters = $this->filters;
                $query = Order::query()->forUser(Auth::id());
                if (!empty($filters['status'])) $query->where('currentStatus', $filters['status']);
                if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
                if (!empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);
                $orders = $query->get();
                $pdf = Pdf::loadView('reports.full-dashboard-report', ['orders' => $orders, 'filters' => $filters]);
                return response()->streamDownload(fn() => print($pdf->output()), 'dashboard-report.pdf');
            }),
    ];
}
}