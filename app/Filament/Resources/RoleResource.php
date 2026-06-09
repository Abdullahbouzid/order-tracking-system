<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'الأدوار';

    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_roles');
    }

    /**
     * ترجمة الصلاحية إلى العربية مع اسم إنجليزي كـ tooltip
     */
    protected static function getPermissionLabel(string $permissionName): string
    {
        $labels = [
            // صلاحيات الواجهات (الموارد الأساسية)
            'view_orders' => 'عرض الطلبات (Orders)',
            'create_orders' => 'إنشاء طلب (Create Order)',
            'edit_orders' => 'تعديل طلب (Edit Order)',
            'delete_orders' => 'حذف طلب (Delete Order)',
            'view_any_order' => 'عرض أي طلب (View Any Order)',
            'create_order' => 'إنشاء طلب (Create Order) - مرادف',
            'update_order' => 'تعديل طلب (Update Order) - مرادف',
            'delete_order' => 'حذف طلب (Delete Order) - مرادف',
            'view_order' => 'عرض طلب (View Order)',

            // صلاحيات المراحل الخاصة بالطلب
            'approve_order' => 'الموافقة على الطلب (Approve Order)',
            'process_payment' => 'معالجة الدفع (Process Payment)',
            'release_order' => 'الإفراج عن الطلب (Release Order)',
            'prepare_order' => 'تجهيز الطلب (Prepare Order)',
            'deliver_order' => 'تسليم الطلب (Deliver Order)',

            // صلاحيات حقول إضافية
            'view_total' => 'عرض إجمالي السعر (View Total)',
            'edit_total' => 'تعديل إجمالي السعر (Edit Total)',
            'view_contact' => 'عرض رقم هاتف العميل (View Contact)',
            'edit_contact' => 'تعديل رقم هاتف العميل (Edit Contact)',

            // صلاحيات المستخدمين
            'view_users' => 'عرض المستخدمين (View Users)',
            'view_any_users' => 'عرض أي مستخدم (View Any Users)',
            'create_users' => 'إنشاء مستخدم (Create User)',
            'edit_users' => 'تعديل مستخدم (Edit User)',
            'delete_users' => 'حذف مستخدم (Delete User)',
            'view_user' => 'عرض مستخدم (View User)',
            'create_user' => 'إنشاء مستخدم (Create User) - مرادف',
            'update_user' => 'تعديل مستخدم (Update User) - مرادف',
            'delete_user' => 'حذف مستخدم (Delete User) - مرادف',
            'delete_any_user' => 'حذف أي مستخدم (Delete Any User)',

            // صلاحيات الأدوار
            'view_roles' => 'عرض الأدوار (View Roles)',
            'view_any_roles' => 'عرض أي دور (View Any Roles)',
            'create_roles' => 'إنشاء دور (Create Role)',
            'edit_roles' => 'تعديل دور (Edit Role)',
            'delete_roles' => 'حذف دور (Delete Role)',
            'view_role' => 'عرض دور (View Role)',
            'create_role' => 'إنشاء دور (Create Role) - مرادف',
            'update_role' => 'تعديل دور (Update Role) - مرادف',
            'delete_role' => 'حذف دور (Delete Role) - مرادف',
            'delete_any_role' => 'حذف أي دور (Delete Any Role)',

            // صلاحيات الصلاحيات (Permissions)
            'view_permissions' => 'عرض الصلاحيات (View Permissions)',
            'view_any_permissions' => 'عرض أي صلاحية (View Any Permissions)',
            'create_permissions' => 'إنشاء صلاحية (Create Permission)',
            'edit_permissions' => 'تعديل صلاحية (Edit Permission)',
            'delete_permissions' => 'حذف صلاحية (Delete Permission)',
            'view_permission' => 'عرض صلاحية (View Permission)',
            'create_permission' => 'إنشاء صلاحية (Create Permission) - مرادف',
            'update_permission' => 'تعديل صلاحية (Update Permission) - مرادف',
            'delete_permission' => 'حذف صلاحية (Delete Permission) - مرادف',
            'delete_any_permission' => 'حذف أي صلاحية (Delete Any Permission)',

            // صلاحيات التقارير والسجلات
            'view_reports' => 'عرض التقارير (View Reports)',
            'generate_reports' => 'إنشاء تقارير (Generate Reports)',
            'view_audit_logs' => 'عرض سجلات التدقيق (View Audit Logs)',

            // صلاحيات الإعدادات
            'view_settings' => 'عرض الإعدادات (View Settings)',
            'edit_settings' => 'تعديل الإعدادات (Edit Settings)',
            'manage_system' => 'إدارة النظام (Manage System)',

            // صلاحيات لوحة التحكم
            'view_dashboard' => 'عرض لوحة التحكم (View Dashboard)',

            // صلاحيات التواقيت واللحظية
            'set_stage_timestamp' => 'تعيين توقيت المرحلة (Set Stage Timestamp)',
            'set_order_date' => 'تعيين تاريخ الطلب (Set Order Date)',

            // ملحقات عامة (لتغطية أي صلاحية غير موجودة)
        ];

        return $labels[$permissionName] ?? $permissionName;
    }

    /**
     * تجميع الصلاحيات في فئات
     */
    protected static function getGroupedPermissions(): array
    {
        $allPermissions = Permission::all()->pluck('name')->toArray();

        $groups = [
            'صلاحيات الواجهات (Interface Permissions)' => [
                'view_orders', 'view_any_order', 'view_order', 'create_orders', 'create_order',
                'edit_orders', 'update_order', 'delete_orders', 'delete_order', 'delete_any_order',
            ],
            'صلاحيات الإنشاء (Create Permissions)' => [
                'create_orders', 'create_order', 'create_users', 'create_user', 'create_roles', 'create_role',
                'create_permissions', 'create_permission',
            ],
            'صلاحيات العرض (View Permissions)' => [
                'view_orders', 'view_any_order', 'view_order', 'view_users', 'view_any_users', 'view_user',
                'view_roles', 'view_any_roles', 'view_role', 'view_permissions', 'view_any_permissions', 'view_permission',
                'view_reports', 'view_audit_logs', 'view_settings', 'view_dashboard', 'view_total', 'view_contact',
            ],
            'صلاحيات التعديل (Edit Permissions)' => [
                'edit_orders', 'update_order', 'edit_users', 'update_user', 'edit_roles', 'update_role',
                'edit_permissions', 'update_permission', 'edit_settings', 'edit_total', 'edit_contact',
            ],
            'صلاحيات الحذف (Delete Permissions)' => [
                'delete_orders', 'delete_order', 'delete_any_order', 'delete_users', 'delete_user', 'delete_any_user',
                'delete_roles', 'delete_role', 'delete_any_role', 'delete_permissions', 'delete_permission', 'delete_any_permission',
            ],
            'صلاحيات مراحل الطلب (Order Stage Permissions)' => [
                'approve_order', 'process_payment', 'release_order', 'prepare_order', 'deliver_order',
            ],
            'صلاحيات التقارير والتصدير (Reports & Export)' => [
                'view_reports', 'generate_reports', 'view_audit_logs',
            ],
            'صلاحيات النظام والإعدادات (System & Settings)' => [
                'view_settings', 'edit_settings', 'manage_system',
            ],
            'صلاحيات الحقول الإضافية (Extra Fields)' => [
                'view_total', 'edit_total', 'view_contact', 'edit_contact',
            ],
            'صلاحيات التواقيت (Timestamps Permissions)' => [
                'set_stage_timestamp', 'set_order_date',
            ],
        ];

        // تصفية المجموعات لاحتواء الصلاحيات الموجودة فقط
        $filteredGroups = [];
        foreach ($groups as $groupName => $perms) {
            $existing = array_intersect($perms, $allPermissions);
            if (!empty($existing)) {
                $filteredGroups[$groupName] = $existing;
            }
        }

        return $filteredGroups;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('اسم الدور'),
                
                // عرض الصلاحيات على شكل CheckboxList مع مجموعات
                Forms\Components\Fieldset::make('الصلاحيات')
                    ->schema(
                        collect(static::getGroupedPermissions())->map(function ($perms, $groupName) {
                            return Forms\Components\CheckboxList::make('permissions')
                                ->label($groupName)
                                ->options(
                                    collect($perms)->mapWithKeys(fn($perm) => [$perm => static::getPermissionLabel($perm)])
                                )
                                ->columns(2)
                                ->bulkToggleable()
                                ->helperText(function () use ($perms) {
                                    return 'إجمالي ' . count($perms) . ' صلاحية';
                                });
                        })->toArray()
                    )
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * حفظ الصلاحيات (ربطها بالدور)
     */
    public static function afterSave(Form $form, Role $record): void
    {
        $permissions = $form->getState()['permissions'] ?? [];
        $record->syncPermissions($permissions);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('permissions_count')->counts('permissions')->label('عدد الصلاحيات'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn () => auth()->user()->can('edit_roles')),
                Tables\Actions\DeleteAction::make()->visible(fn () => auth()->user()->can('delete_roles')),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->visible(fn () => auth()->user()->can('delete_roles')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}