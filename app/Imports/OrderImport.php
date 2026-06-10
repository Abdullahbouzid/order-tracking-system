<?php

namespace App\Imports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class OrderImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $userId;
    protected $importedCount = 0;

    public function __construct()
    {
        $this->userId = Auth::id();
    }

    public function model(array $row)
    {
        static $loggedColumns = false;
        if (!$loggedColumns) {
            Log::info("Excel columns found: " . json_encode(array_keys($row)));
            $loggedColumns = true;
        }

        // تطبيع المفاتيح (إزالة المسافات وتحويل إلى uppercase مع شرطة سفلية)
        $normalized = [];
        foreach ($row as $key => $value) {
            $clean = trim(preg_replace('/\s+/', ' ', $key));
            $clean = strtoupper(str_replace(' ', '_', $clean));
            $normalized[$clean] = $value;
        }
        $row = $normalized;

        $getValue = function($possibleKeys) use ($row) {
            foreach ((array)$possibleKeys as $key) {
                $keyUpper = strtoupper(trim(str_replace(' ', '_', $key)));
                if (isset($row[$keyUpper]) && $row[$keyUpper] !== null && $row[$keyUpper] !== '') {
                    return $row[$keyUpper];
                }
            }
            return null;
        };

        // ORDER ID (إجباري)
        $orderId = $getValue(['ORDER_ID', 'ORDERID', 'ORDER ID']);
        if (!$orderId) {
            Log::warning("Skipping row: ORDER ID missing or empty. Keys: " . json_encode(array_keys($row)));
            return null;
        }

        // الحقول الأساسية
        $customerName           = $getValue(['OUTLET', 'STORE']);
        $customerContactNumber  = $getValue(['CUSTOMER_CONTACT_NUMBER', 'CONTACT_NUMBER', 'PHONE']);
        $orderDate              = $this->parseDateTime($getValue(['ORDER_DATE', 'ORDERDATE', 'ORDER DATE']));
        $priceList              = $getValue(['PAYMENT_MODE', 'PAYMENT_MODE', 'PAYMENT TYPE']);
        $totalAmount            = floatval(str_replace(',', '', $getValue(['TOTAL', 'TOTAL_AMOUNT']) ?? 0));
        $collectCashDate        = $this->parseDateTime($getValue(['CASH', 'CASH_DATE']));
        $collectHawalaDate      = $this->parseDateTime($getValue(['TRANSFE5R', 'TRANSFER', 'TRANSFER_DATE']));
        $employeeName           = $getValue(['NAME', 'EMPLOYEE_NAME', 'STAFF_NAME']);

        // التواريخ الأخرى
        $orderForApprove   = $this->parseDateTime($getValue(['SENT_FOR_APPROVAL']));
        $orderApproved     = $this->parseDateTime($getValue(['APPROVED']));
        $orderForPayment   = $this->parseDateTime($getValue(['SENT_TO_CUSTOER_FOR_COLLECTION']));
        $sellApprove       = $this->parseDateTime($getValue(['SENT_FOR_DLVRY_APPROVAL']));
        $releaseApprove    = $this->parseDateTime($getValue(['APPROVED_FOR_DLVRY']));
        $startPreparation  = $this->parseDateTime($getValue(['SENT_FOR_PREPARATION']));
        $readyToDeliver    = $this->parseDateTime($getValue(['PREPAPRED']));
        $outForDeliver     = $this->parseDateTime($getValue(['SENT_FOR_DLVRY']));
        $delivered         = $this->parseDateTime($getValue(['DLVRD']));
        $notes             = $getValue(['ملاحظة', 'NOTES', 'MLAHTH']);

        if (!$orderDate) {
            Log::warning("Missing order date for order ID: {$orderId}, using current date");
            $orderDate = now();
        }

        $cashReceived = $totalAmount;
        $hawalaReceived = 0;

        $this->importedCount++;

        return new Order([
            'customerName'           => $customerName,
            'customerContactNumber'  => $customerContactNumber,
            'orderId'                => $orderId,
            'orderDate'              => $orderDate,
            'priceList'              => $priceList,
            'cash_received'          => $cashReceived,
            'hawala_received'        => $hawalaReceived,
            'total'                  => $totalAmount,
            'employeeName'           => $employeeName,
            'currentStatus'          => $getValue(['CURRENT_STATUS', 'الحالة']) ?? 'pending',
            'orderForApprove'        => $orderForApprove,
            'orderApproved'          => $orderApproved,
            'orderForPayment'        => $orderForPayment,
            'collectPayment'         => null,
            'collect_payment_cash'   => $collectCashDate,
            'collect_payment_hawala' => $collectHawalaDate,
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

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    protected function parseDateTime($value)
    {
        if ($value === null || $value === '') return null;
        $original = $value;
        $value = trim($value);

        // 1. معالجة القيم الرقمية (Excel serial date)
        if (is_numeric($value)) {
            try {
                $excelDate = ExcelDate::excelToDateTimeObject($value);
                $carbon = Carbon::instance($excelDate);
                // التحقق من صحة السنة (لا تقل عن 1900)
                if ($carbon->year < 1900) {
                    $carbon->year(now()->year);
                }
                return $carbon;
            } catch (\Exception $e) {
                Log::warning("Excel date conversion failed for numeric: {$value}");
            }
        }

        // 2. محاولة الصيغ النصية
        $formats = [
            'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
            'Y-m-d H:i:s', 'Y-m-d',
            'm/d/Y H:i:s', 'm/d/Y',
            'd-m-Y H:i:s', 'd-m-Y',
            'd.m.Y H:i:s', 'd.m.Y'
        ];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                // تصحيح السنة إذا كانت غير منطقية (مثلاً 0206)
                if ($date->year < 1900) {
                    $date->year(now()->year);
                }
                return $date;
            } catch (\Exception $e) {
                continue;
            }
        }

        // 3. محاولة parse العامة (لصيغ مثل "2026-01-06 10:17:00")
        try {
            $date = Carbon::parse($value);
            if ($date->year < 1900) {
                $date->year(now()->year);
            }
            return $date;
        } catch (\Exception $e) {}

        Log::warning("Could not parse date: {$original}");
        return null;
    }

    public function rules(): array
    {
        return [];
    }
}