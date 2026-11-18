<template>
    <create-form @onSubmit='submit'>
        <div class="col-9">
            <fieldset class="pro-fieldset">
                <span class="legend"><i class="fas fa-building text-primary me-1"></i> Office Info</span>
                <div class="row g-3">
                    <date-picker id='date1' v-model='data.reg_date' field='data.reg_date' title='Reg Date'
                        placeholder='Reg Date' col='2' :req='true'></date-picker>
                    <Select title='Type' v-model='data.type' field='data.type' label='name' :reduce='(obj) => obj.value'
                        :options='$root.global.clienttypes' placeholder='--Select One--' :closeOnSelect='true' col="3"
                        :req='true' />
                    <Input v-model='data.clientid' field='data.clientid' col="3" title='Client ID' :req='false' />
                    <div class="col-md-12"></div>
                    <Select title='Service' v-model='data.service_id' field='data.service_id' label='title'
                        :reduce='(obj) => obj.id' :options='$root.global.services' placeholder='--Select One--'
                        :closeOnSelect='true' col="2" :required='true' />
                    <Select title='Package' v-model='data.package_id' field='data.package_id' label='title'
                        v-if="data.service_id != 6" :reduce='(obj) => obj.id' :options='packages'
                        placeholder='--Select One--' :closeOnSelect='true' col="2" :required='false' />
                    <Select title='District' v-model='data.district_id' field='data.district_id' label='district_name'
                        :reduce='(obj) => obj.id' col="2" :options='$root.global.districts' placeholder='--Select One--'
                        :closeOnSelect='true' :required='true' />
                    <div class="custom_v_select col-md-3">
                        <div class="form-group">
                            <label class="form-label d-flex justify-content-between align-items-center gap-4">
                                <div class="lft">
                                    <span>Area</span>
                                    <sup class="text-danger ms-1">*</sup>
                                    <span class="icon_error" v-if="validation.hasError('data.area_id')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                            fill="currentColor" class="icon icon-tabler icons-tabler-filled icon-tabler-help-octagon">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M14.897 1a4 4 0 0 1 2.664 1.016l.165 .156l4.1 4.1a4 4 0 0 1 1.168 2.605l.006 .227v5.794a4 4 0 0 1 -1.016 2.664l-.156 .165l-4.1 4.1a4 4 0 0 1 -2.603 1.168l-.227 .006h-5.795a3.999 3.999 0 0 1 -2.664 -1.017l-.165 -.156l-4.1 -4.1a4 4 0 0 1 -1.168 -2.604l-.006 -.227v-5.794a4 4 0 0 1 1.016 -2.664l.156 -.165l4.1 -4.1a4 4 0 0 1 2.605 -1.168l.227 -.006h5.793zm-2.897 14a1 1 0 0 0 -.993 .883l-.007 .117l.007 .127a1 1 0 0 0 1.986 0l.007 -.117l-.007 -.127a1 1 0 0 0 -.993 -.883zm1.368 -6.673a2.98 2.98 0 0 0 -3.631 .728a1 1 0 0 0 1.44 1.383l.171 -.18a.98 .98 0 0 1 1.11 -.15a1 1 0 0 1 -.34 1.886l-.232 .012a1 1 0 0 0 .111 1.994a3 3 0 0 0 1.371 -5.673z" />
                                        </svg>
                                        <span class="error_info active_error_info">
                                            {{ validation.firstError('data.area_id') }}
                                        </span>
                                    </span>
                                </div>
                            </label>
                            <div class="d-flex align-items-center gap-1">
                                <div class="v-select-wrapper flex-grow-1" style="min-width: 0;" :class="{ 'border-red': validation.hasError('data.area_id') }">
                                    <v-select
                                        v-model="data.area_id"
                                        :options="areas"
                                        label="area_name"
                                        :reduce="(obj) => obj.id"
                                        placeholder="--Select One--"
                                        :closeOnSelect="true"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm d-flex align-items-center justify-content-center"
                                    style="height: 38px; width: 38px; border-radius: 8px; flex-shrink: 0;"
                                    @click.prevent="openAreaModal"
                                    title="Add New Area"
                                >
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <SwitchBoolean v-model='data.is_commission' field='data.is_commission' title='Is Commission'
                        on-label='Yes' off-label='No' col="3" :req='true'>
                    </SwitchBoolean>
                </div>
            </fieldset>
        </div>
        <div class="col-3">
            <fieldset class="pro-fieldset">
                <span class="legend"><i class="fas fa-circle-info text-info me-1"></i> Service Info</span>
                <div class="service-info-card p-3 rounded">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                        <span class="text-secondary small fw-semibold">Service</span>
                        <span class="badge bg-primary text-white px-2 py-1">{{ data.service?.title || 'None' }}</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                        <span class="text-secondary small fw-semibold">Auto Invoice</span>
                        <span :class="data.service?.auto_invoice ? 'badge bg-success text-white' : 'badge bg-secondary text-white'">
                            {{ data.service?.auto_invoice ? 'Yes' : 'No' }}
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-secondary small fw-semibold">Module</span>
                        <span class="text-dark small fw-medium">{{ data.service?.module || '-' }}</span>
                    </div>
                </div>
            </fieldset>
        </div>
        <div :class="data.service_id == 6 ? 'col-md-6' : 'col-md-12'">
            <fieldset class="mt-3 mb-3 pro-fieldset">
                <span class="legend"><i class="fas fa-user text-success me-1"></i> Client Info</span>
                <div class="row g-3">
                    <Input v-model='data.org_name' v-if="data.service_id == 5" field='data.org_name' title='Org Name'
                        col="8" :req='false' />
                    <Input v-model='data.name' field='data.name' title='Name' col="6" :req='true' />

                    <Input v-model='data.mobile' field='data.mobile' title='Mobile' col="3" :req='true' />
                    <Input v-model='data.email' field='data.email' title='Email' col="3" :req='false' />
                    <Input v-model='data.nid' field='data.nid' title='NID' col="4" :req='false' />
                    <Input v-model='data.address' field='data.address' title='Address' col="8" :req='false' />
                </div>
            </fieldset>
        </div>
        <div class="col-6" v-if="data.service_id == 6">
            <fieldset class="mt-3 mb-3 pro-fieldset">
                <span class="legend"><i class="fas fa-boxes-stacked text-primary me-1"></i> Multiple Package</span>
                <div class="row g-3 align-items-center mb-2">
                    <div class="col-md-6">
                        <Input v-model="package_percentage" field="package_percentage" title="Percentage (%)"
                            placeholder="Enter %" col="12" :req="false" @input="calculateClientPackageAmounts" />
                    </div>
                    <div class="col-md-6">
                        <Select title="Recharge Model" v-model="data.recharge_model" field="data.recharge_model"
                            label="name" :reduce="(obj) => obj.value" :options="rechargeModels"
                            placeholder="--Select Model--" :closeOnSelect="true" col="12" :required="false" />
                    </div>
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 pro-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:55%">Package</th>
                                        <th style="width:35%">Amount</th>
                                        <th style="width:10%" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(clientpackage, index) in data.client_packages" :key="index">
                                        <td>
                                            <Select v-model='clientpackage.package_id' field='clientpackage.package_id'
                                                label='title' :reduce='(obj) => obj.id' :options='packages'
                                                placeholder='--Select One--' :closeOnSelect='true' col="12" :required='false' />
                                        </td>
                                        <td>
                                            <Input v-model='clientpackage.amount' field='clientpackage.amount' col="12"
                                                :req='false' />
                                        </td>
                                        <td class="text-center">
                                            <div class="multiple_fields_actions_btn d-flex align-items-center justify-content-center gap-2">
                                                <button type="button" class="btn btn-outline-danger btn-sm p-1 rounded" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" data-bs-title="Delete" v-x-tooltip @click.prevent="
                                                        removeClientPackage(index)
                                                        " v-if="
                                                            Object.keys(
                                                                data.client_packages
                                                            ).length > 1
                                                        ">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <button v-if="isLastItem(data.client_packages, index)" type="button"
                                                    class="btn btn-outline-primary btn-sm p-1 rounded" data-bs-toggle="tooltip" data-bs-placement="top"
                                                    data-bs-title="Add" v-x-tooltip @click.prevent="addClientPackageRow()">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>
        <div class="col-6">
            <fieldset class="mt-3 pro-fieldset">
                <span class="legend"><i class="fas fa-file-invoice-dollar text-warning me-1"></i> Additional Information</span>
                <div class="row g-3" v-if="data.type == 'New'">
                    <Select title='Employee' v-model='data.employee_id' field='data.employee_id' label='full_name'
                        :reduce='(obj) => obj.id' col="6" :options='$root.global.employees' placeholder='--Select One--'
                        :closeOnSelect='true' :required='false' :readonly="!data.is_commission" />
                    <Input v-model='data.ref_name' field='data.ref_name' col="6" title='Ref Name' :req='false'
                        :readonly="!data.is_commission" />
                    <Input v-model='data.commission_amount' field='data.commission_amount' col="12"
                        title='Commission Amount' :readonly="!data.is_commission" :req='false' />
                </div>
                <div class="row g-3" v-else>
                    <Input v-model='data.previous_due' field='data.previous_due' col="6" title='Previous Due'
                        :req='false' />
                    <Textarea v-model='data.note' field='data.note' col="12" title='Remarks' :req='false' />
                </div>
            </fieldset>
        </div>
        <div class="col-6">
            <fieldset class="mt-3 pro-fieldset">
                <span class="legend"><i class="fas fa-landmark text-secondary me-1"></i> Bank Information</span>
                <div class="row g-3">
                    <Input v-model='data.account_name' field='data.account_name' col="6" title='Account Name'
                        :req='false' />
                    <Input v-model='data.account_no' field='data.account_no' col="6" title='Account No' :req='false' />
                    <Select title='Bank' v-model='data.bank_id' field='data.bank_id' label='bank_name'
                        :reduce='(obj) => obj.id' col="6" :options='banks' placeholder='--Select One--'
                        :closeOnSelect='true' :required='false' />
                    <Input v-model='data.branch' field='data.branch' col="6" title='Branch' :req='false' />
                </div>
            </fieldset>
        </div>
        <div class="col-12" v-if="data.service?.module == 'BandwidthHistory'">
            <fieldset class="mt-3 pro-fieldset">
                <span class="legend"><i class="fas fa-network-wired text-primary me-1"></i> Invoice Setup</span>
                <table class="table bandwidth table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="3%">Vat</th>
                            <th width="10%">Type</th>
                            <th width="10%">Category</th>
                            <th width="10%">LinkID</th>
                            <th>Rate</th>
                            <th>Bandwidth</th>
                            <th width="10%">Unit</th>
                            <th width="10%">Start Date</th>
                            <th width="10%">End Date</th>
                            <th>Days</th>
                            <th>Excl. Amount</th>
                            <th>Incl. Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(invoicedata, index) in data.invoice_setup" :key="index">
                            <td><input v-model="invoicedata.is_vat" field="invoicedata.is_vat" :checked="data.is_vat"
                                    type="checkbox">
                            </td>
                            <td>
                                <Select v-model="invoicedata.type" field="invoicedata.type" label="name"
                                    :reduce="(obj) => obj.value" :options="$root.global.bandwidthtypes"
                                    placeholder="--Select One--" :closeOnSelect="true" :required="false" col="12" />
                            </td>
                            <td>
                                <Select v-model="invoicedata.category_id" field="invoicedata.category_id" label="title"
                                    :reduce="(obj) => obj.id" :options="categories" placeholder="--Select One--"
                                    :closeOnSelect="true" :required="false" col="12" />
                            </td>
                            <td>
                                <Input v-model="invoicedata.linkid" field="invoicedata.linkid" col="12" />
                            </td>
                            <td>
                                <Input v-model="invoicedata.price" field="invoicedata.price" col="12" />
                            </td>
                            <td>
                                <Input v-model="invoicedata.bandwidth" field="invoicedata.bandwidth" col="12" />
                            </td>
                            <td>
                                <Select v-model="invoicedata.unit_id" field="invoicedata.unit_id" label="title"
                                    :reduce="(obj) => obj.id" :options="$root.global.units" placeholder="--Select One--"
                                    :closeOnSelect="true" :required="false" col="12" />
                            </td>
                            <td>
                                <date-picker :id='"startdate" + index' v-model='invoicedata.start_date'
                                    field='invoicedata.start_date' placeholder='Start Date' col='12'
                                    :req='false'></date-picker>
                            </td>
                            <td>
                                <date-picker :id='"enddate" + index' v-model='invoicedata.end_date'
                                    field='invoicedata.end_date' placeholder='End Date' col='12'
                                    :req='false'></date-picker>
                            </td>
                            <td>
                                <Input v-model="invoicedata.days" field="invoicedata.days" col="12" />
                            </td>
                            <td>
                                <Input v-model="invoicedata.exclude_amount" field="invoicedata.amount" col="12" />
                            </td>
                            <td>
                                <Input v-model="invoicedata.include_amount" field="invoicedata.amount" col="12" />
                            </td>
                            <td>
                                <div class="multiple_fields_actions_btn d-flex align-items-center gap-2">
                                    <button type="button" class="btns delete_one" data-bs-toggle="tooltip"
                                        data-bs-placement="top" data-bs-title="Delete" v-x-tooltip @click.prevent="
                                            removeBandwidthDetails(index)
                                            " v-if="
                                                Object.keys(
                                                    data.invoice_setup
                                                ).length > 1
                                            ">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button v-if="isLastItem(data.invoice_setup, index)" type="button"
                                        class="btns add_more" data-bs-toggle="tooltip" data-bs-placement="top"
                                        data-bs-title="Add" v-x-tooltip @click.prevent="addBandwidthDetailsRow()">
                                        <i class="fas fa-plus-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th colspan="5">Total</th>
                            <th>{{ data.total_bandwidth }}</th>
                            <th colspan="4"></th>
                            <th>{{ data.total_amount }}</th>
                            <th>{{ data.total_include_amount }}</th>
                            <th></th>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
        </div>
        <Switch v-model='data.status' field='data.status' title='Status' on-label='Active' off-label='Deactive' col="12"
            :req='true'>
        </Switch>

        <!-- Quick Add Area Modal -->
        <div v-if="showAreaModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0, 0, 0, 0.5); z-index: 1055;" @click.self="closeAreaModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow border-0" style="border-radius: 12px; overflow: hidden;">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title text-black d-flex align-items-center gap-2 mb-0">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                            <span>Add New Area</span>
                        </h5>
                        <button type="button" class="btn-close" @click="closeAreaModal" aria-label="Close" :disabled="areaLoading"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Selected District</label>
                            <input type="text" class="form-control bg-light" :value="selectedDistrictName" readonly disabled />
                            <small class="text-muted">The new area will be added under this district.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Area Name <sup class="text-danger">*</sup>
                            </label>
                            <input
                                id="quick_area_name_input"
                                type="text"
                                class="form-control"
                                v-model="newArea.area_name"
                                placeholder="Enter area name"
                                :class="{ 'is-invalid': areaError }"
                                @input="areaError = ''"
                                @keyup.enter.prevent="saveArea"
                                autocomplete="off"
                            />
                            <div class="invalid-feedback d-block" v-if="areaError">
                                {{ areaError }}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary px-3" @click="closeAreaModal" :disabled="areaLoading">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary px-4" @click.prevent="saveArea" :disabled="areaLoading">
                            <span v-if="areaLoading" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            <i v-else class="fas fa-save me-1"></i>
                            Save Area
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </create-form>
</template>

