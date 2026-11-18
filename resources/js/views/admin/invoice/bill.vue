<template>
    <view-page printArea="invoice_print">
        <div class="container my-5">
            <!-- <pre>{{ data }}</pre> -->
            <div class="mb-1">
                <SwitchBoolean v-model='invoice_from' field='invoice_from' col="3" title='Display Org Info? '
                    on-label='Yes' off-label='No'></SwitchBoolean>
            </div>
            <div class="container my-3 invoice-wrapper" id="invoice_print">

                <div class="invoice-box shadow-sm p-3">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col-md-12 col-sm-12 text-md-center">
                            <h2 class="invoice-title">INVOICE</h2>
                        </div>
                        <div class="col-md-6 col-sm-6">
                            <div class="invoice-from" v-if="invoice_from">
                                <img :src="site.logo_two" alt="" style="width:150px">
                                <p class="mb-0"><strong>{{ site.title }}</strong></p>
                                <p class="mb-0">{{ site.address }}</p>
                                <p class="mb-0">Phone: {{ site.mobile1 }}</p>
                                <p>{{ site.email }}</p>
                            </div>
                        </div>
                        <div class="col-md-6 col-sm-6 ">
                            <div class="invoice-to text-md-end">
                                <h6 class="fw-bold">Bill To:</h6>
                                <p class="mb-0" v-if="data.client?.service_id == 5">{{ data.client?.org_name }}</p>
                                <p class="mb-0">{{ data.client?.name }}</p>
                                <p class="mb-0">{{ data.client?.address }}</p>
                                <p class="mb-0">Phone: {{ data.client?.mobile }}</p>
                                <p class="mb-0">Email: {{ data.client?.email }}</p>
                                <p class="mb-0" v-if="data.client?.service?.title">
                                    <span class="badge bg-secondary">{{ data.client?.service?.title }}</span>
                                </p>
                                <p class="mb-0" v-if="data.client?.service_id == 6 && (data.client?.recharge_model || data.client?.recharge_method)">
                                    <small class="text-muted">Recharge Method: </small>
                                    <strong class="text-uppercase">{{ data.client?.recharge_model || data.client?.recharge_method }}</strong>
                                    <span v-if="(data.client?.recharge_model || data.client?.recharge_method) === 'commission' && data.client?.percentage" class="text-primary fw-bold">
                                        ({{ data.client?.percentage }}%)
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Invoice #:</strong> {{ data.invoice_no }}</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1"><strong>Date:</strong> {{ data.invoice_date }}</p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle table-sm">
                            <thead class="text-center table-light">
                                <tr>
                                    <th style="width: 5%">#</th>
                                    <th>Description</th>
                                    <th class="text-center" style="width: 12%" v-if="data.client?.service_id == 6">Total User</th>
                                    <th class="text-end" style="width: 16%" v-if="data.client?.service_id == 6 && hasRechargeAmount">Recharge Amount<br>(৳)</th>
                                    <th class="text-end" style="width: 14%">Unit Price <br>(৳)</th>
                                    <th class="text-center" style="width: 10%">Month</th>
                                    <th class="text-end" style="width: 16%">Amount <br>(৳)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="(invd, index) in data.invoice_details" :key="index">
                                    <tr>
                                        <td class="text-center">{{ index + 1 }}</td>
                                        <td style="white-space: pre-line;">{{ invd.description }}</td>
                                        <td class="text-center" v-if="data.client?.service_id == 6">{{ invd.user_count ?? '-' }}</td>
                                        <td class="text-end" v-if="data.client?.service_id == 6 && hasRechargeAmount">
                                            {{ invd.recharge_amount ? $filter.money(invd.recharge_amount) : '-' }}
                                        </td>
                                        <td class="text-end">{{ $filter.money(invd.amount) }}</td>
                                        <td class="text-center">{{ invd.month_count }}</td>
                                        <td class="text-end">{{ $filter.money(invd.total_amount) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary -->
                    <div class="row justify-content-end mt-3">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <th>Subtotal</th>
                                        <td class="text-end">৳ {{ $filter.money(data.original_amount) }}</td>
                                    </tr>
                                    <tr v-if="Number(data.discount) > 0">
                                        <th>Discount</th>
                                        <td class="text-end">৳ {{ $filter.money(data.discount) }}</td>
                                    </tr>
                                    <tr v-if="Number(data.vat) > 0">
                                        <th>Vat ({{ data.vat }}%)</th>
                                        <td class="text-end">৳ {{ $filter.money(calculatedVatAmount) }}</td>
                                    </tr>
                                    <tr class="fw-bold table-light">
                                        <th>Total</th>
                                        <td class="text-end">৳ {{ $filter.money(data.amount) }}</td>
                                    </tr>
                                    <tr class="fw-bold" v-if="Number(data.previous_due) > 0">
                                        <th>Previous Due</th>
                                        <td class="text-end">৳ {{ $filter.money(data.previous_due) }}</td>
                                    </tr>
                                    <tr class="fw-bold table-active" v-if="Number(data.previous_due) > 0">
                                        <th>Grand Total</th>
                                        <td class="text-end">
                                            ৳ {{ $filter.money(Number(data.amount) + Number(data.previous_due)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <p class="mb-1"><strong>Payment Method:</strong> Bank / Cash / Mobile Banking</p>
                            <p class="text-muted">Thank you for your business!</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </view-page>
</template>

<script>
const model = "invoice";

export default {
    data() {
        return {
            page_title: "Invoice",
            model,
            data: {},
            print_area: "my_print",
            invoice_from: 1
        };
    },
    computed: {
        totalAmount() {
            if (!this.data.invoice_details) return 0;
            return this.data.invoice_details.reduce((sum, acd) => sum + parseFloat(acd.amount || 0), 0);
        },
        hasRechargeAmount() {
            return this.data.invoice_details?.some(
                d => d.recharge_amount !== null && d.recharge_amount !== undefined && d.recharge_amount !== '' && parseFloat(d.recharge_amount) > 0
            ) || !!this.data.client?.recharge_model;
        },
        calculatedVatAmount() {
            const vatPct = parseFloat(this.data.vat) || 0;
            if (vatPct <= 0) return 0;
            const subtotal = parseFloat(this.data.original_amount) || 0;
            const discount = parseFloat(this.data.discount) || 0;
            const taxable = subtotal - discount;
            return (taxable * vatPct) / 100;
        },
    },
    created() {
        this.page_title = "Invoice";
        this.get_data(`${this.model}/bill/${this.$route.params.id}`);
    },
    methods: {
        async paynow() {
            if (!this.data.id) {
                this.$toast("Invoice ID not found", "warning");
                return;
            }
            try {
                const response = await axios.get(`/paynow/${this.data.id}`);
                if (response.data.success) {
                    this.get_data(`${this.model}/bill/${this.$route.params.id}`);
                    this.$toast(response.data.message, "success");
                } else {
                    this.$toast(response.data.message || "Payment failed", "error");
                }
            } catch (error) {
                const msg = error.response?.data?.message || "Error processing payment";
                this.$toast(msg, "error");
            }
        },
    },
};
</script>

<style scoped>
.invoice-wrapper {
    max-width: 780px;
}

.to-section {
    width: 180px;
    float: right
}

.table-bill th {
    background-color: #112C47;
    color: #fff;
}

@media (max-width: 576px) {
    .invoice-header {
        text-align: center !important;
        flex-direction: column;
    }

    .invoice-header .logo-section,
    .invoice-header .title-section,
    .invoice-header .info-section {
        flex: 100%;
        text-align: center !important;
    }

    .invoice-header .info-section {
        margin-top: 10px;
    }

    .to-section {
        float: left
    }
}

@media print {
    .table-bill th {
        background-color: #112C47 !important;
        color: #fff;
    }
}
</style>
