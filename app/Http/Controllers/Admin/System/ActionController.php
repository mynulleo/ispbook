<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use App\Traits\VoucherTrait;


class ActionController extends Controller
{
    use VoucherTrait;

    public function regenerateVoucherForInvoice()
    {
        DB::beginTransaction();

        try {

            // 🔹 Get invoices where voucher not created
            $invoices = DB::table('invoices as i')
                ->whereNull('i.deleted_at')
                ->where('i.status', 'active')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('vouchers as v')
                        ->whereColumn('v.source_id', 'i.id')
                        ->where('v.source', 'Invoice')
                        ->whereNull('v.deleted_at')
                        ->where('v.status', 'active');
                })
                ->select('i.id', 'i.client_id', 'i.invoice_date', 'i.amount')
                ->get();

            $count = 0;

            foreach ($invoices as $invoice) {

                $vdata = [
                    'module'    => 'Invoice',
                    'date'      => vue_to_server_date($invoice->invoice_date),
                    'amount'    => $invoice->amount,
                    'source_id' => $invoice->id,
                    'ref_id'    => $invoice->client_id,
                ];

                $this->createReceivableVoucher($vdata);

                $count++;
            }

            DB::commit();

            // return "Total Voucher Created: " . $count;
            return response()->json([
                'type'    => 'success',
                'message' => 'Total Voucher Created:' . $count
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return $e->getMessage();
        }
    }

    public function regenerateVoucherForBandwidth()
    {
        DB::beginTransaction();

        try {
            // 🔹 Get bandwidth_histories where voucher not created
            $bandwidth_histories = DB::table('bandwidth_histories as b')
                ->whereNull('b.deleted_at')
                ->where('b.status', 'active')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('vouchers as v')
                        ->whereColumn('v.source_id', 'b.id')
                        ->where('v.source', 'BandwidthHistory')
                        ->whereNull('v.deleted_at')
                        ->where('v.status', 'active');
                })
                ->select('b.id', 'b.client_id', 'b.invoice_date', 'b.amount')
                ->get();

            $count = 0;
            foreach ($bandwidth_histories as $record) {
                $vdata = [
                    'module'    => 'Invoice',
                    'date'      => vue_to_server_date($record->transaction_date),
                    'amount'    => $record->total_include_amount,
                    'source_id' => $record->id,
                    'ref_id'    => $record->client_id,
                ];
                $this->createReceivableVoucher($vdata);
                $count++;
            }

            DB::commit();
            // return "Total Voucher Created: " . $count;
            return response()->json([
                'type'    => 'success',
                'message' => 'Total Voucher Created:' . $count
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return $e->getMessage();
        }
    }

    public function regeneratePaymentVoucher()
    {
        DB::beginTransaction();

        try {
            // 🔹 Get payments where voucher not created
            $payments = DB::table('payments as p')
                ->whereNull('p.deleted_at')
                ->where('p.status', 'active')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('vouchers as v')
                        ->whereColumn('v.payment_id', 'p.id')
                        ->whereNull('v.deleted_at')
                        ->where('v.status', 'active');
                })
                ->select('p.*')
                ->get();

            $count = 0;
            foreach ($payments as $record) {

                if (!empty($record->employee_id)) {
                    $this->createEmployeeVoucher($record, $record->id);
                } else {
                    $this->createVoucherFromPayment($record, $record->id);
                }

                $count++;
            }

            DB::commit();
            // return "Total Voucher Created: " . $count;
            return response()->json([
                'type'    => 'success',
                'message' => 'Total Voucher Created:' . $count
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return $e->getMessage();
        }
    }
}