<script>


const model = 'client';

export default {
    data() {
        return {
            model: model,
            page_title: '',
            data: {
                type: 'New',
                reg_date: this.$filter.today(),
                is_commission: 0,
                recharge_model: 'paybill',
                percentage: 0,
                account_name: '',
                account_no: '',
                bank_id: null,
                branch: '',
                employee_id: null,
                service: {
                    title: '',
                    auto_invoice: 0,
                    module: ""
                },
                vat: 0,
                client_packages: [
                    {
                        package_id: '',
                        percentage: '',
                        amount: '',
                    }
                ],
                invoice_setup: [
                    {
                        is_vat: false,
                        type: '',
                        category_id: '',
                        linkid: '',
                        bandwidth: 0,
                        unit_id: 1,
                        price: 0,          // rate
                        start_date: '',
                        end_date: '',
                        days: 0,
                        exclude_amount: 0,
                        include_amount: 0,
                    }
                ],
                total_bandwidth: 0,
                total_amount: 0,
                total_include_amount: 0
            },
            packages: [],
            banks: [],
            areas: [],
            categories: [],
            selectedDistrictName: '',
            newArea: {
                district_id: null,
                area_name: '',
                status: 'active'
            },
            areaLoading: false,
            areaError: '',
            showAreaModal: false,
            package_percentage: '',
            rechargeModels: [
                { name: 'Paybill', value: 'paybill' },
                { name: 'Commission', value: 'commission' },
            ],

        };
    },
    watch: {
        'data.service_id': function () {
            this.getpackages();
            this.getServiceInfo();
        },
        'data.district_id': 'getareas',
        'data.employee_id': 'getEmployeeName',
        'data.is_commission': function () {
            this.emptyCommissionFields();
        },
        'data.package_id': function () {
            this.updateCommissionAmount();
        },
        'data.vat'(val) {
            const hasVat = parseFloat(val) > 0;

            this.data.invoice_setup.forEach(d => {
                d.is_vat = hasVat;
            });

            this.calculateRowAmounts();
            this.calculateTotals();
        },
        'data.invoice_setup': {
            handler() {
                this.calculateRowAmounts();
                this.calculateTotals();
            },
            deep: true
        },
        package_percentage: function () {
            this.calculateClientPackageAmounts();
        },
        'data.client_packages': {
            handler() {
                this.calculateClientPackageAmounts();
            },
            deep: true
        }
    },
    provide() {
        return {
            validate: this.validation,
        };
    },
    methods: {
        getServiceInfo: function () {
            let serviceid = this.data.service_id;
            axios.get("/getserviceinfo/" + serviceid).then((res) => {
                this.data.service = res.data;
            });
        },
        getpackages: function () {
            this.packages = [];
            var serviceid = this.data.service_id;
            axios.get("/getpackages/" + serviceid).then((res) => {
                this.packages = res.data;
                this.calculateClientPackageAmounts();
            });
        },
        getareas: function () {
            this.areas = [];
            var district_id = this.data.district_id;
            if (!district_id) return Promise.resolve([]);
            return axios.get("/getareas/" + district_id).then((res) => {
                this.areas = res.data;
                return res.data;
            });
        },
        openAreaModal() {
            if (!this.data.district_id) {
                this.$toast('Please select a District first!', 'warning');
                return;
            }
            const district = this.$root.global.districts?.find(d => d.id == this.data.district_id);
            this.selectedDistrictName = district ? district.district_name : '';
            this.newArea = {
                district_id: this.data.district_id,
                area_name: '',
                status: 'active'
            };
            this.areaError = '';
            this.showAreaModal = true;

            this.$nextTick(() => {
                setTimeout(() => {
                    const input = document.getElementById('quick_area_name_input');
                    if (input) input.focus();
                }, 100);
            });
        },
        closeAreaModal() {
            this.showAreaModal = false;
            this.areaLoading = false;
            this.areaError = '';
        },
        saveArea() {
            if (!this.data.district_id) {
                this.areaError = 'Please select a District first';
                return;
            }
            if (!this.newArea.area_name || !this.newArea.area_name.trim()) {
                this.areaError = 'Area Name is required';
                return;
            }
            this.areaLoading = true;
            this.areaError = '';

            axios.post('/area', {
                district_id: this.data.district_id,
                area_name: this.newArea.area_name.trim(),
                status: 'active'
            })
            .then(async (res) => {
                this.areaLoading = false;
                const newArea = res.data?.data;
                const newAreaId = newArea ? newArea.id : null;
                const areaName = this.newArea.area_name.trim();

                this.$toast(res.data?.message || 'Area created successfully!', 'success');

                this.closeAreaModal();

                // Refresh areas for the current district
                await this.getareas();

                // Auto-select the newly created area
                if (newAreaId) {
                    this.data.area_id = newAreaId;
                } else if (areaName) {
                    const matched = this.areas.find(a => a.area_name && a.area_name.toLowerCase() === areaName.toLowerCase());
                    if (matched) {
                        this.data.area_id = matched.id;
                    }
                }
            })
            .catch((err) => {
                this.areaLoading = false;
                if (err.response?.data?.errors?.area_name) {
                    this.areaError = err.response.data.errors.area_name[0];
                } else if (err.response?.data?.exception) {
                    this.areaError = typeof err.response.data.exception === 'string' ? err.response.data.exception : 'Failed to save area';
                } else {
                    this.areaError = err.response?.data?.message || 'Failed to save area';
                }
                this.$toast(this.areaError, 'error');
            });
        },
        getbanks: function () {
            this.areas = [];
            axios.get("/getbanks/").then((res) => {
                this.banks = res.data;
            });
        },
        getEmployeeName: function () {
            this.data.ref_name =
                this.$root.global.employees.find(e => e.id === this.data.employee_id)?.full_name || '';
        },
        submit: function (e) {
            this.$validate().then((res) => {
                const error = this.validation.countErrors();

                if (error > 0) {
                    this.$toast(
                        'You need to fill ' + error + ' more empty mandatory fields',
                        'warning'
                    );
                    return false;
                }

                if (res) {
                    if (this.data.id) {
                        this.update(this.model, this.data, this.data.id);
                    } else {
                        this.store(this.model, this.data);
                    }
                }
            });
        },
        updateCommissionAmount: function () {
            let packageid = this.data.package_id;
            axios.get("/getpackagebyid/" + packageid).then((res) => {
                if (this.data.is_commission) {
                    this.data.commission_amount = res.data.commission_amount;
                }

            });
        },
        emptyCommissionFields: function () {
            if (this.data.is_commission == 0) {
                this.data.employee_id = '';
                this.data.referance_name = '';
                this.data.commission_amount = 0;
            } else {
                this.updateCommissionAmount();
            }
        },
        getDateDiffInDays(start, end) {
            if (!start || !end) return 0;
            const s = new Date(start);
            const e = new Date(end);
            const diff = e - s;
            if (diff < 0) return 0;
            return Math.floor(diff / (1000 * 60 * 60 * 24)) + 1;
        },

        getCurrentMonthDays(date) {
            const d = new Date(date);
            return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
        },
        calculateRowAmounts() {
            this.data.invoice_setup.forEach(detail => {
                // day difference
                detail.days = this.getDateDiffInDays(detail.start_date, detail.end_date);

                const bandwidth = parseFloat(detail.bandwidth) || 0;
                const rate = parseFloat(detail.price) || 0;
                const days = parseFloat(detail.days) || 0;

                //current month days
                const monthDays = detail.start_date ? this.getCurrentMonthDays(detail.start_date) : 30;


                if (detail.days == monthDays) {
                    detail.exclude_amount = (bandwidth * rate).toFixed(2);
                } else {
                    detail.exclude_amount = monthDays > 0 ? (((bandwidth * rate) / monthDays) * days).toFixed(2) : 0;
                }

                if (detail.is_vat == 1 && this.data.vat > 0) {
                    detail.include_amount = (parseFloat(detail.exclude_amount) + (parseFloat(detail.exclude_amount) * parseFloat(this.data.vat)) / 100).toFixed(2);
                } else {
                    detail.include_amount = detail.exclude_amount;
                }

            });
        },
        calculateTotals() {
            let totalBw = 0;
            let totalAmt = 0;
            let totalIncAmt = 0;

            this.data.invoice_setup.forEach(detail => {
                if (detail.type == 'DWNG') {
                    totalBw -= parseFloat(detail.bandwidth) || 0;
                    totalAmt -= parseFloat(detail.exclude_amount) || 0;
                    totalIncAmt -= parseFloat(detail.include_amount) || 0;
                } else {
                    totalBw += parseFloat(detail.bandwidth) || 0;
                    totalAmt += parseFloat(detail.exclude_amount) || 0;
                    totalIncAmt += parseFloat(detail.include_amount) || 0;
                }

            });

            this.data.total_bandwidth = totalBw;
            this.data.total_amount = totalAmt;
            this.data.total_include_amount = totalIncAmt;
        },
        getCategories() {
            let module = 'Bandwidth';
            axios.get(`getcategories/${module}`)
                .then((response) => {
                    this.categories = response.data;
                });
        },
        addBandwidthDetailsRow() {
            this.data.invoice_setup.push({
                is_vat: parseFloat(this.data.vat) > 0 ? true : false,
                type: '',
                category_id: '',
                linkid: '',
                bandwidth: 0,
                unit_id: 1,
                price: 0,          // rate
                start_date: '',
                end_date: '',
                days: 0,
                exclude_amount: 0,
                include_amount: 0,
            });
        },
        removeBandwidthDetails(index) {
            if (Object.keys(this.data.invoice_setup).length > 1) {
                this.data.invoice_setup.splice(index)
            }
        },

        addClientPackageRow() {
            this.data.client_packages.push({
                package_id: '',
                percentage: this.package_percentage || '',
                amount: '',
            });
        },
        removeClientPackage(index) {
            if (Object.keys(this.data.client_packages).length > 1) {
                this.data.client_packages.splice(index)
            }
        },
        isLastItem(items, index) {
            return index === items.length - 1;
        },
        calculateClientPackageAmounts() {
            if ((this.package_percentage === '' || this.package_percentage === null) && this.data.client_packages && this.data.client_packages.length > 0) {
                const existingPct = this.data.client_packages.find(p => p.percentage)?.percentage;
                if (existingPct !== undefined && existingPct !== null && existingPct !== '') {
                    this.package_percentage = existingPct;
                }
            }

            const pct = parseFloat(this.package_percentage);
            const hasPct = !isNaN(pct) && this.package_percentage !== '' && this.package_percentage !== null;
            this.data.percentage = hasPct ? pct : 0;

            this.data.client_packages.forEach(row => {
                row.percentage = this.package_percentage;

                if (!row.package_id) {
                    return;
                }

                // packages array থেকে package বের করা
                const packageInfo = this.packages.find(p => p.id == row.package_id);

                if (packageInfo) {
                    const packagePrice = parseFloat(packageInfo.price) || 0;
                    let calculatedAmount;

                    if (hasPct) {
                        calculatedAmount = ((packagePrice * pct) / 100).toFixed(2);
                    } else {
                        calculatedAmount = packagePrice.toFixed(2);
                    }

                    if (row.amount !== calculatedAmount) {
                        row.amount = calculatedAmount;
                    }
                }
            });
        },
    },
    created() {
        if (this.$route.params.id) {
            this.page_title = this.headline(this.model) + ' Edit';
            this.get_data(`${this.model}/${this.$route.params.id}`);
        } else {
            this.page_title = this.headline(this.model) + ' Create';
        }
        this.getbanks();
        this.getCategories();
    },

    validators: {
        'data.type': function (value = null) { return Validator.value(value).required('Type is required'); },
        'data.service_id': function (value = null) { return Validator.value(value).required('Service Id is required'); },
        'data.name': function (value = null) { return Validator.value(value).required('Name is required'); },
        'data.mobile': function (value = null) { return Validator.value(value).required('Mobile is required'); },
        'data.area_id': function (value = null) { return Validator.value(value).required('Area Id is required'); },
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

.pro-table {
    border-radius: 8px;
    overflow: hidden;
    border-color: #e2e8f0;
}

.pro-table th {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    background-color: #f8fafc !important;
    border-bottom: 2px solid #e2e8f0 !important;
}

.service-info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.custom_v_select .btn-primary {
    transition: all 0.2s ease;
}

.custom_v_select .btn-primary:hover {
    transform: scale(1.04);
}
</style>
