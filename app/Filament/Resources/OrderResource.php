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
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FilteredOrdersExport;
use App\Exports\CustomStageComparisonExport;
use App\Imports\OrderImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

    /**
     * دالة مساعدة لإنشاء حقل DateTimePicker مع زر "خذ الوقت الحالي"
     */
    protected static function makeDateTimeField(string $name, string $label, string $permission = 'set_stage_timestamp'): DateTimePicker
    {
        return DateTimePicker::make($name)
            ->label($label)
            ->displayFormat('d/m/Y H:i:s')
            ->suffixActions([
                Action::make('set_now')
                    ->label('خذ الوقت الحالي')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->visible(fn () => auth()->user()->can($permission))
                    ->action(function ($livewire, callable $set) use ($name) {
                        $set($name, now()->format('Y-m-d H:i:s'));
                        $livewire->dispatch('refresh');
                    }),
            ]);
    }

    /**
     * دالة مساعدة لإنشاء حقل DateTimePicker مع زر "خذ الوقت الحالي" وزر رابط SendPulse
     */
    protected static function makeDateTimeFieldWithSendPulseLink(string $name, string $label, string $permission = 'set_stage_timestamp'): DateTimePicker
    {
        return DateTimePicker::make($name)
            ->label($label)
            ->displayFormat('d/m/Y H:i:s')
            ->suffixActions([
                Action::make('set_now')
                    ->label('خذ الوقت الحالي')
                    ->icon('heroicon-o-clock')
                    ->color('primary')
                    ->visible(fn () => auth()->user()->can($permission))
                    ->action(function ($livewire, callable $set) use ($name) {
                        $set($name, now()->format('Y-m-d H:i:s'));
                        $livewire->dispatch('refresh');
                    }),
                Action::make('sendpulse_link')
                    ->label('إدارة المحادثات')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url('https://login.sendpulse.com/messengers', shouldOpenInNewTab: true)
                    ->openUrlInNewTab()
                    ->visible(fn () => auth()->user()->can($permission)),
            ]);
    }

    /**
     * إنشاء ملف قالب Excel للاستيراد
     */
    protected static function generateTemplateFile($path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'A1' => 'customer_name',
            'B1' => 'customer_contact_number',
            'C1' => 'order_id',
            'D1' => 'order_date',
            'E1' => 'price_list',
            'F1' => 'cash_received',
            'G1' => 'hawala_received',
            'H1' => 'employee_name',
            'I1' => 'current_status',
            'J1' => 'order_for_approve',
            'K1' => 'order_approved',
            'L1' => 'order_for_payment',
            'M1' => 'collect_payment',
            'N1' => 'sell_approve',
            'O1' => 'release_approve',
            'P1' => 'start_preparation',
            'Q1' => 'ready_to_deliver',
            'R1' => 'out_for_deliver',
            'S1' => 'delivered',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // صف نموذجي
        $sheet->setCellValue('A2', 'John Doe');
        $sheet->setCellValue('B2', '123456789');
        $sheet->setCellValue('C2', 'ORD-001');
        $sheet->setCellValue('D2', '01/01/2025 10:00:00');
        $sheet->setCellValue('E2', 'cash');
        $sheet->setCellValue('F2', '100');
        $sheet->setCellValue('G2', '0');
        $sheet->setCellValue('H2', 'Employee Name');
        $sheet->setCellValue('I2', 'pending');

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
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
                        self::makeDateTimeField('orderDate', 'تاريخ الطلب'),
                        Forms\Components\Select::make('priceList')
                            ->label('نوع الدفع')
                            ->options([
                                'cash'      => 'كاش',
                                'half_half' => '50% 50%',
                                'hawala'    => 'حوالة',
                            ])
                            ->nullable(),
                        Forms\Components\TextInput::make('cash_received')
                            ->label('المبلغ المستلم (كاش)')
                            ->numeric()
                            ->prefix('LYD')
                            ->nullable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set, $get) => 
                                $set('total', floatval($state ?? 0) + floatval($get('hawala_received') ?? 0))
                            ),
                        Forms\Components\TextInput::make('hawala_received')
                            ->label('المبلغ المستلم (حوالة)')
                            ->numeric()
                            ->prefix('LYD')
                            ->nullable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set, $get) => 
                                $set('total', floatval($get('cash_received') ?? 0) + floatval($state ?? 0))
                            ),
                        Forms\Components\TextInput::make('total')
                            ->label('إجمالي السعر (كاش + حوالة)')
                            ->numeric()
                            ->prefix('LYD')
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText('يتم حسابه تلقائياً عند إدخال المبالغ المستلمة.'),
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
                        self::makeDateTimeFieldWithSendPulseLink('orderForApprove', 'طلب الموافقة'),
                        self::makeDateTimeField('orderApproved', 'تمت الموافقة'),
                        self::makeDateTimeFieldWithSendPulseLink('orderForPayment', 'طلب الدفع'),
                        self::makeDateTimeField('collectPayment', 'تحصيل الدفع'),
                        self::makeDateTimeField('sellApprove', 'موافقة البيع'),
                        self::makeDateTimeField('releaseApprove', 'موافقة الإفراج'),
                        self::makeDateTimeField('startPreparation', 'بدء التجهيز'),
                        self::makeDateTimeField('readyToDeliver', 'جاهز للتسليم'),
                        self::makeDateTimeField('outForDeliver', 'خرج للتسليم'),
                        self::makeDateTimeField('delivered', 'تم التسليم'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->forUser(Auth::id()))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('employeeName')->label('اسم الموظف')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('customerName')->label('العميل')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('customerContactNumber')->label('رقم الهاتف')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('orderId')->label('رقم الطلب')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('orderDate')
                    ->label('تاريخ الطلب')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('priceList')
                    ->label('نوع الدفع')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cash' => 'كاش',
                        'half_half' => '50% 50%',
                        'hawala' => 'حوالة',
                        default => '-',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cash_received')
                    ->label('المبلغ المستلم (كاش)')
                    ->money('LYD')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('hawala_received')
                    ->label('المبلغ المستلم (حوالة)')
                    ->money('LYD')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي (كاش+حوالة)')
                    ->money('LYD')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('currentStatus')
                    ->label('الحالة')
                    ->badge()
                    ->colors([
                        'secondary' => 'pending',
                        'warning'   => 'orderForApprove',
                        'primary'   => 'orderApproved',
                        'info'      => 'orderForPayment',
                        'success'   => 'collectPayment',
                        'danger'    => 'sellApprove',
                        'dark'      => 'releaseApprove',
                        'warning'   => 'startPreparation',
                        'success'   => 'readyToDeliver',
                        'info'      => 'outForDeliver',
                        'success'   => 'delivered',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'          => 'قيد الانتظار',
                        'orderForApprove'  => 'طلب موافقة',
                        'orderApproved'    => 'تمت الموافقة',
                        'orderForPayment'  => 'طلب دفع',
                        'collectPayment'   => 'تم تحصيل الدفع',
                        'sellApprove'      => 'موافقة البيع',
                        'releaseApprove'   => 'موافقة الإفراج',
                        'startPreparation' => 'بدء التجهيز',
                        'readyToDeliver'   => 'جاهز للتسليم',
                        'outForDeliver'    => 'خرج للتسليم',
                        'delivered'        => 'تم التسليم',
                        default            => $state,
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('orderForApprove')
                    ->label('طلب الموافقة')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('orderApproved')
                    ->label('تمت الموافقة')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('orderForPayment')
                    ->label('طلب الدفع')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('collectPayment')
                    ->label('تحصيل الدفع')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sellApprove')
                    ->label('موافقة البيع')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('releaseApprove')
                    ->label('موافقة الإفراج')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('startPreparation')
                    ->label('بدء التجهيز')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('readyToDeliver')
                    ->label('جاهز للتسليم')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('outForDeliver')
                    ->label('خرج للتسليم')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('delivered')
                    ->label('تم التسليم')
                    ->dateTime('d/m/Y H:i:s')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_time')
                    ->label('الوقت الإجمالي')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(),
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
                Tables\Filters\Filter::make('cash_range')
                    ->form([
                        Forms\Components\TextInput::make('cash_from')->label('المبلغ المستلم (كاش) من')->numeric(),
                        Forms\Components\TextInput::make('cash_to')->label('إلى')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['cash_from'], fn ($q, $v) => $q->where('cash_received', '>=', $v))
                        ->when($data['cash_to'], fn ($q, $v) => $q->where('cash_received', '<=', $v))
                    ),
                Tables\Filters\Filter::make('hawala_range')
                    ->form([
                        Forms\Components\TextInput::make('hawala_from')->label('المبلغ المستلم (حوالة) من')->numeric(),
                        Forms\Components\TextInput::make('hawala_to')->label('إلى')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['hawala_from'], fn ($q, $v) => $q->where('hawala_received', '>=', $v))
                        ->when($data['hawala_to'], fn ($q, $v) => $q->where('hawala_received', '<=', $v))
                    ),
                Tables\Filters\Filter::make('total_range')
                    ->form([
                        Forms\Components\TextInput::make('total_from')->label('الإجمالي من')->numeric(),
                        Forms\Components\TextInput::make('total_to')->label('الإجمالي إلى')->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['total_from'], fn ($q, $v) => $q->where('total', '>=', $v))
                        ->when($data['total_to'], fn ($q, $v) => $q->where('total', '<=', $v))
                    ),
                Tables\Filters\SelectFilter::make('currentStatus')
                    ->label('الحالة')
                    ->options([
                        'pending'          => 'قيد الانتظار',
                        'orderForApprove'  => 'طلب موافقة',
                        'orderApproved'    => 'تمت الموافقة',
                        'orderForPayment'  => 'طلب دفع',
                        'collectPayment'   => 'تم تحصيل الدفع',
                        'sellApprove'      => 'موافقة البيع',
                        'releaseApprove'   => 'موافقة الإفراج',
                        'startPreparation' => 'بدء التجهيز',
                        'readyToDeliver'   => 'جاهز للتسليم',
                        'outForDeliver'    => 'خرج للتسليم',
                        'delivered'        => 'تم التسليم',
                    ]),
                Tables\Filters\Filter::make('orderDate_range')
                    ->form([
                        Forms\Components\DateTimePicker::make('orderDate_from')->label('من تاريخ الطلب')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('orderDate_until')->label('إلى تاريخ الطلب')->displayFormat('d/m/Y H:i:s'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['orderDate_from'], fn ($q, $v) => $q->where('orderDate', '>=', $v))
                        ->when($data['orderDate_until'], fn ($q, $v) => $q->where('orderDate', '<=', $v))
                    ),
                Tables\Filters\Filter::make('created_at_range')
                    ->form([
                        Forms\Components\DateTimePicker::make('created_from')->label('من تاريخ الإنشاء')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('created_until')->label('إلى تاريخ الإنشاء')->displayFormat('d/m/Y H:i:s'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['created_from'], fn ($q, $v) => $q->where('created_at', '>=', $v))
                        ->when($data['created_until'], fn ($q, $v) => $q->where('created_at', '<=', $v))
                    ),
                Tables\Filters\Filter::make('orderApproved_range')
                    ->form([
                        Forms\Components\DateTimePicker::make('orderApproved_from')->label('من تاريخ الموافقة')->displayFormat('d/m/Y H:i:s'),
                        Forms\Components\DateTimePicker::make('orderApproved_until')->label('إلى تاريخ الموافقة')->displayFormat('d/m/Y H:i:s'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['orderApproved_from'], fn ($q, $v) => $q->where('orderApproved', '>=', $v))
                        ->when($data['orderApproved_until'], fn ($q, $v) => $q->where('orderApproved', '<=', $v))
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
                // تصدير مع فلترة
                Tables\Actions\Action::make('export_filtered')
                    ->label('تصدير إلى Excel (مع فلترة)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->form([
                        DatePicker::make('date_from')->label('من تاريخ الطلب')->displayFormat('d/m/Y')->nullable(),
                        DatePicker::make('date_to')->label('إلى تاريخ الطلب')->displayFormat('d/m/Y')->nullable(),
                        TextInput::make('customer_name')->label('اسم العميل')->nullable(),
                        TextInput::make('employee_name')->label('اسم الموظف')->nullable(),
                        TextInput::make('phone')->label('رقم هاتف العميل')->nullable(),
                    ])
                    ->action(function (array $data) {
                        $query = Order::query()->forUser(Auth::id());
                        if (!empty($data['date_from'])) $query->whereDate('orderDate', '>=', $data['date_from']);
                        if (!empty($data['date_to'])) $query->whereDate('orderDate', '<=', $data['date_to']);
                        if (!empty($data['customer_name'])) $query->where('customerName', 'like', '%' . $data['customer_name'] . '%');
                        if (!empty($data['employee_name'])) $query->where('employeeName', 'like', '%' . $data['employee_name'] . '%');
                        if (!empty($data['phone'])) $query->where('customerContactNumber', 'like', '%' . $data['phone'] . '%');
                        $orders = $query->get();
                        if ($orders->isEmpty()) {
                            Notification::make()->title('لا توجد بيانات تطابق المعايير')->danger()->send();
                            return;
                        }
                        return Excel::download(new FilteredOrdersExport($orders), 'الطلبات_' . now()->format('Ymd_His') . '.xlsx');
                    })
                    ->visible(fn () => Auth::user()->can('view_orders')),
                
                // تقرير مقارنة المراحل
                Tables\Actions\Action::make('custom_stage_report')
                    ->label('تقرير مخصص (مقارنة مرحلتين)')
                    ->icon('heroicon-o-chart-bar')
                    ->color('success')
                    ->form([
                        Select::make('from_field')->label('حقل البداية')->options([
                            'orderDate' => 'تاريخ الطلب',
                            'orderForApprove' => 'طلب الموافقة',
                            'orderApproved' => 'تمت الموافقة',
                            'orderForPayment' => 'طلب دفع',
                            'collectPayment' => 'تحصيل الدفع',
                            'sellApprove' => 'موافقة البيع',
                            'releaseApprove' => 'موافقة الإفراج',
                            'startPreparation' => 'بدء التجهيز',
                            'readyToDeliver' => 'جاهز للتسليم',
                            'outForDeliver' => 'خرج للتسليم',
                            'delivered' => 'تم التسليم',
                        ])->required(),
                        Select::make('to_field')->label('حقل النهاية')->options([
                            'orderDate' => 'تاريخ الطلب',
                            'orderForApprove' => 'طلب الموافقة',
                            'orderApproved' => 'تمت الموافقة',
                            'orderForPayment' => 'طلب دفع',
                            'collectPayment' => 'تحصيل الدفع',
                            'sellApprove' => 'موافقة البيع',
                            'releaseApprove' => 'موافقة الإفراج',
                            'startPreparation' => 'بدء التجهيز',
                            'readyToDeliver' => 'جاهز للتسليم',
                            'outForDeliver' => 'خرج للتسليم',
                            'delivered' => 'تم التسليم',
                        ])->required(),
                        DatePicker::make('from_date_start')->label('من تاريخ (حقل البداية)')->displayFormat('d/m/Y')->nullable(),
                        DatePicker::make('from_date_end')->label('إلى تاريخ (حقل البداية)')->displayFormat('d/m/Y')->nullable(),
                        DatePicker::make('to_date_start')->label('من تاريخ (حقل النهاية)')->displayFormat('d/m/Y')->nullable(),
                        DatePicker::make('to_date_end')->label('إلى تاريخ (حقل النهاية)')->displayFormat('d/m/Y')->nullable(),
                        TextInput::make('customer_name')->label('اسم العميل')->nullable(),
                        TextInput::make('employee_name')->label('اسم الموظف')->nullable(),
                        TextInput::make('phone')->label('رقم هاتف العميل')->nullable(),
                    ])
                    ->action(function (array $data) {
                        $query = Order::query()->forUser(Auth::id());
                        $fromField = $data['from_field'];
                        $toField = $data['to_field'];
                        if (!empty($data['from_date_start'])) $query->whereDate($fromField, '>=', $data['from_date_start']);
                        if (!empty($data['from_date_end'])) $query->whereDate($fromField, '<=', $data['from_date_end']);
                        if (!empty($data['to_date_start'])) $query->whereDate($toField, '>=', $data['to_date_start']);
                        if (!empty($data['to_date_end'])) $query->whereDate($toField, '<=', $data['to_date_end']);
                        if (!empty($data['customer_name'])) $query->where('customerName', 'like', '%' . $data['customer_name'] . '%');
                        if (!empty($data['employee_name'])) $query->where('employeeName', 'like', '%' . $data['employee_name'] . '%');
                        if (!empty($data['phone'])) $query->where('customerContactNumber', 'like', '%' . $data['phone'] . '%');
                        $orders = $query->get();
                        if ($orders->isEmpty()) {
                            Notification::make()->title('لا توجد بيانات تطابق المعايير')->danger()->send();
                            return;
                        }
                        return Excel::download(new CustomStageComparisonExport($orders, $fromField, $toField), 'تقرير_مقارنة_المراحل_' . now()->format('Ymd_His') . '.xlsx');
                    })
                    ->visible(fn () => Auth::user()->can('view_orders')),

                // تحميل قالب الاستيراد
                Tables\Actions\Action::make('download_template')
                    ->label('تحميل قالب الاستيراد')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        $templatePath = storage_path('app/templates/import_template.xlsx');
                        if (!file_exists($templatePath)) {
                            self::generateTemplateFile($templatePath);
                        }
                        return response()->download($templatePath, 'import_template.xlsx');
                    }),

                // استيراد ملف Excel – طريقة موثوقة
                Tables\Actions\Action::make('import_excel')
                    ->label('استيراد من Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('ملف Excel')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->required()
                            ->helperText('يرجى تحميل ملف Excel بصيغة .xlsx أو .xls مطابق للقالب')
                            ->disk('local')
                            ->directory('imports_temp')
                            ->preserveFilenames(false),
                    ])
                    ->action(function (array $data) {
                        $fileId = $data['file'] ?? null;
                        if (!$fileId) {
                            Notification::make()->title('الملف غير موجود')->danger()->send();
                            return;
                        }
                        $filePath = storage_path('app/imports_temp/' . $fileId);
                        if (!file_exists($filePath)) {
                            Notification::make()->title('الملف غير موجود في المسار: ' . $filePath)->danger()->send();
                            return;
                        }
                        try {
                            Excel::import(new OrderImport, $filePath);
                            Notification::make()->title('تم الاستيراد بنجاح')->success()->send();
                            // حذف الملف بعد الاستيراد
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        } catch (\Exception $e) {
                            Notification::make()->title('فشل الاستيراد: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->bulkActions([
                ExportBulkAction::make()->label('تصدير المحدد إلى Excel')->visible(fn () => Auth::user()->can('view_orders')),
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