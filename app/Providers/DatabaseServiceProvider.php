<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\System\Menu;
use App\Models\System\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\System\RolePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * Register services.
     */
    public function register()
    {
        /*----- SITE SETTINGS (Tenant Aware) -----*/
        $this->app->singleton('siteSettingObj', function ($app) {
            $user = Auth::guard('admin')->user() ?? Auth::user();
            if (!$user) return [];

            $orgId = $user->organization_id ?? $user->residence_id ?? null;
            $this->switchTenantDB($orgId);

            return Cache::rememberForever('site_setting_cache_' . $orgId, function () {
                $site = SiteSetting::first();
                return $site ? $site->toArray() : [];
            });
        });

        /*----- LEFT MENUS (Tenant Aware) -----*/
        $this->app->singleton('sideMenus', function ($app) {
            $user = Auth::guard('admin')->user() ?? Auth::user();
            if (!$user) return [];

            $orgId = $user->organization_id ?? $user->residence_id ?? null;
            $this->switchTenantDB($orgId);

            return Menu::menus();
        });

        /*----- PERMITTED MENUS / ROLE PERMISSIONS (Tenant Aware) -----*/
        $this->app->singleton('permittedMenuArr', function ($app) {
            $user = Auth::guard('admin')->user() ?? Auth::user();
            if (!$user) return [];

            $orgId = $user->organization_id ?? $user->residence_id ?? null;
            $this->switchTenantDB($orgId);

            $obj = RolePermission::permissions();
            if ($obj->count()) {
                return RolePermission::permissionProcess($obj);
            }

            return [];
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        //
    }

    /**
     * Switch to tenant DB based on organization_id / residence_id
     */
    private function switchTenantDB($orgId)
    {
        if (!$orgId) return;

        $organization = Organization::find($orgId);
        if (!$organization || !$organization->db_name) return;

        config([
            'database.connections.tenant' => [
                'driver' => 'mysql',
                'host' => $organization->db_host ?: env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $organization->db_name,
                'username' => $organization->db_user ?: env('DB_USERNAME', 'root'),
                'password' => $organization->db_password ?: env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => 'InnoDB',
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ]
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
    }
}
