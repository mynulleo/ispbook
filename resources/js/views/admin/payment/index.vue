<template>
  <index-page :show_status="false">
    <template v-slot:search-field>
      <v-select-container title="Select Client" field="search_data.client_id" col="3">
        <v-select v-model="search_data.client_id" label="name" :reduce="(obj) => obj.id" :options="clients"
          placeholder="--Select Client--" :closeOnSelect="true"></v-select>
      </v-select-container>
      <v-select-container title="Select Supplier" field="search_data.supplier_id" col="3">
        <v-select v-model="search_data.supplier_id" label="org_name" :reduce="(obj) => obj.id"
          :options="$root.global.suppliers" placeholder="--Select Supplier--" :closeOnSelect="true"></v-select>
      </v-select-container>

      <v-select-container title="Select Uplinks" field="search_data.uplink_provider_id" col="3">
        <v-select v-model="search_data.uplink_provider_id" label="org_name" :reduce="(obj) => obj.id"
          :options="$root.global.uplinkproviders" placeholder="--Select Supplier--" :closeOnSelect="true"></v-select>
      </v-select-container>
      <v-select-container title="Select Eployee" field="search_data.employee_id" col="3">
        <v-select v-model="search_data.employee_id" label="full_name" :reduce="(obj) => obj.id"
          :options="$root.global.employees" placeholder="--Select Employee--" :closeOnSelect="true"></v-select>
      </v-select-container>
      <v-select-container title="Select Payment Methods" field="search_data.payment_method" col="3">
        <v-select v-model="search_data.payment_method" label="name" :reduce="(obj) => obj.value"
          :options="$root.global.paymentmethods" placeholder="--Select Employee--" :closeOnSelect="true"></v-select>
      </v-select-container>

      <date-picker id='searchfrominvoicedate' v-model='search_data.from_payment_date' field='search_data.from_trns_date'
        title='From Invoice Date' placeholder='From Payment Date' col='3' :req='false'></date-picker>
      <date-picker id='searchtoPaymentdate' v-model='search_data.to_payment_date' field='search_data.to_trns_date'
        title='To Payment Date' placeholder='To Payment Date' col='3' :req='false'
        :disablePastDates="search_data.from_payment_date"></date-picker>

      <SwitchBoolean v-model='search_data.office_expense' field='data.office_expense' title='Office Expense'
        on-label='Yes' off-label='No' :req='false'></SwitchBoolean>
    </template>

    <template v-slot:summary-page>
      <div class="row my-3 g-3 align-items-stretch">
        <!-- Payment Methods Summary Badges / Cards on Left -->
        <div class="col-md-8 col-lg-8">
          <div class="d-flex flex-wrap gap-2 align-items-center h-100">
            <div
              v-for="(grp, idx) in methodSummary"
              :key="'card-' + idx"
              class="card shadow-sm border py-2 px-3 d-flex flex-row align-items-center gap-3 bg-white"
              style="border-radius: 8px; min-width: 170px;"
            >
              <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" :class="getMethodBgClass(grp.method)" style="width: 38px; height: 38px;">
                <i :class="getMethodIcon(grp.method)"></i>
              </div>
              <div>
                <div class="small text-secondary fw-semibold">
                  {{ grp.method }}
                  <span class="badge bg-light text-secondary border rounded-pill ms-1">{{ grp.count }}</span>
                </div>
                <div class="fw-bold text-dark fs-6">
                  {{ formatCurrency(grp.total) }}
                </div>
              </div>
            </div>

            <!-- Fallback if no transactions on current page -->
            <div v-if="!methodSummary.length" class="badge bg-light text-secondary border px-3 py-2 rounded-pill">
              <i class="fa-solid fa-circle-info me-1"></i> No transactions on this page
            </div>
          </div>
        </div>

        <!-- Summary Card on Right -->
        <div class="col-md-4 col-lg-4 d-flex justify-content-md-end">
          <div class="card shadow-sm border w-100" style="max-width: 340px; border-radius: 10px; overflow: hidden;">
            <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="fa-solid fa-chart-pie text-primary"></i>
                <span>Page Summary</span>
              </h6>
              <span class="badge bg-primary rounded-pill">{{ table.datas?.length || 0 }} items</span>
            </div>
            <div class="card-body p-3">
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <tbody>
                    <tr v-for="(grp, idx) in methodSummary" :key="'summary-' + idx">
                      <td class="text-secondary small">
                        <i :class="getMethodIcon(grp.method)" class="me-1"></i> {{ grp.method }}:
                      </td>
                      <td class="text-end fw-semibold text-dark">
                        {{ formatCurrency(grp.total) }}
                      </td>
                    </tr>
                    <tr class="border-top">
                      <td class="fw-bold text-dark small pt-2">
                        <i class="fa-solid fa-layer-group text-primary me-1"></i> Total Amount:
                      </td>
                      <td class="text-end fw-bold text-primary fs-6 pt-2">
                        {{ formatCurrency(pageTotalAmount) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </index-page>
</template>
<script>

const model = "payment";

const tableColumns = [

  { field: "payslipno", title: "Payslipno" },
  { field: "payment_type", title: "Payment Type" },
  { field: "client_id", title: "Client", subfield: "client.name" },
  { field: "supplier_id", title: "Supplier", subfield: "supplier.org_name" },
  { field: "uplink_provider_id", title: "Uplink Provider", subfield: "uplink_provider.org_name" },
  { field: "employee_id", title: "Employee", subfield: "employee.full_name" },
  { field: "payment_date", title: "Payment Date" },
  { field: "payment_method", title: "Payment Method" },
  { field: "amount", title: "Amount" },
  { field: "status", title: "Status", align: "center" },
];

const json_fields = {
  "Payslipno": "payslipno",
  "Payment Type": "payment_type",
  "Client Id": "client_id",
  "Supplier Id": "supplier_id",
  "Payment Date": "payment_date",
  "Payment Method": "payment_method",
  "Mbanking Type": "mbanking_type",
  "Chequeno": "chequeno",
  "Bank Name": "bank_name",
  "Branch": "branch",
  "Accountno": "accountno",
  "Trxid": "trxid",
};

export default {

  data() {
    return {
      model: model,
      page_title: "",
      json_fields: json_fields,
      fields_name: { default: "Select One", payslipno: "Pay Slip No", chequeno: "Cheque No", bank_name: "Bank Name", accountno: "Account No", trxid: "Trx ID" },
      search_data: {
        pagination: this.$route.query.pagination ?? 10,
        page: this.$route.query.page ?? 1,
        field_name: this.$route.query.field_name ?? "",
        value: this.$route.query.value ?? "",
        status: this.$route.query.status ?? "",
      },
      table: {
        columns: tableColumns,
        routes: {},
        datas: [],
        meta: [],
        links: []
      },
      clients: []
    };
  },

  computed: {
    methodSummary() {
      if (!this.table.datas || !this.table.datas.length) return [];
      const groups = {};
      this.table.datas.forEach(row => {
        let method = (row.payment_method || 'Other').trim();
        if (method === 'BankTransfer') {
          method = 'Bank';
        }
        if (!groups[method]) {
          groups[method] = {
            method: method,
            count: 0,
            total: 0,
          };
        }
        groups[method].count += 1;
        groups[method].total += Number(row.amount || 0);
      });
      return Object.values(groups).sort((a, b) => b.total - a.total);
    },
    pageTotalAmount() {
      if (!this.table.datas || !this.table.datas.length) return 0;
      return this.table.datas.reduce((sum, row) => sum + Number(row.amount || 0), 0);
    },
    totalCount() {
      return this.table.meta?.total ?? this.table.datas?.length ?? 0;
    },
  },

  provide() {
    return {
      validate: this.validation,
      model: this.model,
      fields_name: this.fields_name,
      search_data: this.search_data,
      table: this.table,
      json_fields: this.json_fields,
      search: this.search,
      resetSearchData: this.resetSearchData,
    };
  },

  methods: {
    getMethodIcon(method) {
      const m = (method || '').toLowerCase();
      if (m.includes('cash')) return 'fa-solid fa-money-bill-wave text-success';
      if (m.includes('bank')) return 'fa-solid fa-building-columns text-primary';
      if (m.includes('mobile') || m.includes('bkash') || m.includes('nagad') || m.includes('rocket')) {
        return 'fa-solid fa-mobile-screen-button text-info';
      }
      if (m.includes('cheque')) return 'fa-solid fa-money-check text-warning';
      return 'fa-solid fa-credit-card text-secondary';
    },
    getMethodBgClass(method) {
      const m = (method || '').toLowerCase();
      if (m.includes('cash')) return 'bg-success bg-opacity-10';
      if (m.includes('bank')) return 'bg-primary bg-opacity-10';
      if (m.includes('mobile') || m.includes('bkash') || m.includes('nagad') || m.includes('rocket')) {
        return 'bg-info bg-opacity-10';
      }
      if (m.includes('cheque')) return 'bg-warning bg-opacity-10';
      return 'bg-secondary bg-opacity-10';
    },
    formatCurrency(val) {
      const num = Number(val || 0);
      return '৳ ' + num.toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
    search() {
      this.get_paginate(this.model, this.search_data);
    },
    getClients() {
      axios.get(
        `clients/`
      )
        .then((response) => {
          this.clients = response.data;
        })
        .catch((error) => {
          console.error(error);
        });
    },
    resetSearchData() {
      this.search_data.pagination = 10;
      this.search_data.page = 1;
      this.search_data.field_name = "";
      this.search_data.value = "";
      this.search_data.status = "";
    },
  },

  created() {
    this.getRouteName(this.model);
    this.page_title = `${this.headline(this.model)} List`;
    this.search();
    this.getClients();
  },

  validators: {},
};
</script>