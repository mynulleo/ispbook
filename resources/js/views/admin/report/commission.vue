<template>
    <index-page :defaultTable="false" :show_status="false">
        <template v-slot:search-field>
            <!-- Date Filter -->
            <date-picker id='searchfromdate' v-model='search_data.from_date' field='search_data.from_date'
                title='From Date' placeholder='From Date' col='3' :req='false'></date-picker>
            <date-picker id='searchtodate' v-model='search_data.to_date' field='search_data.to_date' title='To Date'
                placeholder='To Date' col='3' :req='false'></date-picker>

            <!-- Client Filter -->
            <v-select-container title="Select Client" field="search_data.client_id" col="3">
                <v-select v-model="search_data.client_id" label="name" :reduce="(obj) => obj.id" :options="clients"
                    placeholder="--All Clients--" :closeOnSelect="true"></v-select>
            </v-select-container>

            <!-- Service Filter -->
            <v-select-container title="Service" field="search_data.service_id" col="3">
                <v-select v-model="search_data.service_id" label="title" :reduce="(obj) => obj.id"
                    :options="$root.global.services" placeholder="--All Services--" :closeOnSelect="true"></v-select>
            </v-select-container>

            <!-- Payment Status Filter -->
            <v-select-container title="Payment Status" field="search_data.payment_status" col="3">
                <v-select v-model="search_data.payment_status" label="name" :reduce="(obj) => obj.value"
                    :options="paymentStatusOptions" placeholder="--All Status--" :closeOnSelect="true"></v-select>
            </v-select-container>

            <!-- Approval Status Filter -->
            <v-select-container title="Approval Status" field="search_data.approval_status" col="3">
                <v-select v-model="search_data.approval_status" label="name" :reduce="(obj) => obj.value"
                    :options="approvalStatusOptions" placeholder="--All Approval--" :closeOnSelect="true"></v-select>
            </v-select-container>

            <!-- MAC Service Only Switch -->
            <div class="col-md-3 d-flex align-items-center mt-3">
                <div class="p-2 border rounded-3 bg-light w-100 d-flex align-items-center justify-content-between">
                    <span class="small fw-bold text-dark d-flex align-items-center gap-1">
                        <i class="fa-solid fa-desktop text-danger"></i> MAC Service Only
                    </span>
                    <SwitchBoolean v-model="search_data.is_mac" field="search_data.is_mac"
                        on-label="Yes" off-label="No" @change="search" />
                </div>
            </div>
        </template>

        <template v-slot:table-list>
            <!-- Actions Toolbar -->
            <div class="col-md-12 mb-3">
                <div class="d-flex gap-2 align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-3 py-2">
                            Total Records: {{ datas.total_count || 0 }}
                        </span>
                        <span v-if="search_data.is_mac" class="badge bg-danger rounded-pill px-3 py-2">
                            <i class="fa-solid fa-filter me-1"></i> Filtered by MAC Service
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-primary btn-sm px-3 d-flex align-items-center gap-2"
                            @click="print('printArea', model)" title="Print Report">
                            <i class="fas fa-print"></i>
                            <span>Print Report</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="col-md-12 mb-4">
                <div class="row g-3">
                    <!-- Total Invoices Amount -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow-sm border-0 border-start border-primary border-4 h-100 bg-white p-3 rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Total Invoices</div>
                                    <div class="fs-5 fw-bold text-dark mt-1">{{ formatCurrency(datas.total_invoices) }}</div>
                                </div>
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                                    <i class="fa-solid fa-file-invoice-dollar fs-5"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Commission Payable -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow-sm border-0 border-start border-warning border-4 h-100 bg-white p-3 rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Total Commission</div>
                                    <div class="fs-5 fw-bold text-warning mt-1">{{ formatCurrency(datas.total_commission) }}</div>
                                </div>
                                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                                    <i class="fa-solid fa-hand-holding-dollar fs-5"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Paid -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow-sm border-0 border-start border-success border-4 h-100 bg-white p-3 rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Commission Paid</div>
                                    <div class="fs-5 fw-bold text-success mt-1">{{ formatCurrency(datas.total_paid) }}</div>
                                </div>
                                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                                    <i class="fa-solid fa-circle-check fs-5"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Due -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card shadow-sm border-0 border-start border-danger border-4 h-100 bg-white p-3 rounded-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small fw-semibold text-uppercase">Commission Due</div>
                                    <div class="fs-5 fw-bold text-danger mt-1">{{ formatCurrency(datas.total_due) }}</div>
                                </div>
                                <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                                    <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAC Service Highlight Alert / Banner -->
                <div v-if="datas.total_mac_commission > 0" class="alert alert-danger bg-opacity-10 border-danger border-opacity-25 rounded-3 py-2 px-3 mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger text-uppercase px-2 py-1">MAC Service</span>
                        <span class="text-dark fw-semibold small">
                            Total MAC Commissions: <strong>{{ datas.total_mac_count }}</strong> items
                        </span>
                    </div>
                    <div class="fw-bold text-danger fs-6">
                        Payable MAC Commission: {{ formatCurrency(datas.total_mac_commission) }}
                    </div>
                </div>
            </div>

            <!-- Printable Area & Detailed Report Table -->
            <div class="col-md-12 my-2" id="printArea">
                <!-- 🧾 Report Header (Visible in Print) -->
                <div class="text-center mb-3 report-title d-none d-print-block">
                    <h3 class="fw-bold mb-1">{{ $root.site.title }}</h3>
                    <p class="mb-1 text-muted">{{ $root.site.address }}</p>
                    <p class="mb-2 text-muted">Email: {{ $root.site.contact_email }} | Phone: {{ $root.site.mobile1 }}</p>
                    <h5 class="fw-bold text-dark mt-2 border-top border-bottom py-1 d-inline-block px-4">Commission Details Report</h5>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Commission Statements</h6>
                        <small class="text-muted">Generated: <strong>{{ reportDate }}</strong></small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Period: <strong>{{ dateRange }}</strong></small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0 report-table">
                        <thead class="bg-light text-dark">
                            <tr>
                                <th style="width: 45px;" class="text-center">#</th>
                                <th style="width: 100px;">Date</th>
                                <th>Client Details</th>
                                <th style="width: 110px;" class="text-center">Service</th>
                                <th style="width: 130px;">Invoice No</th>
                                <th style="width: 120px;" class="text-end">Invoice (৳)</th>
                                <th style="width: 80px;" class="text-center">Rate (%)</th>
                                <th style="width: 130px;" class="text-end">Commission (৳)</th>
                                <th style="width: 120px;" class="text-end">Paid (৳)</th>
                                <th style="width: 120px;" class="text-end">Due (৳)</th>
                                <th style="width: 95px;" class="text-center">Paid Status</th>
                                <th style="width: 95px;" class="text-center">Approval</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="datas.commissions && datas.commissions.length > 0">
                                <tr v-for="(item, index) in datas.commissions" :key="'comm-' + index"
                                    :class="item.is_mac ? 'table-warning bg-opacity-25' : ''">
                                    <td class="text-center text-secondary small">{{ index + 1 }}</td>
                                    <td class="small fw-semibold text-dark">{{ item.date || '-' }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ item.client_name }}</div>
                                        <div class="small text-muted d-flex align-items-center gap-1">
                                            <span class="badge bg-light text-secondary border">{{ item.client_code }}</span>
                                            <span v-if="item.client_mobile">{{ item.client_mobile }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span v-if="item.is_mac" class="badge bg-danger text-uppercase fw-bold shadow-sm">
                                            <i class="fa-solid fa-desktop me-1"></i> MAC
                                        </span>
                                        <span v-else class="badge bg-primary text-uppercase">
                                            {{ item.service_name }}
                                        </span>
                                    </td>
                                    <td>
                                        <span v-if="item.invoice_no && item.invoice_no !== '-'" class="badge bg-light text-dark border fw-bold">
                                            {{ item.invoice_no }}
                                        </span>
                                        <span v-else class="text-muted small">-</span>
                                        <div v-if="item.invoice_date" class="text-muted" style="font-size: 11px;">
                                            {{ item.invoice_date }}
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold text-dark">
                                        {{ formatCurrency(item.invoice_amount) }}
                                    </td>
                                    <td class="text-center fw-bold text-primary">
                                        {{ item.percentage ? item.percentage + '%' : '-' }}
                                    </td>
                                    <td class="text-end fw-bold text-dark">
                                        {{ formatCurrency(item.commission_amount) }}
                                    </td>
                                    <td class="text-end fw-semibold text-success">
                                        {{ formatCurrency(item.paid_amount) }}
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        {{ formatCurrency(item.due_amount) }}
                                    </td>
                                    <td class="text-center">
                                        <span v-if="item.payment_status === 'Paid'" class="badge bg-success">
                                            <i class="fa-solid fa-check me-1"></i> Paid
                                        </span>
                                        <span v-else-if="item.payment_status === 'Partial'" class="badge bg-warning text-dark">
                                            Partial
                                        </span>
                                        <span v-else class="badge bg-danger">
                                            Unpaid
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span v-if="item.is_approved" class="badge bg-info text-dark" :title="'Approved by ' + (item.approved_by || '')">
                                            Approved
                                        </span>
                                        <span v-else class="badge bg-secondary">
                                            Pending
                                        </span>
                                    </td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr>
                                    <td colspan="12" class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-circle-info me-1"></i> No commission records found matching the criteria.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-light fw-bold border-top-2">
                            <tr>
                                <td colspan="5" class="text-end py-2">Grand Total:</td>
                                <td class="text-end py-2 text-dark">{{ formatCurrency(datas.total_invoices) }}</td>
                                <td class="text-center py-2">-</td>
                                <td class="text-end py-2 text-dark">{{ formatCurrency(datas.total_commission) }}</td>
                                <td class="text-end py-2 text-success">{{ formatCurrency(datas.total_paid) }}</td>
                                <td class="text-end py-2 text-danger">{{ formatCurrency(datas.total_due) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- 📌 Print Footer -->
                <div class="mt-4 small text-muted d-none d-print-block">
                    <div class="d-flex justify-content-between pt-5">
                        <div class="border-top px-4 pt-1 text-center">Prepared By</div>
                        <div class="border-top px-4 pt-1 text-center">Checked By</div>
                        <div class="border-top px-4 pt-1 text-center">Authorized Signature</div>
                    </div>
                </div>
            </div>
        </template>
    </index-page>
</template>

<script>
import axios from "axios";
import moment from "moment";

const tableColumns = [{ field: "status", title: "Status", align: "center" }];
const json_fields = {
    Date: "date",
    Client: "client_name",
    Code: "client_code",
    Service: "service_name",
    Invoice: "invoice_no",
    "Invoice Amount": "invoice_amount",
    Percentage: "percentage",
    "Commission Amount": "commission_amount",
    "Paid Amount": "paid_amount",
    "Due Amount": "due_amount",
    "Payment Status": "payment_status",
};
const model = "commission_report";

export default {
    data() {
        return {
            model: model,
            page_title: "Commission Report",
            reportDate: moment().format("D MMMM, YYYY"),
            json_fields: json_fields,
            search_data: {
                from_date: null,
                to_date: null,
                client_id: null,
                service_id: null,
                is_mac: false,
                payment_status: "all",
                approval_status: "all",
            },
            paymentStatusOptions: [
                { name: "All Payment Status", value: "all" },
                { name: "Paid", value: "paid" },
                { name: "Partial", value: "partial" },
                { name: "Unpaid / Due", value: "unpaid" },
            ],
            approvalStatusOptions: [
                { name: "All Approval Status", value: "all" },
                { name: "Approved", value: "approved" },
                { name: "Pending", value: "pending" },
            ],
            table: {
                columns: tableColumns,
                routes: {},
                datas: [],
                meta: [],
                links: [],
            },
            datas: {
                commissions: [],
                total_invoices: 0,
                total_commission: 0,
                total_paid: 0,
                total_due: 0,
                total_mac_commission: 0,
                total_mac_count: 0,
                total_count: 0,
            },
            clients: [],
        };
    },

    provide() {
        return {
            validate: this.validation,
            model: this.model,
            search_data: this.search_data,
            table: this.table,
            json_fields: this.json_fields,
            search: this.search,
            resetSearchData: this.resetSearchData,
        };
    },

    computed: {
        dateRange() {
            if (this.search_data.from_date && this.search_data.to_date) {
                return (
                    moment(this.search_data.from_date).format("D MMM, YYYY") +
                    " to " +
                    moment(this.search_data.to_date).format("D MMM, YYYY")
                );
            }
            if (this.search_data.from_date) {
                return "From " + moment(this.search_data.from_date).format("D MMM, YYYY");
            }
            if (this.search_data.to_date) {
                return "Up to " + moment(this.search_data.to_date).format("D MMM, YYYY");
            }
            return "All Time";
        },
    },

    methods: {
        formatCurrency(val) {
            const num = Number(val || 0);
            return (
                "৳ " +
                num.toLocaleString("en-BD", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })
            );
        },

        search() {
            this.getCommissionData();
        },

        resetSearchData() {
            this.search_data = {
                from_date: null,
                to_date: null,
                client_id: null,
                service_id: null,
                is_mac: false,
                payment_status: "all",
                approval_status: "all",
            };
            this.getCommissionData();
        },

        getCommissionData() {
            axios
                .get(`report/commission/`, { params: this.search_data })
                .then((res) => {
                    this.datas = res.data;
                })
                .catch((err) => {
                    console.error("Error fetching commission report data", err);
                });
        },

        getClients() {
            axios
                .get(`clients/`)
                .then((response) => {
                    this.clients = response.data;
                })
                .catch((error) => {
                    console.error("Error fetching clients", error);
                });
        },
    },

    created() {
        this.page_title = "Commission Report";
        this.getCommissionData();
        this.getClients();
    },

    validators: {},
};
</script>

<style scoped>
.report-table th {
    font-size: 12.5px;
    font-weight: 600;
    white-space: nowrap;
}

.report-table td {
    font-size: 13px;
    vertical-align: middle;
}

@media print {
    #printArea {
        padding: 10px;
    }
}
</style>

