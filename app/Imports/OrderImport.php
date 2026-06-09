<?php

namespace App\Imports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $userId;

    public function __construct()
    {
        $this->userId = Auth::id();
    }

    public function model(array $row)
    {
        // تحويل التواريخ - افتراض صيغة d/m/Y H:i:s أو d/m/Y
        $orderDate = $this->parseDateTime($row['order_date'] ?? $row['تاريخ_الطلب'] ?? null);
        $orderForApprove = $this->parseDateTime($row['order_for_approve'] ?? $row['طلب_الموافقة'] ?? null);
        $orderApproved = $this->parseDateTime($row['order_approved'] ?? $row['تمت_الموافقة'] ?? null);
        $orderForPayment = $this->parseDateTime($row['order_for_payment'] ?? $row['طلب_الدفع'] ?? null);
        $collectPayment = $this->parseDateTime($row['collect_payment'] ?? $row['تحصيل_الدفع'] ?? null);
        $sellApprove = $this->parseDateTime($row['sell_approve'] ?? $row['موافقة_البيع'] ?? null);
        $releaseApprove = $this->parseDateTime($row['release_approve'] ?? $row['موافقة_الإفراج'] ?? null);
        $startPreparation = $this->parseDateTime($row['start_preparation'] ?? $row['بدء_التجهيز'] ?? null);
        $readyToDeliver = $this->parseDateTime($row['ready_to_deliver'] ?? $row['جاهز_للتسليم'] ?? null);
        $outForDeliver = $this->parseDateTime($row['out_for_deliver'] ?? $row['خرج_للتسليم'] ?? null);
        $delivered = $this->parseDateTime($row['delivered'] ?? $row['تم_التسليم'] ?? null);

        return new Order([
            'customerName'           => $row['customer_name'] ?? $row['اسم_العميل'] ?? null,
            'customerContactNumber'  => $row['customer_contact_number'] ?? $row['رقم_الهاتف'] ?? null,
            'orderId'                => $row['order_id'] ?? $row['رقم_الطلب'] ?? null,
            'orderDate'              => $orderDate,
            'priceList'              => $this->mapPriceList($row['price_list'] ?? $row['نوع_الدفع'] ?? null),
            'cash_received'          => $row['cash_received'] ?? $row['المبلغ_المستلم_كاش'] ?? null,
            'hawala_received'        => $row['hawala_received'] ?? $row['المبلغ_المستلم_حوالة'] ?? null,
            'employeeName'           => $row['employee_name'] ?? $row['اسم_الموظف'] ?? null,
            'currentStatus'          => $row['current_status'] ?? $row['الحالة'] ?? 'pending',
            'orderForApprove'        => $orderForApprove,
            'orderApproved'          => $orderApproved,
            'orderForPayment'        => $orderForPayment,
            'collectPayment'         => $collectPayment,
            'sellApprove'            => $sellApprove,
            'releaseApprove'         => $releaseApprove,
            'startPreparation'       => $startPreparation,
            'readyToDeliver'         => $readyToDeliver,
            'outForDeliver'          => $outForDeliver,
            'delivered'              => $delivered,
            'user_id'                => $this->userId,
        ]);
    }

    protected function parseDateTime($value)
    {
        if (!$value) return null;
        // محاولة قراءة التاريخ بصيغ متعددة
        $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value));
            } catch (\Exception $e) {
                continue;
            }
        }
        // إذا فشل كل شيء، نرجع null ونسجل خطأ
        Log::warning("Could not parse date: {$value}");
        return null;
    }

    protected function mapPriceList($value)
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['cash', 'كاش'])) return 'cash';
        if (in_array($value, ['half_half', '50% 50%', '50%'])) return 'half_half';
        if (in_array($value, ['hawala', 'حوالة', 'تحويل'])) return 'hawala';
        return null;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|unique:orders,orderId',
            'customer_name' => 'nullable|string',
            'order_date' => 'nullable',
        ];
    }
}