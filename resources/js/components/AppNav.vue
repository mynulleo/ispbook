<template>
    <header class="header_area w-100">
        <div class="row">
            <div class="col-md-4 col-4 align-self-center">
                <div class="header_left">
                    <div class="main d-flex gap-2 align-items-center">
                        <div class="sidebar_control_bar">
                            <button type="button" class="sidebar_control_btn border-0" data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-title="Navbar" v-x-tooltip>
                                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-menu-deep">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M4 6h16" />
                                    <path d="M7 12h13" />
                                    <path d="M10 18h10" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8 col-8 align-self-center">
                <div class="header_right d-flex align-items-center justify-content-end gap-4">
                    <div class="date_time position-relative">
                        <p id="currentDateTime"></p>
                    </div>

                    <!-- Database Update Button (Visible when pending migrations exist) -->
                    <div v-if="dbStatus.has_pending" key="db-update-btn-container" class="db_update_btn_wrapper">
                        <button
                            type="button"
                            class="btn btn-sm btn-warning d-flex align-items-center gap-2 fw-bold text-dark shadow-sm px-3 py-1 pulse_db_btn"
                            @click="openDbModal"
                            title="Database updates are available"
                        >
                            <i class="fa-solid fa-database"></i>
                            <span>Update Now</span>
                            <span class="badge bg-danger text-white rounded-pill ms-1">{{ dbStatus.pending_count }}</span>
                        </button>
                    </div>

                    <div class="action_info d-flex gap-4 align-items-center">
                        <div class="icon_box position-relative">
                            <button type="button"
                                class="icon_btn dropdown_menu bg-transparent border-0 position-relative"
                                data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Messages" v-x-tooltip>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-message">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M8 9h8" />
                                    <path d="M8 13h6" />
                                    <path
                                        d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z" />
                                </svg>
                                <sup
                                    class="notification_number position-absolute rounded-pill d-flex justify-content-center align-items-center">{{
                                        $root.global?.notify_contacts?.length >
                                            5
                                            ? "5+"
                                            : $root.global?.notify_contacts
                                                ?.length
                                    }}</sup>
                            </button>
                            <div
                                class="information_box dropdown_menu_info messages_information position-absolute top-100 end-0 x_top_message_dropdown_box">
                                <div class="top d-flex justify-content-between align-items-center gap-4">
                                    <h3 class="title">Messages</h3>
                                </div>
                                <div class="list">
                                    <ul class="list-unstyled">
                                        <li>
                                            <h4 class="no_list">
                                                <div>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="1.5" stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        class="icon icon-tabler icons-tabler-outline icon-tabler-alarm-minus">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                        <path d="M12 13m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                                                        <path d="M7 4l-2.75 2" />
                                                        <path d="M17 4l2.75 2" />
                                                        <path d="M10 13h4" />
                                                    </svg>
                                                </div>
                                                No Messages Found
                                            </h4>
                                        </li>
                                    </ul>
                                </div>
                                <div class="btm text-center">

                                </div>
                            </div>
                        </div>
                        <div class="user_box position-relative">
                            <button type="button"
                                class="user_image dropdown_menu rounded-pill bg-transparent border-0 position-relative"
                                data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Profile" v-x-tooltip>
                                <img class="rounded-pill w-100 h-100 object-fit-cover" :src="user?.profile_three ??
                                    `${$root.asset_url}/images/profile.jpg`
                                    " alt="profile-user" />
                            </button>
                            <div class="user_information position-absolute dropdown_menu_info">
                                <div class="edit_image text-center">
                                    <div class="image position-relative">
                                        <img :src="user?.profile_one ??
                                            `${$root.asset_url}/images/profile.jpg`
                                            " :href="user?.profile_two" v-x-zoom-image alt="profile-edit" />
                                    </div>
                                    <div class="name text-center">
                                        <h4 class="nm">
                                            {{ $root.user.name }}
                                        </h4>
                                        <router-link :to="{
                                            name: 'profile.profileDetails',
                                        }" class="edit">Edit Your Profile</router-link>
                                    </div>
                                    <div class="menus text-start">
                                        <ul class="list-unstyled">
                                            <li v-for="(
