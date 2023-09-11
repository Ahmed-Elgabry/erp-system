<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ExperienceCertificate;
use App\Models\GenerateOfferLetter;
use App\Models\JoiningLetter;
use App\Models\NOC;
use App\Models\User;
use  App\Models\Utility;
use  App\Models\SmsSetting;

use Auth;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Http;


class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */

  public function __construct()
    {
        $this->middleware('guest');
    }


    public function create()
    {
        // return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        //ReCaptcha
        if(env('RECAPTCHA_MODULE') == 'on')
        {
            $validation['g-recaptcha-response'] = 'required|captcha';
        }else{
            $validation = [];
        }
        $this->validate($request, $validation);
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|numeric|unique:users',
            'password' => ['required', 'string', 'min:8','confirmed', Rules\Password::defaults()],
        ]);

        $otp = rand(1000, 9999);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'otp' => $otp,
            'type' => 'company',
            'default_pipeline' => 1,
            'plan' => null,
            'lang' => Utility::getValByName('default_language'),
            'avatar' => '',
            'created_by' => 1,
        ]);

        $settings = Utility::settings();

        $smsSettings = SmsSetting::find(1);

        if ($smsSettings) {
            $smsApiKey = $smsSettings->sms_api_key;
            $smsSenderName = $smsSettings->sms_sender_name;

            // Send the activation message
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $smsApiKey,
                'Content-Type' => 'application/json',
            ])->post('https://app.mobile.net.sa/api/v1/send', [
                'number' => $user->phone,
                'senderName' => $smsSenderName,
                'sendAtOption' => 'Now',
                'messageBody' => 'Your otp is '. $otp,
                'allow_duplicate' => true,
            ]);

            // dd($response->json());
        }


        Auth::login($user);

        $settings = Utility::settings();

        if ($settings['email_verification'] == 'on') {
            try {
                event(new Registered($user));

                $role_r = Role::findByName('company');
                $user->assignRole($role_r);
                $user->userDefaultDataRegister($user->id);
                $user->userWarehouseRegister($user->id);

                //default bank account for new company
                $user->userDefaultBankAccount($user->id);

                Utility::chartOfAccountTypeData($user->id);
                Utility::chartOfAccountData($user);
                // default chart of account for new company
                Utility::chartOfAccountData1($user->id);

                Utility::pipeline_lead_deal_Stage($user->id);
                Utility::project_task_stages($user->id);
                Utility::labels($user->id);
                Utility::sources($user->id);
                Utility::jobStage($user->id);
                GenerateOfferLetter::defaultOfferLetterRegister($user->id);
                ExperienceCertificate::defaultExpCertificatRegister($user->id);
                JoiningLetter::defaultJoiningLetterRegister($user->id);
                NOC::defaultNocCertificateRegister($user->id);
            } catch (\Exception $e) {
                $user->delete();
                return redirect('/register/lang?')->with('status', __('Email SMTP settings does not configure so please contact your site admin.'));
            }
            return view('auth.verify');
        } else {
            $user->email_verified_at = date('h:i:s');
            $user->save();
            $role_r = Role::findByName('company');
            $user->assignRole($role_r);
            $user->userDefaultData($user->id);
            $user->userDefaultDataRegister($user->id);
            GenerateOfferLetter::defaultOfferLetterRegister($user->id);
            ExperienceCertificate::defaultExpCertificatRegister($user->id);
            JoiningLetter::defaultJoiningLetterRegister($user->id);
            NOC::defaultNocCertificateRegister($user->id);
            return redirect(RouteServiceProvider::HOME);
        }
    }


    public function showRegistrationForm($lang = '')
    {

        $settings = Utility::settings();
//    $lang = $settings['default_language'];

        if($settings['enable_signup'] == 'on')
        {
            if($lang == '')
            {
                $lang = Utility::getValByName('default_language');
            }
            \App::setLocale($lang);

            return view('auth.register', compact('lang'));
        }
        else
        {
            return \Redirect::to('login');
        }
    }

}
