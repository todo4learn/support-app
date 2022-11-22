<?php

namespace App\Http\Controllers\User\Auth;

use Auth;
use GeoIP;
use Response;

use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\Apptitle;
use App\Models\Customer;
use App\Models\Seosetting;
use Illuminate\Http\Request;
use App\Models\CustomerSetting;
use App\Models\SocialAuthSetting;
use App\Traits\SocialAuthSettings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesUsers;




class LoginController extends Controller

{
  use SocialAuthSettings, ThrottlesLogins, AuthenticatesUsers;

  public function showLoginForm()
    {

        $title = Apptitle::first();
        $data['title'] = $title;

        $socialAuthSettings = SocialAuthSetting::first();
        $data['socialAuthSettings'] = $socialAuthSettings;

        $seopage = Seosetting::first();
        $data['seopage'] = $seopage;

        return view('user.auth.login')->with($data);
    }

    public function login(Request $request)
    {
        if(setting('CAPTCHATYPE') == 'off'){
            $request->validate([
                'email'     => 'required|max:255',
                'password'  => 'required|min:6|max:255',

            ]);
        }else{
            if(setting('CAPTCHATYPE') == 'manual'){
                if(setting('RECAPTCH_ENABLE_LOGIN')=='yes'){
                    $this->validate($request, [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                        'captcha' => ['required', 'captcha'],

                    ]);
                }else{
                    $this->validate($request, [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                    ]);
                }

            }
            if(setting('CAPTCHATYPE') == 'google'){
                if(setting('RECAPTCH_ENABLE_LOGIN')=='yes'){
                    $this->validate($request, [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                        'g-recaptcha-response'  =>  'required|recaptcha',

                    ]);
                }else{
                    $this->validate($request, [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                    ]);
                }
            }
        }

        $credentials  = $request->only('email', 'password');
        $customerExist = Customer::where(['email' => $request->email, 'status' => 0])->exists();

        if ($customerExist) {
            return redirect()->back()->with('error',trans('langconvert.functions.customerinactive'));
        }

        $unverifiedCustomer = Customer::where('email', $request->email)->first();

        if (!empty($unverifiedCustomer) && $unverifiedCustomer->verified == 0) {
            return redirect()->back()->with('error',trans('langconvert.functions.unverifyuser'));
        }

        if (empty($unverifiedCustomer)) {

            // SSO like...

            // Tentative de connexion à SIG AGEROUTE

            $response = Http::withHeaders([
                'allowed_origins' => '*'
            ])->post(config('app.sig_api_url') . 'login', [
                'username' => $credentials['email'],
                'password' => $credentials['password'],
            ]);

            // dd($response->json(), $response->successful(), $response->failed(), $response->clientError(), $response->serverError());

            if ($response->successful()) {

                $data = $response->json()['data'];

                $geolocation = GeoIP::getLocation(request()->getClientIp());

                $clearPassword = $credentials['password'];

                $newCustomer = new Customer();
                $newCustomer->firstname =  $data['nom'];
                $newCustomer->lastname =  $data['nom'];
                $newCustomer->username = $data['nom'];
                $newCustomer->email =  $data['email'];
                $newCustomer->password =  Hash::make($clearPassword);
                $newCustomer->userType = 'Customer';
                $newCustomer->country = $geolocation->country;
                $newCustomer->timezone = $geolocation->timezone;
                $newCustomer->status =  true;
                $newCustomer->verified =  true;
                $newCustomer->image = null;
                $newCustomer->save();

                $customersetting = new CustomerSetting();
                $customersetting->custs_id = $newCustomer->id;
                $customersetting->darkmode = setting('DARK_MODE');
                $customersetting->save();

                $credentials = [
                    'email' => $newCustomer->email,
                    'password' => $clearPassword
                ];

                if ($data['role'] === "ROLE_USER") {
                    $newCustomer->givePermissionTo('View hidden Article');
                }
            } else {
                return redirect()->back()->with('error',trans('langconvert.functions.nonregisteruser'));
            }
        }

        if (Auth::guard('customer')->attempt($credentials)) {

            $cust = Customer::find(Auth::guard('customer')->id());
            $geolocation = GeoIP::getLocation(request()->getClientIp());
            $cust->update([
                'last_login_at' => Carbon::now()->toDateTimeString(),
                'last_login_ip' => $geolocation->ip,
                'timezone' => $geolocation->timezone,
                'country' => $geolocation->country,
            ]);

            return redirect()->route('client.dashboard');
        }

        return back()->withInput()->withErrors(['email' => trans('langconvert.functions.invalidemailpass')]);

    }