profileMenu,
    profileMenuIndex
                                                ) in $root.global
        ?.profile_menus" :key="`profile_menu_${profileMenuIndex}`">
                                                <router-link v-if="profileMenu.params" :to="{
                                                    name: profileMenu.route_name,
                                                    params: {
                                                        slug: profileMenu.params,
                                                    },
                                                }">
                                                    <span class="menu_icon" v-if="profileMenu.icon" v-html="profileMenu.icon
                                                        "></span>
                                                    {{ profileMenu.menu_name }}
                                                </router-link>

                                                <router-link v-else :to="{
                                                    name: profileMenu.route_name,
                                                }">
                                                    <span v-if="profileMenu.icon" v-html="profileMenu.icon
                                                        "></span>
                                                    {{ profileMenu.menu_name }}
                                                </router-link>
                                            </li>

                                            <li>
                                                <a href="javascript:void(0)" @click.prevent="logout()">
                                                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                                    Log Out
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pure Vue Database Migration Modal (Teleported to body) -->
        <Teleport to="body">
            <div v-if="showDbModal" key="db-migration-modal-backdrop" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.55); z-index: 1060;" @click.self="closeDbModal">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">
                        <div class="modal-header bg-warning bg-opacity-25 border-bottom border-warning py-3">
                            <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2 mb-0">
                                <i class="fa-solid fa-database text-warning"></i>
                                <span>Database Migration & Update</span>
                            </h5>
                            <button type="button" class="btn-close" :disabled="isUpdatingDb" @click="closeDbModal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="alert alert-info d-flex align-items-center gap-3 mb-3 border-0 shadow-sm" style="background: #e0f2fe; color: #0369a1;">
                                <i class="fa-solid fa-circle-info fa-2x"></i>
                                <div>
                                    <div class="fw-bold fs-6">Target Database: <code>{{ dbStatus.database }}</code> (Connection: <code>{{ dbStatus.connection }}</code>)</div>
                                    <div class="small mt-1 text-secondary">
                                        There are <strong class="text-dark">{{ dbStatus.pending_count }}</strong> pending database migration(s) ready to be applied.
                                    </div>
                                </div>
                            </div>

                            <!-- Pending Migrations List -->
                            <div class="card mb-3 border">
                                <div class="card-header bg-light py-2 px-3 fw-semibold d-flex justify-content-between align-items-center">
                                    <span class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-list-check text-primary"></i>
                                        <span>Pending Migration Files</span>
                                    </span>
                                    <span class="badge bg-primary rounded-pill">{{ dbStatus.pending_migrations?.length || 0 }} files</span>
                                </div>
                                <div class="card-body p-2" style="max-height: 180px; overflow-y: auto;">
                                    <ul class="list-group list-group-flush small" v-if="dbStatus.pending_migrations && dbStatus.pending_migrations.length">
                                        <li v-for="(mig, idx) in dbStatus.pending_migrations" :key="idx" class="list-group-item py-2 px-3 d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-secondary border">{{ idx + 1 }}</span>
                                            <code class="text-primary fw-semibold">{{ mig }}</code>
                                        </li>
                                    </ul>
                                    <div v-else class="text-muted p-2 text-center small">No pending migrations found.</div>
                                </div>
                            </div>

                            <!-- Console Output View -->
                            <div v-if="dbOutput" key="card-db-output" class="card border-0 mb-3" style="background: #0f172a; border-radius: 8px; overflow: hidden;">
                                <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center" style="background: #1e293b; color: #94a3b8; font-size: 13px;">
                                    <span class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-terminal text-warning"></i>
                                        <span>Migration Execution Log</span>
                                    </span>
                                    <span v-if="updateSuccess" class="badge bg-success">Success</span>
                                    <span v-else-if="isUpdatingDb" class="badge bg-info">Running...</span>
                                </div>
                                <div class="card-body p-3 font-monospace small" style="max-height: 180px; overflow-y: auto; white-space: pre-wrap; font-size: 12px; line-height: 1.6; color: #4ade80;">{{ dbOutput }}</div>
                            </div>

                            <!-- Error Alert -->
                            <div v-if="dbError" key="alert-db-error" class="alert alert-danger py-2 px-3 small border-0 shadow-sm d-flex align-items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>{{ dbError }}</span>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 px-3">
                            <button type="button" class="btn btn-secondary btn-sm px-3" :disabled="isUpdatingDb" @click="closeDbModal">
                                <span>{{ updateSuccess ? 'Close' : 'Cancel' }}</span>
                            </button>
                            <button v-if="!updateSuccess" key="btn-run-migration" type="button" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" :disabled="isUpdatingDb" @click="runDbUpdate">
                                <template v-if="isUpdatingDb">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <span>Migrating Database...</span>
                                </template>
                                <template v-else>
                                    <i class="fa-solid fa-play me-1"></i>
                                    <span>Run Migration Now</span>
                                </template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </header>
</template>

<script>
import { mapState } from "vuex";

