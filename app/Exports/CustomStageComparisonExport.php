<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomStageComparisonExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $orders;
    protected $fromField;
    protected $toField;

    public function __construct($orders, $fromField, $toField)
    {
        $this->orders = $orders;
        $this->fromField = $fromField;
        $this->toField = $toField;
    }

    public function collection()
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return [
            '#',
            'اسم العميل',
            'رقم الهاتف',
            'رقم الطلب (orderId)',
            'حقل البداية ('. $this->fromField .')',
            'حقل النهاية ('. $this->toField .')',
            'عدد الساعات بينهما',
            'جميع التواريخ الأخرى',
            'الحالة الحالية',
            'نوع الدفع',
            'الإجمالي',
            'اسم الموظف',
            'المنشئ',
            'إجمالي المدة (من تاريخ الطلب → التسليم)',   // الحقل الجديد
            'ملاحظات'                                    // الحقل الجديد
        ];
    }

    public function map($order): array
    {
        $fromValue = $order->{$this->fromField};
        $toValue = $order->{$this->toField};
        $hoursDiff = '';
        if ($fromValue && $toValue) {
            $diffInHours = $fromValue->diffInHours($toValue);
            $hoursDiff = $diffInHours . ' ساعة';
        }

        // تجميع التواريخ الأخرى في نص واحد
        $otherDates = [];
        $dateFields = ['orderDate', 'orderForApprove', 'orderApproved', 'orderForPayment', 'collectPayment', 'sellApprove', 'releaseApprove', 'startPreparation', 'readyToDeliver', 'outForDeliver', 'delivered'];
        foreach ($dateFields as $field) {
            if ($field !== $this->fromField && $field !== $this->toField) {
                $val = $order->$field;
                $otherDates[] = $field . ': ' . ($val ? $val->format('d/m/Y H:i:s') : '-');
            }
        }
        $otherDatesStr = implode(' | ', $otherDates);

        return [
            $order->id,
            $order->customerName,
            $order->customerContactNumber,
            $order->orderId,
            $fromValue ? $fromValue->format('d/m/Y H:i:s') : '-',
            $toValue ? $toValue->format('d/m/Y H:i:s') : '-',
            $hoursDiff,
            $otherDatesStr,
            $order->currentStatus,
            $order->priceList,
            number_format($order->total, 2),
            $order->employeeName,
            $order->user->name ?? '',
            $order->total_duration ?? '',   // إجمالي المدة
            $order->notes ?? '',            // الملاحظات
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}