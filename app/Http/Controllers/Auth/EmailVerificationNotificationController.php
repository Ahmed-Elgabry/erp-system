<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */

        use AuthenticatesUsers;

        public function verify(Request $request)
        {
            $user = Auth::user();
            $enteredOTP = '';

            for ($i = 1; $i <= 4; $i++) {
                $inputName = 'otp' . $i;
                $inputValue = $request->input($inputName);
                $enteredOTP .= $inputValue;
            }

            $user = Auth::user();
            // dd($user);
            $expectedOTP = $user->otp;

            if ($enteredOTP === $expectedOTP) {

                $user->email_verified_at = Carbon::now();
                $user->save();

                return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
            } else {
                $user->delete();
                return redirect()->back()->withErrors(['otp' => 'Incorrect OTP']);
            }
        }

}