export default {
    computed: {
        ...mapState("setting", ["colors"]),
    },
    data() {
        return {
            profile: false,
            message: false,
            notification: false,
            dbStatus: {
                has_pending: false,
                pending_count: 0,
                pending_migrations: [],
                database: '',
                connection: '',
            },
            showDbModal: false,
            isUpdatingDb: false,
            dbOutput: '',
            dbError: '',
            updateSuccess: false,
        };
    },
    methods: {
        async logout() {
            const res = await this.callApi("post", "logout", null, false);
            if (res.status == 200) {
                this.$store.dispatch("auth/logout");
                window.location.href = this.$root.baseurl + "/qpanel";
            }
        },

        async checkDbStatus() {
            try {
                const res = await this.callApi("get", "database-status", null, false);
                if (res && res.status === 200 && res.data) {
                    this.dbStatus = res.data;
                }
            } catch (e) {
                console.error("DB status check error:", e);
            }
        },

        openDbModal() {
            this.showDbModal = true;
            this.dbOutput = '';
            this.dbError = '';
            this.updateSuccess = false;
        },

        closeDbModal() {
            if (this.isUpdatingDb) return;
            this.showDbModal = false;
        },

        async runDbUpdate() {
            this.isUpdatingDb = true;
            this.dbError = '';
            this.dbOutput = 'Executing pending migrations, please wait...';
            try {
                const res = await this.callApi("post", "database-update", null, false);
                if (res && res.status === 200 && res.data.success) {
                    this.dbOutput = res.data.output || 'All migrations executed successfully!';
                    this.updateSuccess = true;
                    this.dbStatus.has_pending = false;
                    this.dbStatus.pending_count = 0;
                    this.dbStatus.pending_migrations = [];
                    this.$toast(res.data.message || 'Database updated successfully!', 'success');
                    setTimeout(() => {
                        this.checkDbStatus();
                    }, 1500);
                } else {
                    this.dbError = res?.data?.message || 'Database migration failed.';
                    this.$toast(this.dbError, 'error');
                }
            } catch (e) {
                this.dbError = e.response?.data?.message || e.message || 'Migration request failed';
                this.$toast(this.dbError, 'error');
            } finally {
                this.isUpdatingDb = false;
            }
        },

        loggedInfo() {
            const today = new Date();
            const options = {
                weekday: "long",
                day: "numeric",
                month: "long",
                year: "numeric",
            };
            const formattedDate = today.toLocaleDateString("en-US", options);
            const loggedInfo = `You Logged as ${this.ucfirst(
                this.$root.user.name
            )}`;

            return `${loggedInfo}, ${formattedDate}`;
        },
    },

    mounted() {
        // collapsed sidebar js
        $(".control-bar i").click(function () {
            $("body").toggleClass("collapsed-menu");
        });

        $(".mobile-control-bar i").click(function () {
            $(".navigation-body").addClass("show-mobile-sidebar");
            body.style.overflow = "hidden";
        });

        $(".mobile-control-bar i").click(function () {
            $(".toggle-overlay").addClass("show-toggle-overlay");
            body.style.overflow = "hidden";
        });

        $(".close-mobile-menu i").click(function () {
            $(".navigation-body").removeClass("show-mobile-sidebar");
            body.style.overflow = "auto";
        });

        $(".close-mobile-menu i").click(function () {
            $(".toggle-overlay").removeClass("show-toggle-overlay");
            body.style.overflow = "auto";
        });

        $(".toggle-overlay").click(function () {
            $(".toggle-overlay").removeClass("show-toggle-overlay");
            body.style.overflow = "auto";
        });

        $(".toggle-overlay").click(function () {
            $(".navigation-body").removeClass("show-mobile-sidebar");
            body.style.overflow = "auto";
        });

        // Request full screen js
        const arrows = document.querySelector(".fa-arrows-alt");
        const body = document.querySelector("body");

        const toggleFullscreen = () => {
            if (document.fullscreenElement) document.exitFullscreen();
            else body.requestFullscreen();
        };

        // fixed header part js
        $(window).scroll(function () {
            let scrolling = $(this).scrollTop();
            if (scrolling > 0) {
                $(".top-header").addClass("fixed");
            } else {
                $(".top-header").removeClass("fixed");
            }
        });

        // Check for pending database updates
        this.checkDbStatus();
    },
};
</script>

<style scoped>
.margin-top-10 {
    margin-top: 10px !important;
}

@keyframes pulseGlow {
    0% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(255, 193, 7, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
    }
}

.pulse_db_btn {
    animation: pulseGlow 2s infinite;
    font-size: 13px;
    border-radius: 6px;
    transition: all 0.2s ease-in-out;
}

.pulse_db_btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15) !important;
}
</style>
