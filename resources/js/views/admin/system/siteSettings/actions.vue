<template>
    <div class="container mt-5">
        <div class="row mb-4">
            <div class="col-md-12">
                <h3 class="page-title">
                    ⚙️ System Maintenance Actions
                </h3>
                <p class="text-muted">
                    Run system maintenance tasks safely. These operations affect accounting and system data.
                </p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6">
                <fieldset>
                    <span class="legend">Generate Auto Invoice</span>
                    <p class="text-muted mt-3">
                        Automatically generate invoices for all active clients based on service configuration.
                    </p>
                    <button class="btn btn-primary action-btn mt-3" @click="generateInvoice">
                        Generate Invoice
                    </button>
                </fieldset>
            </div>
            <div class="col-md-6">
                <fieldset>
                    <span class="legend">Re-Generate Voucher For Invoice</span>
                    <p class="text-muted mt-3">
                        Create missing accounting vouchers for invoices. Useful when vouchers were not generated due to
                        system errors.
                    </p>
                    <button class="btn btn-success action-btn mt-3" @click="regenerateVoucherForInvoice">
                        Re-Generate Invoice Voucher
                    </button>
                </fieldset>
            </div>
            <div class="col-md-6">
                <fieldset>
                    <span class="legend">Re-Generate Voucher For Bandwidth</span>
                    <p class="text-muted mt-3">
                        Create missing accounting vouchers for bandwidth module. Useful when vouchers were not generated
                        due to
                        system errors.
                    </p>
                    <button class="btn btn-info action-btn text-white mt-3" @click="regenerateVoucherForBandwidth">
                        Re-Generate Bandwidth Voucher
                    </button>
                </fieldset>
            </div>
            <div class="col-md-6">
                <fieldset>
                    <span class="legend">Re-Generate Payment Voucher</span>
                    <p class="text-muted mt-3">
                        Create missing accounting vouchers for payment module. Useful when vouchers were not generated
                        due to
                        system errors.
                    </p>
                    <button class="btn btn-warning action-btn text-white mt-3" @click="regeneratePaymentVoucher">
                        Re-Generate Payment Voucher
                    </button>
                </fieldset>
            </div>
        </div>
    </div>
</template>
<script>

export default {
    name: "ResidenceOwnerDashboard",
    data() {
        return {};
    },

    methods: {
        generateInvoice() {
            axios.post(`generate-invoice`).then(() => {
                this.$toast("Successfully generated invoices", "success");
            });
        },

        regenerateVoucherForInvoice() {
            axios.get(`regeneratevoucherforinvoice`).then(() => {
                this.$toast("Successfully re-generated voucher", "success");
            });
        },

        regenerateVoucherForBandwidth() {
            axios.get(`regeneratevoucherforbandwidth`).then(() => {
                this.$toast("Successfully re-generated voucher for bandwidth", "success");
            });
        },
        regeneratePaymentVoucher() {
            axios.get(`regenerate-payment-voucher`).then(() => {
                this.$toast("Successfully re-generated voucher for payment", "success");
            });
        },


    },
};
</script>