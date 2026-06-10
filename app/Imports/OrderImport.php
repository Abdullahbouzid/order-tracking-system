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
        // تنظيف أسماء الأعمدة (إزالة المسافات الزائدة)
        $cleanedRow = [];
        foreach ($row as $key => $value) {
            $cleanedRow[trim($key)] = $value;
        }
        $row = $cleanedRow;

        // تعيين الحقول من الأسماء الجديدة
        $customerName   = $row['OUTLET'] ?? $row['outlet'] ?? null;
        $customerContactNumber = $row['CUSTOMER CONTACT NUMBER'] ?? $row['customer_contact_number'] ?? null;
        $orderId        = $row['ORDER ID'] ?? $row['order_id'] ?? null;
        $orderDate      = $this->parseDateTime($row['ORDER DATE'] ?? $row['order_date'] ?? null);
        $priceList      = $this->mapPriceList($row['PAYMENT MODE'] ?? $row['payment mode'] ?? null);
        $cash           = floatval(str_replace(',', '', $row['CASH'] ?? $row['cash'] ?? 0));
        $hawala         = floatval(str_replace(',', '', $row['TRANSFE5R'] ?? $row['transfer'] ?? 0));
        $total          = $cash + $hawala; // حساب الإجمالي تلقائياً (يمكن أيضاً قراءة TOTAL من الملف لو أردت)

        // التواريخ حسب المراحل
        $orderForApprove   = $this->parseDateTime($row['SENT FOR APPROVAL'] ?? $row['sent_for_approval'] ?? null);
        $orderApproved     = $this->parseDateTime($row['APPROVED'] ?? $row['approved'] ?? null);
        $orderForPayment   = $this->parseDateTime($row['SENT TO CUSTOER FOR COLLECTION'] ?? $row['sent_to_customer_for_collection'] ?? null);
        $collectPayment    = null; // ليس لدينا عمود مقابل، ربما يكون نفس SENT TO CUSTOMER FOR COLLECTION؟ سنتركه null
        $sellApprove       = $this->parseDateTime($row['SENT FOR DLVRY APPROVAL'] ?? $row['sent_for_dlvry_approval'] ?? null);
        $releaseApprove    = $this->parseDateTime($row['APPROVED FOR DLVRY'] ?? $row['approved_for_dlvry'] ?? null);
        $startPreparation  = $this->parseDateTime($row['SENT FOR PREPARATION'] ?? $row['sent_for_preparation'] ?? null);
        $readyToDeliver    = $this->parseDateTime($row['PREPAPRED'] ?? $row['prepared'] ?? null);
        $outForDeliver     = $this->parseDateTime($row['SENT FOR DLVRY'] ?? $row['sent_for_dlvry'] ?? null);
        $delivered         = $this->parseDateTime($row['DLVRD'] ?? $row['dlvrd'] ?? null);
        $notes             = $row['ملاحظة'] ?? $row['notes'] ?? null;

        // إذا لم يجد التاريخ، نستخدم الآن
        if (!$orderDate) {
            Log::warning("Invalid or missing order date, using current date for order: " . $orderId);
            $orderDate = now();
        }

        // اسم الموظف (employeeName) ليس موجوداً في الأعمدة المعطاة، يمكن تعيينه لاحقاً أو تركه فارغاً
        $employeeName = null; // يمكن تركه فارغاً، أو قراءته من عمود إضافي إن وجد

        return new Order([
            'customerName'           => $customerName,
            'customerContactNumber'  => $customerContactNumber,
            'orderId'                => $orderId,
            'orderDate'              => $orderDate,
            'priceList'              => $priceList,
            'cash_received'          => $cash,
            'hawala_received'        => $hawala,
            'total'                  => $total,
            'employeeName'           => $employeeName,
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
            'notes'                  => $notes,
            'user_id'                => $this->userId,
        ]);
    }

    protected function parseDateTime($value)
    {
        if (!$value) return null;
        $value = trim($value);
        $formats = [
            'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
            'Y-m-d H:i:s', 'Y-m-d',
            'm/d/Y H:i:s', 'm/d/Y',
            'd-m-Y H:i:s', 'd-m-Y',
        ];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Exception $e) {
                continue;
            }
        }
        // دعم تنسيق Excel timestamp الرقمي
        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($value));
            } catch (\Exception $e) {}
        }
        Log::warning("Could not parse date: {$value}");
        return null;
    }

    protected function mapPriceList($value)
    {
        if (!$value) return null;
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