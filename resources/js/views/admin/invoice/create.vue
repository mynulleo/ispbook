<template>
    <div class="row">
        <create-form @onSubmit="submit">
            <!-- Left Column: Form Controls -->
            <div class="col-md-8 mb-5">
                <div class="row g-4">
                    <!-- Invoice Setup Card -->
                    <div class="col-md-12">
                        <fieldset class="pro-fieldset">
                            <span class="legend">
                                <i class="fa-solid fa-file-invoice-dollar text-primary me-1"></i> Invoice Setup
                            </span>
                            <div class="row g-3">
                                <Input v-model="data.clientid" field="data.residenceno" title="Client ID" :req="true"
                                    placeholder="Enter ID & press Enter"
                                    :disabled="this.$route.params.id ? true : false" @keyup.enter="fetchClientData" col="3" />
                                <Select title='Service' v-model='data.service_id' field='data.service_id' col="3"
                                    label='title' :reduce='(obj) => obj.id' :options='$root.global.services'
                                    placeholder='--Select One--' :closeOnSelect='true' :required='false'
                                    :disabled="this.$route.params.id ? true : false" />
                                <Select title='Client' v-model='data.client_id' field='data.client_id' col="3"
                                    label='name' :reduce='(obj) => obj.id' :options='clients'
                                    placeholder='--Select One--' @change="fetchClientData" :closeOnSelect='true'
                                    :required='false' :disabled="this.$route.params.id ? true : false" />
                                <date-picker id="date2" v-model="data.invoice_date" field="data.invoice_date"
                                    title="Invoice Date" placeholder="Invoice Date" col="3" :req="false" />

                                <!-- Financials Row -->
                                <div class="col-md-12 my-1">
                                    <div class="financials-card p-3 rounded-3 bg-light border">
                                        <div class="row g-3 align-items-center">
                                            <Input v-model="data.original_amount" field="data.original_amount" title="Sub Total (৳)"
                                                col="3" :readonly="true" :req="true" />
                                            <Input v-model="data.discount" field="data.discount" @change="recalculateTotals()"
                                                title="Discount (৳)" col="3" />
                                            <Input v-model="data.vat" field="data.vat" @change="recalculateTotals()"
                                                title="VAT (%)" col="2" />
                                            <Input v-model="data.amount" field="data.amount" title="Total Amount (৳)"
                                                col="4" :req="true" :readonly="true" class="fw-bold" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Receive Switch & Panel -->
                                <div class="col-md-12 mt-2">
                                    <div class="payment-receive-box rounded-3 p-3 transition-all"
                                        :class="data.is_payment_received ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : 'bg-light border'">
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle p-2 d-flex align-items-center justify-content-center"
                                                    :class="data.is_payment_received ? 'bg-success text-white' : 'bg-secondary bg-opacity-25 text-secondary'"
                                                    style="width: 32px; height: 32px;">
                                                    <i class="fa-solid fa-money-bill-transfer"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark fs-6">Receive Payment</span>
                                                    <span class="text-muted small ms-2">
                                                        {{ data.is_payment_received ? 'Payment & voucher will be auto-generated' : 'Receive instant payment for this invoice?' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <SwitchBoolean v-model="data.is_payment_received" field="data.is_payment_received"
                                                    on-label="Yes" off-label="No"
                                                    @change="onPaymentReceiveToggle" />
                                            </div>
                                        </div>

                                        <!-- Payment Receive Fields -->
                                        <div v-if="data.is_payment_received" class="row g-3 pt-3 mt-1 border-top border-success border-opacity-25">
                                            <date-picker id="payment_date" v-model="data.payment_date"
                                                field="data.payment_date" title="Payment Date" placeholder="Payment Date"
                                                col="3" :req="false" />

                                            <Input v-model="data.paid_amount" field="data.paid_amount"
                                                col="3" title="Received Amount (৳)" :req="false" />

                                            <Select title="Payment Method" v-model="data.payment_method"
                                                field="data.payment_method" col="3" label="name"
                                                :reduce="(obj) => obj.value" :options="$root.global.paymentmethods"
                                                placeholder="--Select Method--" :closeOnSelect="true" :required="false" />

                                            <Select title="Fund Account" v-model="data.fund_account_id"
                                                field="data.fund_account_id" col="3" label="name"
                                                :reduce="(obj) => obj.id" :options="fundaccounts"
                                                placeholder="--Select Fund Account--" :closeOnSelect="true" :required="false" />

                                            <Select v-if="data.payment_method === 'MobileBanking'" title="Mbanking Type"
                                                v-model="data.mbanking_type" field="data.mbanking_type" col="3"
                                                label="name" :reduce="(obj) => obj.value" :options="$root.global.mbankings"
                                                placeholder="--Select Type--" :closeOnSelect="true" :required="false" />

                                            <Select v-if="data.payment_method === 'BankTransfer' || data.payment_method === 'Bank' || data.payment_method === 'Cheque'"
                                                title="Bank" v-model="data.bank_id" field="data.bank_id"
                                                label="bank_name" :reduce="(obj) => obj.id" col="3"
                                                :options="banks" placeholder="--Select Bank--" :closeOnSelect="true" :required="false" />

                                            <Input v-if="data.payment_method === 'Cheque'" v-model="data.chequeno"
                                                field="data.chequeno" title="Cheque No" col="3" :req="false" />

                                            <Input v-model="data.trxid" field="data.trxid"
                                                title="Trx ID / Ref" col="3" :req="false" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Billing Months Section -->
                    <div class="col-md-12">
                        <fieldset class="pro-fieldset">
                            <span class="legend">
                                <i class="fa-regular fa-calendar-check text-info me-1"></i> Billing Months
                            </span>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small text-secondary fw-semibold">Applicable months:</span>
                                    <span class="badge bg-primary rounded-pill">{{ selectedMonthsCount }} selected</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm px-2 py-1"
                                        @click="selectCurrentMonthOnly" title="Select current month">
                                        <i class="fa-solid fa-calendar-day me-1"></i> Current Month
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-1"
                                        @click="clearAllMonths" title="Clear all selected months">
                                        <i class="fa-solid fa-xmark me-1"></i> Clear
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <div
                                    v-for="(m, index) in data.invoice_months"
                                    :key="index"
                                    class="month-chip user-select-none"
                                    :class="m.checked ? 'active' : ''"
                                    @click="toggleMonth(m)"
                                >
                                    <i :class="m.checked ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle text-muted'" class="me-1"></i>
                                    <span>{{ m.label }}</span>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Invoice Details (Line Items) -->
                    <div class="col-md-12">
                        <fieldset class="pro-fieldset">
                            <span class="legend">
                                <i class="fa-solid fa-list-check text-warning me-1"></i> Invoice Details
                            </span>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0 pro-table">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th style="width: 85px;" class="text-center">Months</th>
                                            <th style="width: 120px;" v-if="data.service_id == 6">Total User</th>
                                            <th style="width: 150px;" v-if="data.service_id == 6">Recharge Amount</th>
                                            <th style="width: 180px;" v-if="data.service_id != 6">Package</th>
                                            <th style="width: 120px;">Amount</th>
                                            <th style="width: 130px;">Total</th>
                                            <th style="width: 80px;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(invdetail, index) in data.invoice_details" :key="index">
                                            <td>
                                                <Textarea v-model="invdetail.description"
                                                    field="invdetail.description" :required="false" col="12" />
                                            </td>
                                            <td>
                                                <Input v-model="invdetail.month_count" field="invdetail.month_count"
                                                    col="12" :readonly="true" />
                                            </td>
                                            <td v-if="data.service_id == 6">
                                                <Input v-model="invdetail.user_count" field="invdetail.user_count"
                                                    placeholder="Users" col="12" :req="false" />
                                            </td>
                                            <td v-if="data.service_id == 6">
                                                <Input v-model="invdetail.recharge_amount" field="invdetail.recharge_amount"
                                                    placeholder="Recharge" col="12" @input="onRechargeAmountChange(invdetail)" />
                                            </td>
                                            <td v-if="data.service_id != 6">
                                                <Select v-model="invdetail.package_id" field="invdetail.package_id"
                                                    :options="client_packages" label="title" :reduce="(obj) => obj.package_id"
                                                    placeholder="--Select Package--" col="12" :req="false"
                                                    @change="onPackageChange(invdetail)" />
                                            </td>
                                            <td>
                                                <Input v-model="invdetail.amount" field="invdetail.amount" col="12"
                                                    @change="recalculateRow(invdetail)" />
                                            </td>
                                            <td>
                                                <Input v-model="invdetail.total_amount"
                                                    field="invdetail.total_amount" col="12" :readonly="false" />
                                            </td>
                                            <td>
                                                <div class="multiple_fields_actions_btn d-flex align-items-center justify-content-center gap-1">
                                                    <button type="button" class="btns delete_one"
                                                        v-if="data.invoice_details.length > 1"
                                                        @click.prevent="removeInvoiceDetails(index)" title="Delete Row">
                                                        <span class="icon"><i class="fas fa-trash"></i></span>
                                                    </button>

                                                    <button v-if="isLastItem(data.invoice_details, index)"
                                                        type="button" class="btns add_more"
                                                        @click.prevent="addInvoiceDetailsRow()" title="Add Row">
                                                        <span class="icon"><i class="fas fa-plus"></i></span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-light fw-semibold">
                                            <td :colspan="data.service_id == 6 ? 4 : 3" class="text-end small py-2">
                                                Items: {{ data.invoice_details.length }}
                                            </td>
                                            <td class="text-end small py-2">Sub Total:</td>
                                            <td class="py-2 text-primary fw-bold">{{ formatMoney(data.original_amount) }}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Status -->
                    <div class="col-md-12">
                        <div class="card p-3 border shadow-sm rounded-3 bg-white d-flex flex-row align-items-center justify-content-between">
                            <div>
                                <span class="fw-semibold text-dark">Invoice Status</span>
                                <div class="text-muted small">Set active or deactive status for this invoice</div>
                            </div>
                            <Switch v-model="data.status" field="data.status" title="" on-label="Active"
                                off-label="Deactive" :req="true" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Client Info Sidebar -->
            <div class="col-md-4 mb-5">
                <fieldset class="pro-fieldset sticky-top" style="top: 80px; z-index: 10;">
                    <span class="legend">
                        <i class="fa-solid fa-user-check text-primary me-1"></i> Client Information
                    </span>

                    <template v-if="data?.client?.id || data?.client?.name">
                        <!-- Profile Card Header -->
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-3 border">
                            <div class="avatar-circle bg-primary bg-opacity-10 text-primary fw-bold fs-5 d-flex align-items-center justify-content-center rounded-circle" style="width: 46px; height: 46px; min-width: 46px;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="fw-bold mb-0 text-dark text-truncate">{{ data?.client?.name }}</h6>
                                <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                                    <span class="badge bg-secondary">{{ data?.client?.clientid || data.clientid || 'ID: --' }}</span>
                                    <span class="text-truncate">{{ data?.client?.org_name || data?.client?.service?.title }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Recharge Method & Auto-Commission Callout -->
                        <div class="mb-3 p-2 rounded-2 border" :class="clientRechargeMethod === 'commission' ? 'bg-warning bg-opacity-10 border-warning border-opacity-25' : 'bg-primary bg-opacity-10 border-primary border-opacity-25'">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold text-secondary">Recharge Method:</span>
                                <span :class="clientRechargeMethod === 'commission' ? 'badge bg-warning text-dark text-uppercase fw-bold' : 'badge bg-primary text-uppercase fw-bold'">
                                    {{ clientRechargeMethod }}
                                </span>
                            </div>
                            <div v-if="clientPercentage > 0" class="d-flex justify-content-between align-items-center small">
                                <span class="text-secondary fw-medium">
                                    <i class="fa-solid fa-percent text-success me-1"></i> Commission:
                                </span>
                                <span class="fw-bold text-primary">{{ clientPercentage }}%</span>
                            </div>
                            <div v-if="clientRechargeMethod === 'paybill' && clientPercentage > 0" class="mt-2 pt-2 border-top text-muted" style="font-size: 11px;">
                                <i class="fa-solid fa-wand-magic-sparkles text-success me-1"></i> <strong>Auto-Commission:</strong> {{ clientPercentage }}% commission will be auto-calculated & approved in commissions module.
                            </div>
                        </div>

                        <!-- Package & Pricing Specs -->
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr class="border-bottom">
                                        <td class="text-secondary small py-2"><i class="fa-solid fa-network-wired text-muted me-2"></i>Service</td>
                                        <td class="text-end fw-semibold text-dark py-2">{{ data?.client?.service?.title || '-' }}</td>
                                    </tr>
                                    <tr class="border-bottom" v-if="data?.client?.package?.title">
                                        <td class="text-secondary small py-2"><i class="fa-solid fa-cube text-muted me-2"></i>Package</td>
                                        <td class="text-end fw-semibold text-dark py-2">{{ data?.client?.package?.title }}</td>
                                    </tr>
                                    <tr class="border-bottom" v-if="data?.client?.package?.bandwidth">
                                        <td class="text-secondary small py-2"><i class="fa-solid fa-gauge-high text-muted me-2"></i>Bandwidth</td>
                                        <td class="text-end fw-semibold text-dark py-2">
                                            {{ data?.client?.package?.bandwidth }} {{ data?.client?.package?.unit?.title }}
                                        </td>
                                    </tr>
                                    <tr class="border-bottom" v-if="data?.client?.package?.price">
                                        <td class="text-secondary small py-2"><i class="fa-solid fa-tag text-muted me-2"></i>Price</td>
                                        <td class="text-end fw-semibold text-dark py-2">{{ formatMoney(data?.client?.package?.price) }}</td>
                                    </tr>
                                    <tr class="border-bottom" v-if="data?.client?.package?.vat">
                                        <td class="text-secondary small py-2"><i class="fa-solid fa-receipt text-muted me-2"></i>VAT</td>
                                        <td class="text-end fw-semibold text-dark py-2">{{ formatMoney(data?.client?.package?.vat) }}</td>
                                    </tr>
                                    <tr class="bg-light rounded" v-if="data?.client?.package?.price">
                                        <td class="text-dark fw-bold small py-2 ps-2">Total Price</td>
                                        <td class="text-end fw-bold text-primary py-2 pe-2">
                                            {{ formatMoney(parseFloat(data?.client?.package?.price || 0) + parseFloat(data?.client?.package?.vat || 0)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Contact Details -->
                        <div class="border-top pt-2">
                            <div v-if="data?.client?.mobile" class="d-flex align-items-center gap-2 text-secondary small py-1">
                                <i class="fa-solid fa-phone text-muted" style="width: 16px;"></i>
                                <span class="fw-medium text-dark">{{ data?.client?.mobile }}</span>
                            </div>
                            <div v-if="data?.client?.email" class="d-flex align-items-center gap-2 text-secondary small py-1">
                                <i class="fa-solid fa-envelope text-muted" style="width: 16px;"></i>
                                <span class="fw-medium text-dark text-truncate">{{ data?.client?.email }}</span>
                            </div>
                            <div v-if="data?.client?.address" class="d-flex align-items-start gap-2 text-secondary small py-1">
                                <i class="fa-solid fa-location-dot text-muted mt-1" style="width: 16px;"></i>
                                <span class="fw-medium text-dark">{{ data?.client?.address }}</span>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="text-center py-5 text-muted">
                        <div class="rounded-circle bg-light d-inline-flex p-3 mb-2">
                            <i class="fa-solid fa-user-slash fs-2 text-secondary opacity-50"></i>
                        </div>
                        <h6 class="fw-semibold text-secondary mb-1">No Client Selected</h6>
                        <p class="small text-muted mb-0">Select a client or enter Client ID to preview account details</p>
                    </div>
                </fieldset>
            </div>
        </create-form>
    </div>
</template>

<script>
const model = "invoice";

export default {
    data() {
        return {
            model,
            page_title: "",
            isEditMode: false,
            base_description: "",

            data: {
                id: null,
                clientid: "",
                service_id: '',
                client_id: null,
                invoice_date: this.$filter.today(),
                original_amount: 0,
                discount: 0,
                vat: 0,
                amount: 0,
                is_payment_received: false,
                payment_date: this.$filter.today(),
                paid_amount: 0,
                payment_method: "Cash",
                fund_account_id: null,
                mbanking_type: null,
                bank_id: null,
                chequeno: "",
                trxid: "",
                payment_status: "",
                status: true,

                client: {},
                invoice_months: [],
                invoice_details: [
                    {
                        package_id: null,
                        description: "",
                        recharge_amount: "",
                        amount: 0,
                        user_count: null,
                        month_count: 1,
                        total_amount: 0,
                        account_id: null,
                    },
                ],
                savedMonths: [],
            },

            accounts: [],
            clients: [],
            client_packages: [],
            fundaccounts: [],
            banks: [],
        };
    },

    computed: {
        clientRechargeMethod() {
            return (this.data?.client?.recharge_model || this.data?.client?.recharge_method || 'paybill').toLowerCase();
        },
        clientPercentage() {
            if (this.data?.client?.percentage) {
                return parseFloat(this.data.client.percentage);
            }
            if (this.client_packages && this.client_packages.length > 0 && this.client_packages[0].percentage) {
                return parseFloat(this.client_packages[0].percentage);
            }
            if (this.data?.client?.client_packages && this.data.client.client_packages.length > 0 && this.data.client.client_packages[0].percentage) {
                return parseFloat(this.data.client.client_packages[0].percentage);
            }
            return 0;
        },
        selectedMonthsCount() {
            return this.data.invoice_months?.filter(m => m.checked).length || 0;
        },
    },

    provide() {
        return {
            validate: this.validation || { errors: {} },
        };
    },
    watch: {
        "data.invoice_months": {
            handler() {
                this.onMonthsChange();
            },
            deep: true,
        },
        "data.invoice_date": {
            handler() {
                this.generateInvoiceMonths();
            }
        },
        "data.invoice_details": {
            handler() {
                this.recalculateTotals();
            },
            deep: true,
        },
        'data.service_id': function () {
            this.getClientsByService();
        }
    },
    methods: {
        submit() {
            this.$validate().then(() => {
                const error = this.validation.countErrors();
                if (error > 0) {
                    this.$toast(
                        "You need to fill " + error + " more empty mandatory fields",
                        "warning"
                    );
                    return;
                }

                this.data.id
                    ? this.update(this.model, this.data, this.data.id)
                    : this.store(this.model, this.data);
            });
        },
        getClientsByService() {
            let service_id = this.data.service_id;
            axios.get(`clients/` + service_id)
                .then((response) => {
                    this.clients = response.data;
                });
        },
        /* -------------------------
           Invoice Months & Currency Helpers
        --------------------------*/
        formatMoney(val) {
            const num = Number(val || 0);
            return '৳ ' + num.toLocaleString('en-BD', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        toggleMonth(m) {
            m.checked = !m.checked;
            this.onMonthsChange();
        },

        selectCurrentMonthOnly() {
            const current = new Date();
            const currentValue = `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, "0")}`;
            this.data.invoice_months.forEach(m => {
                m.checked = (m.value === currentValue);
            });
            this.onMonthsChange();
        },

        clearAllMonths() {
            this.data.invoice_months.forEach(m => {
                m.checked = false;
            });
            this.onMonthsChange();
        },

        generateInvoiceMonths() {
            const base = new Date(this.data.invoice_date);
            const current = new Date();
            const months = [];

            const currentValue =
                `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, "0")}`;

            for (let i = -6; i <= 6; i++) {
                const d = new Date(base);
                d.setMonth(base.getMonth() + i);

                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, "0");
                const value = `${year}-${month}`;

                months.push({
                    value,
                    label: d.toLocaleString("en-US", {
                        month: "long",
                        year: "numeric",
                    }),
                    // current invoice month auto checked
                    checked: value === currentValue,
                });
            }

            this.data.invoice_months = months;
        },

        onMonthsChange() {

            const selected = this.data.invoice_months.filter(m => m.checked);
            const monthCount = selected.length || 1;
            const monthsText = selected.map(m => m.label).join(", ");

            this.data.invoice_details.forEach(row => {
                row.month_count = monthCount;

                if (this.base_description) {
                    row.description =
                        `${this.base_description}\nMonths: ${monthsText}`;
                }

                this.recalculateRow(row);
            });

            this.recalculateTotals();
        },

        /* -------------------------
           Row Calculations
        --------------------------*/
        onRechargeAmountChange(row) {
            const rechargeVal = parseFloat(row.recharge_amount) || 0;
            const method = this.clientRechargeMethod;
            const pct = parseFloat(this.clientPercentage) || 0;

            if (method === 'commission') {
                row.amount = ((rechargeVal * pct) / 100).toFixed(2);
            } else {
                row.amount = rechargeVal.toFixed(2);
            }

            this.recalculateRow(row);
            this.recalculateTotals();
        },

        recalculateRow(row) {
            if (this.isEditMode) return;
            const unit = parseFloat(row.amount) || 0;
            const months = parseInt(row.month_count) || 1;
            const userCount = parseInt(row.user_count) || null;

            if (parseInt(this.data.service_id) !== 6 && userCount && userCount > 0) {
                row.total_amount = (unit * months * userCount).toFixed(2);
            } else {
                row.total_amount = (unit * months).toFixed(2);
            }
        },

        recalculateTotals() {
            const sum = this.data.invoice_details.reduce(
                (s, r) => s + (parseFloat(r.total_amount) || 0),
                0
            );

            this.data.original_amount = sum.toFixed(2);

            let amount = sum - (parseFloat(this.data.discount) || 0);

            let vat = parseFloat(this.data.vat) || 0; // vat empty হলে 0 হবে
            let vat_amount = (vat * amount) / 100;

            this.data.amount = (amount + vat_amount).toFixed(2);

            if (this.data.is_payment_received) {
                this.data.paid_amount = this.data.amount;
            }
        },

        onPaymentReceiveToggle() {
            if (this.data.is_payment_received) {
                this.data.paid_amount = this.data.amount;
                if (!this.data.payment_date) {
                    this.data.payment_date = this.data.invoice_date || this.$filter.today();
                }
                if (!this.data.payment_method) {
                    this.data.payment_method = 'Cash';
                }
                if (!this.data.fund_account_id && this.fundaccounts?.length > 0) {
                    this.data.fund_account_id = this.fundaccounts[0].id;
                }
            }
        },

        getFundAccounts() {
            axios.get('/getfundaccounts').then(res => {
                this.fundaccounts = res.data;
                if (this.data.is_payment_received && !this.data.fund_account_id && this.fundaccounts?.length > 0) {
                    this.data.fund_account_id = this.fundaccounts[0].id;
                }
            }).catch(() => {});
        },

        getBanks() {
            axios.get('/getbanks').then(res => {
                this.banks = res.data;
            }).catch(() => {});
        },

        onDiscountChange() {
            this.recalculateTotals();
        },

        /* -------------------------
           Invoice Rows
        --------------------------*/
        addInvoiceDetailsRow() {
            const selected = this.data.invoice_months.filter(m => m.checked);
            const monthsText = selected.map(m => m.label).join(", ");
            const desc = this.base_description
                ? (monthsText ? `${this.base_description}\nMonths: ${monthsText}` : this.base_description)
                : '';

            this.data.invoice_details.push({
                package_id: null,
                description: desc,
                recharge_amount: "",
                amount: 0,
                user_count: null,
                month_count: this.data.invoice_details[0]?.month_count || (selected.length || 1),
                total_amount: 0,
                account_id: null,
            });
        },

        removeInvoiceDetails(index) {
            if (this.data.invoice_details.length > 1) {
                this.data.invoice_details.splice(index, 1);
                this.recalculateTotals();
            }
        },

        isLastItem(items, index) {
            return index === items.length - 1;
        },

        /* -------------------------
           Client Fetch
        --------------------------*/
        async fetchClientData() {
            const res = await axios.get('/clientinfo', {
                params: {
                    clientid: this.data.clientid,
                    client_id: this.data.client_id
                }
            });

            const c = res.data;

            this.data.client = c;
            this.generateInvoiceMonths();

            const serviceId = parseInt(this.data.service_id);
            const serviceTitle = c?.service?.title || "Internet Service";

            /*
            |--------------------------------------------------------------------------
            | Service ID = 6 (MAC User)
            | Package dropdown removed, recharge_amount is used instead
            |--------------------------------------------------------------------------
            */
            if (serviceId === 6) {

                this.client_packages = c?.client_packages || [];

                this.base_description = `${serviceTitle} Recharge`;

                const selected = this.data.invoice_months.filter(m => m.checked);
                const monthsText = selected.map(m => m.label).join(", ");
                const desc = monthsText ? `${this.base_description}\nMonths: ${monthsText}` : this.base_description;

                this.data.invoice_details = [
                    {
                        package_id: null,
                        description: desc,
                        user_count: c?.user_count || null,
                        recharge_amount: '',
                        amount: 0,
                        month_count: selected.length || 1,
                        total_amount: 0,
                        account_id: null,
                    },
                ];

                this.recalculateTotals();

                return;
            }

            // অন্য service হলে client_packages clear করে দিবে
            this.client_packages = [];

            const pkg = c?.package;

            let unitPrice = 0;

            this.base_description = '';

            if (pkg) {
                this.base_description =
                    `${serviceTitle}\nPackage: ${pkg.title} - ${pkg.bandwidth} ${pkg.unit?.title}`;

                unitPrice =
                    parseFloat(pkg.price || 0) +
                    parseFloat(pkg.vat || 0);
            }

            const user_count =
                serviceTitle === 'MAC'
                    ? c.user_count
                    : null;

            this.data.invoice_details = [
                {
                    package_id: null,
                    description: this.base_description,
                    amount: unitPrice.toFixed(2),
                    month_count: 1,
                    user_count: user_count,
                    total_amount: unitPrice.toFixed(2),
                    account_id: null,
                },
            ];

            this.recalculateTotals();
        },

        /* -------------------------
           Edit Mode Init
        --------------------------*/
        initEditMode() {
            this.isEditMode = true;
            this.page_title = this.headline(this.model) + " Edit";

            // শুধু fetch করবে, কিছু return করবে না
            this.get_data(`${this.model}/${this.$route.params.id}`);

            // data reactive ভাবে সেট হওয়ার পর পরবর্তী কাজগুলো করবো
            this.$nextTick(() => {
                this.generateInvoiceMonths();

                // backend থেকে আসা saved months apply
                if (this.data.savedMonths?.length) {
                    this.data.invoice_months.forEach(m => {
                        m.checked = this.data.savedMonths.includes(m.value);
                    });
                }

                // backend থেকে আসা row অনুযায়ী total recalc
                if (this.data.invoice_details?.length) {
                    this.data.invoice_details.forEach(row => {
                        this.recalculateRow(row);
                    });
                }

                this.recalculateTotals();

            });

        },
        onPackageChange(row) {

            // শুধুমাত্র service_id = 6 এর জন্য
            if (parseInt(this.data.service_id) !== 6) {
                return;
            }

            const packageId = row.package_id;

            // Package deselect করলে
            if (!packageId) {
                row.description = "";
                row.amount = 0;
                row.total_amount = 0;
                row.user_count = null;

                this.recalculateTotals();
                return;
            }

            // Selected client package খুঁজে বের করা
            const pkg = this.client_packages.find(
                item => parseInt(item.package_id) === parseInt(packageId)
            );

            if (!pkg) {
                return;
            }

            const serviceTitle = this.data?.client?.service?.title || "Internet Service";

            // client_packages.amount ব্যবহার হবে
            const unitPrice = parseFloat(pkg.amount || 0);

            // Invoice Description
            row.description = `${serviceTitle}\nPackage: ${pkg.title}`;

            // Amount auto fill
            row.amount = unitPrice.toFixed(2);

            // Service ID 6 এর জন্য বর্তমানে User Count লাগছে না
            row.user_count = null;

            // Row Total calculate
            this.recalculateRow(row);

            // Invoice Total calculate
            this.recalculateTotals();
        },
    },

    created() {
        this.getFundAccounts();
        this.getBanks();
        if (this.$route.params.id) {
            this.initEditMode();

            setTimeout(() => {
                this.isEditMode = false;
            }, 2000);

        } else {
            this.page_title = this.headline(this.model) + " Create";
            this.generateInvoiceMonths();
        }
    },

    validators: {
        "data.amount": function (value = null) {
            return Validator.value(value).required("Amount is required");
        },
    },
};
</script>

<style scoped>
.pro-fieldset {
    background: #ffffff;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
    padding: 22px 18px 18px 18px !important;
    position: relative;
    transition: all 0.2s ease-in-out;
}

.pro-fieldset:hover {
    border-color: #cbd5e1 !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.06), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
}

.pro-fieldset > .legend {
    position: absolute;
    top: -14px;
    left: 18px;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    color: #1e293b !important;
    border-radius: 20px !important;
    background: #f8fafc !important;
    padding: 3px 14px !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.month-chip {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 12.5px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
}

.month-chip:hover {
    border-color: #cbd5e1;
    background: #f1f5f9;
}

.month-chip.active {
    background: #3b82f6 !important;
    border-color: #3b82f6 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.25);
}

.month-chip.active i {
    color: #ffffff !important;
}

.pro-table {
    border-radius: 8px;
    overflow: hidden;
    border-color: #e2e8f0;
}

.pro-table thead th {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    background-color: #f8fafc !important;
    border-bottom: 2px solid #e2e8f0 !important;
}

.financials-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.payment-receive-box {
    transition: all 0.3s ease;
}

.transition-all {
    transition: all 0.25s ease-in-out;
}
</style>
