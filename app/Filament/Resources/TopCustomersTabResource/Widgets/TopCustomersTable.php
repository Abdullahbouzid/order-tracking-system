<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class TopCustomersTable extends BaseWidget
{
    public ?array $filters = [];
    protected static ?int $sort = 7;
    protected static ?string $heading = 'أفضل العملاء (حسب إجمالي المشتريات)';

    public function table(Table $table): Table
    {
        $query = Order::query()
            ->forUser(Auth::id())
            ->selectRaw('customerName, SUM(total) as total_spent, count(*) as order_count')
            ->groupBy('customerName')
            ->orderBy('total_spent', 'desc');

        if (!empty($this->filters['status'])) {
            $query->where('currentStatus', $this->filters['status']);
        }
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('customerName')->label('العميل')->searchable(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('إجمالي المشتريات')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('order_count')->label('عدد الطلبات'),
            ])
            ->defaultPaginationPageOption(5);
    }
}