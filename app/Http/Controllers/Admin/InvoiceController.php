<?php

/**
 * @Quill Information Technology
 */

namespace App\Http\Controllers\Admin;

use Storage;
use Exception;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Voucher;
use App\Models\InvoiceMonth;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\VoucherDetail;
use App\Http\Resources\Resource;
use App\Models\Client;
use App\Models\Commission;
use App\Models\BandwidthHistory;
use Illuminate\Support\Facades\Auth;
use App\Traits\InvoiceTrait;
use App\Traits\VoucherTrait;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Base\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceController extends BaseController
{
    use InvoiceTrait, VoucherTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['invoice_details', 'client:id,clientid,name,service_id', 'payment_details:id,payment_id,reference_type,reference_id,amount,is_closed'])->latest();

        // ✅ account_id → invoice_details টেবিলে filter
        if (!empty($request->account_id)) {
            $query->whereHas('invoice_details', function ($q) use ($request) {
                $q->where('account_id', $request->account_id);
            });
        }

        // ✅ payment_status → invoices টেবিলে
        if (!empty($request->client_id)) {
            $query->where('client_id', $request->client_id);
        }

        // service_id → clients টেবিলে filter
        if (!empty($request->service_id)) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('service_id', $request->service_id);
            });
        }

        // ✅ invoice date range
        if (!empty($request->from_invoice_date) && !empty($request->to_invoice_date)) {
            $query->whereBetween('invoice_date', [
                date('Y-m-d', strtotime($request->from_invoice_date)),
                date('Y-m-d', strtotime($request->to_invoice_date))
            ]);
        } elseif (!empty($request->from_invoice_date)) {
            $query->whereDate('invoice_date', '>=', date('Y-m-d', strtotime($request->from_invoice_date)));
        } elseif (!empty($request->to_invoice_date)) {
            $query->whereDate('invoice_date', '<=', date('Y-m-d', strtotime($request->to_invoice_date)));
        }

        // ✅ payment_status → invoices টেবিলে
        if (!empty($request->is_closed) &&  $request->is_closed == 1) {
            $query->where('is_closed', 1);
        }

        // ✅ field_name & value (যদি generic search থাকে)
        if (!empty($request->field_name) && !empty($request->value)) {
            $query->whereLike($request->field_name, $request->value);
        }

        if ($request->allData) {
            return $query->get();
        } else {
            $datas = $query->paginate($request->pagination);
            return new Resource($datas);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('layouts.backend_app');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->all();

            $data['client_id'] = $request->client['id'] ?? $request->client_id;

            $invoiceDetails = $data['invoice_details'] ?? [];
            $months = $request->invoice_months ?? [];

            // Payment receive parameters
            $isPaymentReceived = !empty($data['is_payment_received']);
            $paidAmount = floatval($data['paid_amount'] ?? 0);
            $paymentDate = !empty($data['payment_date']) ? vue_to_server_date($data['payment_date']) : null;
            $paymentMethod = $data['payment_method'] ?? 'Cash';
            $fundAccountId = $data['fund_account_id'] ?? 0;
            $trxid = $data['trxid'] ?? null;
            $mbankingType = $data['mbanking_type'] ?? null;
            $bankId = $data['bank_id'] ?? null;
            $chequeno = $data['chequeno'] ?? null;

            unset(
                $data['invoice_details'],
                $data['invoice_months'],
                $data['is_payment_received'],
                $data['payment_method'],
                $data['fund_account_id'],
                $data['mbanking_type'],
                $data['bank_id'],
                $data['chequeno'],
                $data['client'],
                $data['clientid'],
                $data['service_id']
            );

            $data['invoice_no'] = Invoice::generateInvoiceNumber();
            $data['invoice_date'] = vue_to_server_date($data['invoice_date']);

            if ($isPaymentReceived && $paidAmount > 0) {
                $data['paid_amount'] = $paidAmount;
                $data['is_closed'] = ($paidAmount >= floatval($data['amount'] ?? 0)) ? 1 : 0;
            }

            $invoice = Invoice::create($data);
            $account_id = Account::where('system_key_name')->first()->id;
            foreach ($invoiceDetails as $d) {
                $invoice->invoice_details()->create([
                    'account_id' => $account_id,
                    'description' => $d['description'],
                    'month_count' => $d['month_count'],
                    'user_count' => !empty($d['user_count']) ? $d['user_count'] : null,
                    'recharge_amount' => !empty($d['recharge_amount']) ? $d['recharge_amount'] : null,
                    'amount' => $d['amount'] ?? 0,
                    'total_amount' => $d['total_amount'] ?? 0,
                ]);
            }

            foreach ($months as $m) {
                if (!empty($m['checked'])) {
                    $invoice->invoice_months()->create([
                        'invoice_id' => $invoice->id,
                        'client_id' => $data['client_id'],
                        'invoice_month' => $m['value'] . '-01'
                    ]);
                }
            }

            // Create Payment & Payment Detail if Payment Received is checked
            if ($isPaymentReceived && $paidAmount > 0) {
                $paymentDate = $paymentDate ?: $invoice->invoice_date;

                $payment = Payment::create([
                    'payslipno'          => Payment::getPaySlipNo(),
                    'payment_type'       => 'Receive',
                    'client_id'          => $invoice->client_id,
                    'payment_date'       => $paymentDate,
                    'discount'           => 0,
                    'amount'             => $paidAmount,
                    'fund_account_id'    => $fundAccountId,
                    'payment_method'     => $paymentMethod,
                    'mbanking_type'      => $mbankingType,
                    'bank_id'            => $bankId,
                    'chequeno'           => $chequeno,
                    'trxid'              => $trxid,
                    'status'             => 'active',
                ]);

                $detailAccountId = $invoiceDetails[0]['account_id'] ?? $account_id;
                $payment->payment_details()->create([
                    'payment_id'     => $payment->id,
                    'reference_type' => 'Invoice',
                    'reference_id'   => $invoice->id,
                    'account_id'     => $detailAccountId,
                    'amount'         => $paidAmount,
                    'is_closed'      => $data['is_closed'] ?? 1,
                    'status'         => 'active',
                ]);

                // Create Voucher for payment
                $this->createVoucherFromPayment($payment, $payment->id);
            }

            // Auto Commission if Client recharge method is 'paybill'
            $this->handleCommissionForInvoice($invoice, $invoiceDetails);

            DB::commit();
            return $this->responseReturn("create", $invoice);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['exception' => $ex->getMessage()], 422);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $invoice = Invoice::with([
            'client.service',
            'client.package.unit',
            'invoice_details',
            'invoice_details.account',
            'invoice_months',
            'payment_details.payment',
        ])->find($id);
        // 🔹 saved months (YYYY-MM)
        $savedMonths = $invoice->invoice_months
            ->pluck('invoice_month')
            ->map(fn($m) => substr($m, 0, 7))
            ->toArray();

        // 🔹 generate 13 months window (same as Vue logic)
        $base = \Carbon\Carbon::parse($invoice->invoice_date);
        $months = [];

        for ($i = -6; $i <= 6; $i++) {
            $d = $base->copy()->addMonths($i);

            $value = $d->format('Y-m');
            $label = $d->format('F Y');

            $months[] = [
                'value'   => $value,
                'label'   => $label,
                'checked' => in_array($value, $savedMonths),
            ];
        }

        $paymentDetail = $invoice->payment_details->where('reference_type', 'Invoice')->first();
        $payment = $paymentDetail?->payment;

        return response()->json([
            'id'              => $invoice->id,
            'clientid'         => $invoice->clientid,
            'service_id'       => $invoice->client?->service_id,
            'client_id'        => $invoice->client_id,
            'original_amount' => $invoice->original_amount,
            'invoice_date'    => $invoice->invoice_date,
            'discount'        => $invoice->discount,
            'vat'             => $invoice->vat,
            'amount'          => $invoice->amount,
            'is_payment_received' => !empty($payment) || floatval($invoice->paid_amount) > 0,
            'payment_date'    => $payment ? ($payment->payment_date ?: $invoice->invoice_date) : $invoice->invoice_date,
            'paid_amount'     => $payment ? $payment->amount : ($invoice->paid_amount ?: 0),
            'payment_method'  => $payment ? $payment->payment_method : 'Cash',
            'fund_account_id' => $payment ? $payment->fund_account_id : null,
            'mbanking_type'   => $payment ? $payment->mbanking_type : null,
            'bank_id'         => $payment ? $payment->bank_id : null,
            'chequeno'        => $payment ? $payment->chequeno : '',
            'trxid'           => $payment ? $payment->trxid : '',
            'client'          => $invoice->client,
            'invoice_months'  => $months,
            'invoice_details' => $invoice->invoice_details->map(function ($row) {
                return [
                    'invoice_id'    => $row->invoice_id,
                    'account_id'    => $row->account_id,
                    'account'       => $row->account,
                    'description'   => $row->description,
                    'user_count'    => $row->user_count,
                    'recharge_amount' => $row->recharge_amount,
                    'amount'        => $row->amount,
                    'month_count'   => $row->month_count,
                    'total_amount'  => $row->total_amount,
                ];
            })->values(),
            'savedMonths'     => $savedMonths,
            'status'          => $invoice->status,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        return view('layouts.backend_app');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $invoice = Invoice::find($id);
        DB::beginTransaction();
        try {
            $data = $request->all();

            $details = $data['invoice_details'] ?? [];
            $months = $data['invoice_months'] ?? [];

            // Payment receive parameters
            $isPaymentReceived = !empty($data['is_payment_received']);
            $paidAmount = floatval($data['paid_amount'] ?? 0);
            $paymentDate = !empty($data['payment_date']) ? vue_to_server_date($data['payment_date']) : null;
            $paymentMethod = $data['payment_method'] ?? 'Cash';
            $fundAccountId = $data['fund_account_id'] ?? 0;
            $trxid = $data['trxid'] ?? null;
            $mbankingType = $data['mbanking_type'] ?? null;
            $bankId = $data['bank_id'] ?? null;
            $chequeno = $data['chequeno'] ?? null;

            unset(
                $data['invoice_details'],
                $data['invoice_months'],
                $data['is_payment_received'],
                $data['payment_method'],
                $data['fund_account_id'],
                $data['mbanking_type'],
                $data['bank_id'],
                $data['chequeno'],
                $data['client'],
                $data['clientid'],
                $data['service_id']
            );

            $data['invoice_date'] = vue_to_server_date($data['invoice_date']);

            if ($isPaymentReceived && $paidAmount > 0) {
                $data['paid_amount'] = $paidAmount;
                $data['is_closed'] = ($paidAmount >= floatval($data['amount'] ?? $invoice->amount)) ? 1 : 0;
            }

            $invoice->fill($data)->save();
            $invoice->touch(); // force updated event

            $invoice->invoice_details()->delete();
            $account_id = Account::where('system_key_name')->first()->id;
            foreach ($details as $d) {
                $invoice->invoice_details()->create([
                    'invoice_id' => $invoice->id,
                    'account_id' => $account_id,
                    'description' => $d['description'],
                    'month_count' => $d['month_count'],
                    'user_count' => !empty($d['user_count']) ? $d['user_count'] : null,
                    'recharge_amount' => !empty($d['recharge_amount']) ? $d['recharge_amount'] : null,
                    'amount' => $d['amount'] ?? 0,
                    'total_amount' => $d['total_amount'] ?? 0,
                ]);
            }

            $invoice->invoice_months()->delete();

            foreach ($months as $m) {
                if (!empty($m['checked'])) {
                    $invoice->invoice_months()->create([
                        'invoice_id' => $invoice->id,
                        'client_id' => $invoice->client_id,
                        'invoice_month' => $m['value'] . '-01'
                    ]);
                }
            }

            // Payment & Payment Detail handle
            $existingDetail = PaymentDetail::where('reference_type', 'Invoice')
                ->where('reference_id', $invoice->id)
                ->first();

            if ($isPaymentReceived && $paidAmount > 0) {
                $paymentDate = $paymentDate ?: $invoice->invoice_date;
                $isClosed = ($paidAmount >= floatval($invoice->amount)) ? 1 : 0;
                $detailAccountId = $details[0]['account_id'] ?? $account_id;

                if ($existingDetail && $existingDetail->payment) {
                    $payment = $existingDetail->payment;
                    $payment->update([
                        'payment_date'    => $paymentDate,
                        'amount'          => $paidAmount,
                        'fund_account_id' => $fundAccountId,
                        'payment_method'  => $paymentMethod,
                        'mbanking_type'   => $mbankingType,
                        'bank_id'         => $bankId,
                        'chequeno'        => $chequeno,
                        'trxid'           => $trxid,
                    ]);
                    $existingDetail->update([
                        'account_id' => $detailAccountId,
                        'amount'     => $paidAmount,
                        'is_closed'  => $isClosed,
                    ]);
                    $this->removeVoucherByPaymentID($payment->id);
                    $this->createVoucherFromPayment($payment, $payment->id);
                } else {
                    $payment = Payment::create([
                        'payslipno'          => Payment::getPaySlipNo(),
                        'payment_type'       => 'Receive',
                        'client_id'          => $invoice->client_id,
                        'payment_date'       => $paymentDate,
                        'discount'           => 0,
                        'amount'             => $paidAmount,
                        'fund_account_id'    => $fundAccountId,
                        'payment_method'     => $paymentMethod,
                        'mbanking_type'      => $mbankingType,
                        'bank_id'            => $bankId,
                        'chequeno'           => $chequeno,
                        'trxid'              => $trxid,
                        'status'             => 'active',
                    ]);
                    $payment->payment_details()->create([
                        'payment_id'     => $payment->id,
                        'reference_type' => 'Invoice',
                        'reference_id'   => $invoice->id,
                        'account_id'     => $detailAccountId,
                        'amount'         => $paidAmount,
                        'is_closed'      => $isClosed,
                        'status'         => 'active',
                    ]);
                    $this->createVoucherFromPayment($payment, $payment->id);
                }
            }

            // Auto Commission if Client recharge method is 'paybill'
            $this->handleCommissionForInvoice($invoice, $details);

            DB::commit();
            return $this->responseReturn("update", $invoice);
        } catch (\Exception $ex) {
            DB::rollBack();
            return response()->json(['exception' => $ex->getMessage()], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $invoice = Invoice::find($id);
        DB::beginTransaction();

        try {

            // =========================
            // 1️⃣ Check Eligibility (any payment exists?)
            // =========================
            $hasPayment = PaymentDetail::where('reference_type', 'Invoice')
                ->where('reference_id', $invoice->id)
                ->exists();

            if ($hasPayment) {
                return response()->json([
                    'message' => 'This invoice has payments. Delete is not allowed.'
                ], 422);
            }

            // =========================
            // 2️⃣ Delete related vouchers
            // =========================
            $vouchers = Voucher::where('source', 'Invoice')
                ->where('source_id', $invoice->id)
                ->get();

            foreach ($vouchers as $voucher) {
                VoucherDetail::where('voucher_id', $voucher->id)->delete(); // soft delete
                $voucher->delete(); // soft delete
            }

            // =========================
            // 3️⃣ Delete invoice details
            // =========================
            $invoice->invoice_details()->delete(); // soft delete

            // =========================
            // 4️⃣ Delete invoice (soft)
            // =========================
            $invoice->delete();

            // =========================
            // 5️⃣ Remove auto commission if exists for this invoice
            // =========================
            $commissions = Commission::where('invoice_id', $invoice->id)
                ->orWhere('remarks', 'like', '%Invoice #' . $invoice->invoice_no . '%')
                ->get();
            foreach ($commissions as $comm) {
                if (!$comm->is_closed) {
                    $source = ['source' => 'Commission', 'source_id' => $comm->id];
                    $this->removeVoucherBySourceInfo($source);
                    $comm->delete();
                }
            }

            DB::commit();

            return $this->responseReturn("delete", true);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'exception' => $e->getMessage()
            ], 422);
        }
    }

    public function paynow($invoiceid)
    {
        if (!$invoiceid) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ID not provided'
            ], 400);
        }

        $invoice = Invoice::find($invoiceid);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        try {
            $invoice->paid_amount = $invoice->amount;
            $invoice->payment_date = date('Y-m-d');
            $invoice->card_type = 'cash';
            $invoice->payment_status = 'paid';
            $invoice->save();

            return response()->json([
                'success' => true,
                'message' => 'Cash Payment completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    public function months(Invoice $invoice)
    {
        return InvoiceMonth::where('invoice_id', $invoice->id)
            ->pluck('invoice_month')
            ->map(fn($m) => substr($m, 0, 7))
            ->toArray();
    }


    public function bill(Request $request, $invoiceid)
    {
        if ($request->format() == 'html') {
            return view('admin.layouts.admin_app');
        }
        $invoice = Invoice::with(
            [
                'client.service',
                'client.package.unit',
                'invoice_details.account',
                'invoice_months'
            ]
        )->where('id', $invoiceid)->first();

        if ($invoice) {
            $client_id = $invoice->client_id;
            $previous_due = $this->previousPackageDue($client_id, $invoice->id, $invoice->invoice_date);
            $invoice->previous_due = $previous_due;
        }

        return $invoice->toArray();
    }

    public function moneyreceipt(Request $request, $invoiceid)
    {
        if ($request->format() == 'html') {
            return view('admin.layouts.admin_app');
        }

        $invoice = Invoice::with(
            [
                'client:id,name,org_name,mobile,email,address',
                'invoice_details.account:id,account_name',
                'payment_details.payment:payslipno,payment_date'
            ]
        )->where('id', $invoiceid)->first();
        return $invoice;
    }

    public function generateInvoice()
    {
        DB::beginTransaction();

        try {
            $this->autoInvoiceForPackage();
            $this->autoInvoiceForBandwidth();

            DB::commit();
            return $this->responseReturn("create", true);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Invoice generation failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Handle auto commission for client with paybill recharge method
     */
    protected function handleCommissionForInvoice(Invoice $invoice, array $invoiceDetails)
    {
        try {
            if (empty($invoice->client_id)) {
                return;
            }

            $client = Client::with('client_packages')->find($invoice->client_id);
            if (!$client) {
                return;
            }

            // Check if recharge method is 'paybill'
            $rechargeMethod = strtolower($client->recharge_model ?? $client->recharge_method ?? '');
            if ($rechargeMethod !== 'paybill') {
                return;
            }

            // Get commission percentage
            $percentage = floatval($client->percentage ?? 0);
            if ($percentage <= 0 && $client->client_packages && $client->client_packages->isNotEmpty()) {
                $percentage = floatval($client->client_packages->first()->percentage ?? 0);
            }

            if ($percentage <= 0) {
                return;
            }

            // Calculate commission base amount
            $baseAmount = 0;
            foreach ($invoiceDetails as $d) {
                if (!empty($d['recharge_amount']) && floatval($d['recharge_amount']) > 0) {
                    $months = !empty($d['month_count']) ? intval($d['month_count']) : 1;
                    $baseAmount += floatval($d['recharge_amount']) * $months;
                } else {
                    $baseAmount += floatval($d['total_amount'] ?? $d['amount'] ?? 0);
                }
            }

            if ($baseAmount <= 0) {
                $baseAmount = floatval($invoice->amount ?? 0);
            }

            if ($baseAmount <= 0) {
                return;
            }

            $commissionAmount = round(($baseAmount * $percentage) / 100, 2);
            if ($commissionAmount <= 0) {
                return;
            }

            $adminId = Auth::guard('admin')->id() ?? 1;

            // Check if commission already exists for this invoice (e.g. on update)
            $existingCommission = Commission::where('invoice_id', $invoice->id)->first();
            if (!$existingCommission) {
                $existingCommission = Commission::where('client_id', $client->id)
                    ->where('remarks', 'like', '%Invoice #' . $invoice->invoice_no . '%')
                    ->first();
            }

            if ($existingCommission) {
                if ($existingCommission->is_closed != 1) {
                    $existingCommission->invoice_id = $invoice->id;
                    $existingCommission->amount = $commissionAmount;
                    $existingCommission->percentage = $percentage;
                    $existingCommission->save();
                }
                return;
            }

            // Create Commission record
            $commission = Commission::create([
                'client_id'        => $client->id,
                'invoice_id'       => $invoice->id,
                'is_employee'      => !empty($client->employee_id) ? 1 : 0,
                'employee_id'      => $client->employee_id ?: null,
                'service_id'       => $client->service_id ?? $invoice->service_id,
                'package_id'       => $client->package_id ?: ($invoiceDetails[0]['package_id'] ?? null),
                'reference_name'   => $client->ref_name ?? $client->name,
                'reference_mobile' => $client->mobile ?? null,
                'reference_address'=> $client->address ?? null,
                'percentage'       => $percentage,
                'amount'           => $commissionAmount,
                'remarks'          => 'Auto commission from Invoice #' . $invoice->invoice_no,
                'is_closed'        => 0,
                'status'           => 'active',
                'created_by'       => $adminId,
            ]);

            // Auto approve
            $commission->approved_by   = $adminId;
            $commission->approved_date = date('Y-m-d');
            $commission->save();
        } catch (\Exception $e) {
            \Log::error('Commission auto create failed for invoice #' . ($invoice->invoice_no ?? '') . ': ' . $e->getMessage());
        }
    }

    /**
     * Validate form field.
     *
     * @return \Illuminate\Http\Response
     */
    public function validateCheck($request, $id = null)
    {
        return true;
        return $request->validate([
            //ex: 'name' => 'required|email|nullable|date|string|min:0|max:191',
        ], [
            //ex: 'name' => "This name is required" (custom message)
        ]);
    }
}