    public function ajaxlogin(Request $request){
        if(setting('CAPTCHATYPE') == 'off'){
            $validator = Validator::make($request->all(), [
                'email'     => 'required|max:255',
                'password'  => 'required|min:6|max:255',
            ]);

        }else{
            if(setting('CAPTCHATYPE') == 'manual'){
                if(setting('RECAPTCH_ENABLE_LOGIN')=='yes'){
                    $validator = Validator::make($request->all(), [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                        'captcha' => ['required', 'captcha'],
                    ]);

                }else{
                    $validator = Validator::make($request->all(), [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                    ]);

                }

            }
            if(setting('CAPTCHATYPE') == 'google'){
                if(setting('RECAPTCH_ENABLE_LOGIN')=='yes'){
                    $validator = Validator::make($request->all(), [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                        'grecaptcharesponse'  =>  'recaptcha',
                    ]);

                }else{
                    $validator = Validator::make($request->all(), [
                        'email'     => 'required|max:255',
                        'password'  => 'required|min:6|max:255',
                    ]);

                }
            }
        }



        if ($validator->passes()) {
            $user = $request->email;
            $pass  = $request->password;
            $customerExist = Customer::where(['email' => $request->email, 'status' => 0])->exists();

        if ($customerExist) {
            return response()->json([ [5] ]);
        }
        $unverifiedCustomer = Customer::where('email', $request->email)->first();

        if (!empty($unverifiedCustomer) && $unverifiedCustomer->verified == 0) {
            return response()->json([ [4] ]);
        }
        if (Auth::guard('customer')->attempt(array('email' => $user, 'password' => $pass)))
        {
            $cust = Customer::find(Auth::guard('customer')->id());
            $cust->update([
                'last_login_at' => Carbon::now()->toDateTimeString(),
                'last_login_ip' => $request->getClientIp()
            ]);
            return response()->json([ [1] ]);

        }
        else
         {
            return response()->json([ [3] ]);
         }
        }
        else {
            return Response::json(['errors' => $validator->errors()]);
        }
    }

    public function ajaxslogin(Request $request)
    {
        $user = $request->email;
        $pass  = $request->password;
        $pass  = $request->grecaptcha;

        $customerExist = Customer::where(['email' => $request->email, 'status' => 0])->exists();

        if ($customerExist) {
            return response()->json([ [5] ]);
        }
        $unverifiedCustomer = Customer::where('email', $request->email)->first();

        if (!empty($unverifiedCustomer) && $unverifiedCustomer->verified == 0) {
            return response()->json([ [4] ]);
        }
        if (Auth::guard('customer')->attempt(array('email' => $user, 'password' => $pass)))
        {
            $cust = Customer::find(Auth::guard('customer')->id());
            $geolocation = GeoIP::getLocation(request()->getClientIp());
            $cust->update([
                'last_login_at' => Carbon::now()->toDateTimeString(),
                'last_login_ip' => $geolocation->ip,
                'timezone' => $geolocation->timezone,
                'country' => $geolocation->country,
            ]);
            return response()->json([ [1] ]);

        }
        else
         {
            return response()->json([ [3] ]);
         }
    }


    public function logout()
    {

        request()->session()->flush();
        Auth::guard('customer')->logout();
        if(setting('REGISTER_POPUP') == 'yes'){

            return redirect()->route('home')->with('error',trans('langconvert.functions.logoutuser'));
        }else{
            return back()->with('error',trans('langconvert.functions.logoutuser'));
        }


    }

    // Social Login

    public function socialLogin($social)
    {
            $this->setSocailAuthConfigs();

            return Socialite::driver($social)->redirect();
    }
   /**
    * Obtain the user information from Social Logged in.
    * @param $social
    * @return Response
    */
    public function handleProviderCallback($social)
    {

        $this->setSocailAuthConfigs();
        $user = Socialite::driver($social)->user();
        $this->registerOrLogin($user);
        return redirect('customer/');
      }

      protected function registerOrLogin($data){

        $user = Customer::where('email', '=', $data->email)->first();
        if(!$user){

            $user = new Customer();
            $user->username = $data->name;
            $user->email = $data->email;
            $user->provider_id = $data->id;
            $user->status = '1';
            $user->verified = '1';
            $user->userType = 'Customer';
            $user->save();

        }
        Auth::guard('customer')->login($user);

    }

}
