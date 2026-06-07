<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'الطلبات';

    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->can('view_orders');
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        return $data;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\TextInput::make('customerName')
                            ->label('اسم العميل')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customerContactNumber')
                            ->label('رقم هاتف العميل')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('orderId')
                            ->label('رقم الطلب')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('orderDate')
                            ->label('تاريخ الطلب')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Select::make('priceList')
                            ->label('نوع الدفع')
                            ->options([
                                'cash'      => 'كاش',
                                'half_half' => '50% 50%',
                                'hawala'    => 'حوالة',
                            ])
                            ->nullable(),
                        Forms\Components\TextInput::make('total')
                            ->label('إجمالي السعر')
                            ->numeric()
                            ->prefix('$')
                            ->nullable(),
                        Forms\Components\TextInput::make('employeeName')
                            ->label('اسم الموظف')
                            ->maxLength(255)
                            ->nullable()
                            ->helperText('أدخل اسم الموظف المسؤول عن الطلب (كتابة يدوية)'),
                        Forms\Components\Select::make('currentStatus')
                            ->label('الحالة الحالية')
                            ->options([
                                'pending'            => 'قيد الانتظار',
                                'orderForApprove'    => 'طلب موافقة',
                                'orderApproved'      => 'تمت الموافقة',
                                'orderForPayment'    => 'طلب دفع',
                                'collectPayment'     => 'تم تحصيل الدفع',
                                'sellApprove'        => 'موافقة البيع',
                                'releaseApprove'     => 'موافقة الإفراج',
                                'startPreparation'   => 'بدء التجهيز',
                                'readyToDeliver'     => 'جاهز للتسليم',
                                'outForDeliver'      => 'خرج للتسليم',
                                'delivered'          => 'تم التسليم',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Card::make()
                    ->schema([
                        Forms\Components\DateTimePicker::make('orderForApprove')->label('طلب الموافقة')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('orderApproved')->label('تمت الموافقة')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('orderForPayment')->label('طلب الدفع')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('collectPayment')->label('تحصيل الدفع')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('sellApprove')->label('موافقة البيع')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('releaseApprove')->label('موافقة الإفراج')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('startPreparation')->label('بدء التجهيز')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('readyToDeliver')->label('جاهز للتسليم')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('outForDeliver')->label('خرج للتسليم')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('delivered')->label('تم التسليم')->displayFormat('d/m/Y H:i:s'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->forUser(Auth::id()))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('user.name')->label('المستخدم المنشئ')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('employeeName')->label('اسم الموظف')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('customerName')->label('العميل')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('customerContactNumber')->label('رقم الهاتف')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('orderId')->label('رقم الطلب')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('orderDate')->label('التاريخ')->date('d/m/Y')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('priceList')
                    ->label('نوع الدفع')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'cash' => 'كاش', 'half_half' => '50% 50%', 'hawala' => 'حوالة', default => '-',
                    })->toggleable(),
                Tables\Columns\TextColumn::make('total')->label('الإجمالي')->money('USD')->toggleable(),
                Tables\Columns\TextColumn::make('currentStatus')
                    ->label('الحالة')
                    ->badge()
                    ->colors([
                        'secondary' => 'pending', 'warning' => 'orderForApprove', 'primary' => 'orderApproved',
                        'info' => 'orderForPayment', 'success' => 'collectPayment', 'danger' => 'sellApprove',
                        'dark' => 'releaseApprove', 'warning' => 'startPreparation', 'success' => 'readyToDeliver',
                        'info' => 'outForDeliver', 'success' => 'delivered',
                    ])
                    ->formatStateUsing(fn ($state) => match($state) {
                        'pending' => 'قيد الانتظار', 'orderForApprove' => 'طلب موافقة', 'orderApproved' => 'تمت الموافقة',
                        'orderForPayment' => 'طلب دفع', 'collectPayment' => 'تم تحصيل الدفع', 'sellApprove' => 'موافقة البيع',
                        'releaseApprove' => 'موافقة الإفراج', 'startPreparation' => 'بدء التجهيز', 'readyToDeliver' => 'جاهز للتسليم',
                        'outForDeliver' => 'خرج للتسليم', 'delivered' => 'تم التسليم', default => $state,
                    })->toggleable(),
                Tables\Columns\TextColumn::make('orderForApprove')->label('طلب الموافقة')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('orderApproved')->label('تمت الموافقة')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('orderForPayment')->label('طلب الدفع')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('collectPayment')->label('تحصيل الدفع')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('sellApprove')->label('موافقة البيع')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('releaseApprove')->label('موافقة الإفراج')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('startPreparation')->label('بدء التجهيز')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('readyToDeliver')->label('جاهز للتسليم')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('outForDeliver')->label('خرج للتسليم')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('delivered')->label('تم التسليم')->dateTime('d/m/Y H:i:s')->toggleable(),
                Tables\Columns\TextColumn::make('total_time')->label('الوقت الإجمالي')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime('d/m/Y H:i:s')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('المنشئ (user)')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->visible(fn () => auth()->user()->hasRole('super-admin')),
                Tables\Filters\SelectFilter::make('employeeName')
                    ->label('اسم الموظف')
                    ->options(fn () => Order::query()->distinct()->pluck('employeeName', 'employeeName')->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('priceList')
                    ->label('نوع الدفع')
                    ->options(['cash' => 'كاش', 'half_half' => '50% 50%', 'hawala' => 'حوالة']),
                Tables\Filters\Filter::make('total_range')
                    ->form([
                        Forms\Components\TextInput::make('total_from')->label('الإجمالي من')->numeric(),
                        Forms\Components\TextInput::make('total_to')->label('الإجمالي إلى')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['total_from'], fn ($q, $val) => $q->where('total', '>=', $val))
                        ->when($data['total_to'], fn ($q, $val) => $q->where('total', '<=', $val))
                    ),
                Tables\Filters\SelectFilter::make('currentStatus')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار', 'orderForApprove' => 'طلب موافقة', 'orderApproved' => 'تمت الموافقة',
                        'orderForPayment' => 'طلب دفع', 'collectPayment' => 'تم تحصيل الدفع', 'sellApprove' => 'موافقة البيع',
                        'releaseApprove' => 'موافقة الإفراج', 'startPreparation' => 'بدء التجهيز', 'readyToDeliver' => 'جاهز للتسليم',
                        'outForDeliver' => 'خرج للتسليم', 'delivered' => 'تم التسليم',
                    ]),
                Tables\Filters\Filter::make('orderDate_range')
                    ->form([
                        Forms\Components\DatePicker::make('orderDate_from')->label('من تاريخ الطلب')->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('orderDate_until')->label('إلى تاريخ الطلب')->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['orderDate_from'], fn ($q, $date) => $q->whereDate('orderDate', '>=', $date))
                        ->when($data['orderDate_until'], fn ($q, $date) => $q->whereDate('orderDate', '<=', $date))
                    ),
                Tables\Filters\Filter::make('created_at_range')
                    ->form([
                        Forms\Components\DateTimePicker::make('created_from')->label('من تاريخ الإنشاء')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('created_until')->label('إلى تاريخ الإنشاء')->displayFormat('d/m/Y H:i:s'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['created_from'], fn ($q, $date) => $q->where('created_at', '>=', $date))
                        ->when($data['created_until'], fn ($q, $date) => $q->where('created_at', '<=', $date))
                    ),
                Tables\Filters\Filter::make('orderApproved_range')
                    ->form([
                        Forms\Components\DateTimePicker::make('orderApproved_from')->label('من تاريخ الموافقة')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('orderApproved_until')->label('إلى تاريخ الموافقة')->displayFormat('d/m/Y H:i:s'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['orderApproved_from'], fn ($q, $date) => $q->where('orderApproved', '>=', $date))
                        ->when($data['orderApproved_until'], fn ($q, $date) => $q->where('orderApproved', '<=', $date))
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('toApprove')->label('طلب موافقة')->icon('heroicon-o-clock')->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'pending' && Auth::user()->can('approve_order'))
                        ->action(fn (Order $record) => $record->update(['orderForApprove' => now(), 'currentStatus' => 'orderForApprove'])),
                    Tables\Actions\Action::make('approve')->label('موافقة')->icon('heroicon-o-check-circle')->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'orderForApprove' && Auth::user()->can('approve_order'))
                        ->action(fn (Order $record) => $record->update(['orderApproved' => now(), 'currentStatus' => 'orderApproved'])),
                    Tables\Actions\Action::make('requestPayment')->label('طلب دفع')->icon('heroicon-o-currency-dollar')->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'orderApproved' && Auth::user()->can('process_payment'))
                        ->action(fn (Order $record) => $record->update(['orderForPayment' => now(), 'currentStatus' => 'orderForPayment'])),
                    Tables\Actions\Action::make('collectPayment')->label('تحصيل الدفع')->icon('heroicon-o-banknotes')->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'orderForPayment' && Auth::user()->can('process_payment'))
                        ->action(fn (Order $record) => $record->update(['collectPayment' => now(), 'currentStatus' => 'collectPayment'])),
                    Tables\Actions\Action::make('sellApprove')->label('موافقة البيع')->icon('heroicon-o-check')->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'collectPayment' && Auth::user()->can('release_order'))
                        ->action(fn (Order $record) => $record->update(['sellApprove' => now(), 'currentStatus' => 'sellApprove'])),
                    Tables\Actions\Action::make('release')->label('إفراج')->icon('heroicon-o-finger-print')->color('primary')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'sellApprove' && Auth::user()->can('release_order'))
                        ->action(fn (Order $record) => $record->update(['releaseApprove' => now(), 'currentStatus' => 'releaseApprove'])),
                    Tables\Actions\Action::make('prepare')->label('بدء التجهيز')->icon('heroicon-o-cog')->color('info')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'releaseApprove' && Auth::user()->can('prepare_order'))
                        ->action(fn (Order $record) => $record->update(['startPreparation' => now(), 'currentStatus' => 'startPreparation'])),
                    Tables\Actions\Action::make('readyToDeliver')->label('جاهز للتسليم')->icon('heroicon-o-truck')->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'startPreparation' && Auth::user()->can('prepare_order'))
                        ->action(fn (Order $record) => $record->update(['readyToDeliver' => now(), 'currentStatus' => 'readyToDeliver'])),
                    Tables\Actions\Action::make('outForDeliver')->label('خرج للتسليم')->icon('heroicon-o-map-pin')->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'readyToDeliver' && Auth::user()->can('deliver_order'))
                        ->action(fn (Order $record) => $record->update(['outForDeliver' => now(), 'currentStatus' => 'outForDeliver'])),
                    Tables\Actions\Action::make('deliver')->label('تسليم')->icon('heroicon-o-check')->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Order $record) => $record->currentStatus === 'outForDeliver' && Auth::user()->can('deliver_order'))
                        ->action(fn (Order $record) => $record->update(['delivered' => now(), 'currentStatus' => 'delivered'])),
                ])->button()->label('تغيير الحالة'),
                Tables\Actions\EditAction::make()->visible(fn () => Auth::user()->can('edit_orders')),
                Tables\Actions\DeleteAction::make()->visible(fn () => Auth::user()->can('delete_orders')),
            ])
            ->headerActions([
                // زر تصدير جميع البيانات (بدون فلترة) باستخدام query مخصصة
                ExportAction::make('export_all')
                    ->label('تصدير الكل إلى Excel')
                    ->exports([
                        ExcelExport::make()
                            ->fromModel(Order::class)
                            ->modifyQueryUsing(fn ($query) => $query->forUser(Auth::id()))
                    ])
                    ->visible(fn () => Auth::user()->can('view_orders')),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->label('تصدير المحدد إلى Excel')
                    ->visible(fn () => Auth::user()->can('view_orders')),
                Tables\Actions\DeleteBulkAction::make()->visible(fn () => Auth::user()->can('delete_orders')),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->persistSearchInSession();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}