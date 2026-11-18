<?php

/**
 * Dev: @Quill Information Technology
 */

namespace App\Http\Controllers\Auth;

use App\Models\Admin;
use App\Models\Organization;
use Illuminate\Http\Request;
use App\Traits\Auth\LoginTrait;
use App\Traits\Auth\ResetTrait;
use App\Models\OrganizationUser;
use App\Traits\Auth\ForgetTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AdminLoginController extends Controller
{
    use ForgetTrait, LoginTrait, ResetTrait;

    public function __construct()
    {
        $this->middleware('guest:admin')->except('logout', 'loginCheck');
    }

    /**
     * Process the user login
     *
     * @return response
     */
    public function login(Request $request)
    {
        if (app()->environment('production')) {
            $rules['g-recaptcha-response'] = 'required';
        }

        if ($request->isMethod('post')) {
            if ($this->validateCheck($request)) {
                $email = $request->email;
                $arr = [
                    'email' => $email,
                    'password' => $request->password,
                    'status' => 'active',
                ];

                $organizationUser = OrganizationUser::with('organization:id,organization_name,db_name,db_user,db_password,db_host,block,expired_date,status')->where('email', $email)->first();

                if (!empty($organizationUser)) {
                    if ($organizationUser->block == 0) {
                        if (Auth::guard('admin')->attempt($arr, $request->remember)) {
                            Session::forget($email);
                            Artisan::call('cache:clear');

                            $user = Auth::guard('admin')->user();
                            $organization = $user->organization ?? Organization::find($user->organization_id ?? $user->residence_id);

                            // Switch to tenant DB for this request
                            if ($organization && $organization->db_name) {
                                Config::set('database.connections.tenant', [
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
                                ]);
                                DB::purge('tenant');
                                DB::reconnect('tenant');
                                DB::setDefaultConnection('tenant');
                            }

                            // Store the login details.
                            $userId = $user->id;
                            $this->storeLoginDetails($userId, 'admin');

                            return response([
                                // 'message' => 'Logged in successfully',
                                'user' => $user,
                                'role' => $user->role->name ?? '',
                                'organization_id' => $user->organization_id ?? '',
                                'expired_date' => $organization->expired_date ?? '',
                                'redirect_url' => $request->session()->pull('url.intended', route('dashboard.index')),
                            ], 200);
                        } else {
                            $attempt = $this->attemptAdmin($organizationUser->id, $email);
                            if ($attempt <= 3 && $attempt != 0) {
                                $message = "You have {$attempt} login attempts remaining";
                            } elseif ($attempt == 0) {
                                $message = 'Your account has been blocked. Please contact your administrator for assistance';
                            } else {
                                $message = 'The login information provided is incorrect';
                            }

                            return response([
                                'message' => $message,
                            ], 201);
                        }
                    } else {
                        Session::forget($email);

                        return response([
                            'message' => 'Your account has been blocked. Please contact your administrator for assistance',
                        ], 201);
                    }
                } else {
                    return response([
                        'message' => 'The provided email does not match our records',
                    ], 201);
                }
            }
        } elseif ($request->isMethod('get')) {
            return view('admin.layouts.login_app', ['token' => $request->query('token'), 'email' => $request->query('email')]);
        } else {
            return view('admin.layouts.login_app', ['token' => $request->query('token'), 'email' => $request->query('email')]);
        }
    }

    /**
     * Attempt to login as admin
     *
     * @param [type] $id
     * @param [type] $email
     * @return response
     */
    private function attemptAdmin($id, $email)
    {
        $count = Session::get($email) ?? 0;
        $inc = $count + 1;
        Session::put($email, $inc);

        $attempt = 6 - $inc;
        if ($attempt == 0) {
            Session::forget($email);
            OrganizationUser::where('id', $id)->update(['block' => 1]);
        }

        return $attempt;
    }

    /**
     * Logout the authenticated user
     *
     * @return response
     */
    public function logout()
    {
        Artisan::call('cache:clear');
        $logout = Auth::guard('admin')->logout();
        return response([
            'message' => 'You have been successfully logged out',
        ], 200);
    }

    /**
     * Check auth
     *
     * @return response
     */
    public function loginCheck()
    {
        if ($user = Auth::guard('admin')->user()) {
            $organization = $user->organization ?? Organization::find($user->organization_id ?? $user->residence_id);
            return response([
                'message' => 'Login Successfully',
                'user' => $user,
                'role' => $user->role->name ?? '',
                'organization_id' => $user->organization_id ?? '',
                'expired_date' => $organization->expired_date ?? '',
            ], 200);
        }

        return response(['message' => 'Unauthorized'], 201);
    }

    /**
     * Validation on login request
     *
     * @param [type] $request
     * @return bool
     */
    public function validateCheck($request)
    {
        return $request->validate([
            'email' => 'required|email:rfc,|max:100',
            'password' => 'required|min:3',
            'g-recaptcha-response' => 'captcha'
        ], [], [
            'g-recaptcha-response' => 'captcha'
        ]);
    }
}
