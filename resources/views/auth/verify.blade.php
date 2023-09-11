@extends('layouts.auth')
@section('page-title')
    {{__('Verify Email')}}
@endsection
@php
  //  $logo=asset(Storage::url('uploads/logo/'));
      $logo=\App\Models\Utility::get_file('uploads/logo');
      $company_logo=Utility::getValByName('company_logo');
      if(empty($lang))
      {
          $lang = Utility::getValByName('default_language');
      }
@endphp

@section('auth-topbar')
    <select class="btn btn-primary my-1 me-2" onchange="this.options[this.selectedIndex].value && (window.location = this.options[this.selectedIndex].value);" id="language">
        @foreach(Utility::languages() as $language)
            <option class="" @if($lang == $language) selected @endif value="{{ route('verification.notice',$language) }}">{{Str::upper($language)}}</option>
        @endforeach
    </select>
@endsection
@section('content')
<style>

.title{
  font-weight:800;
  margin-top:20px;
  font-size:28px !important;
}

.form-otp input {
    margin: 0;
    font-family: inherit;
    font-size: 23px;
    line-height: inherit;
}

.customBtn{
  padding:10px;
}

.form-otp form input{
  display:inline-block;
  width:50px;
  height:50px;
  text-align:center;
  color: rgb(7, 213, 7);
}
</style>
    <div class="col-xl-12">
        <div class="">
            @if (session('status') == 'verification-link-sent')
                <div class="mb-4 font-medium text-sm text-green-600 text-primary">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </div>
            @endif
            <div class="mb-4 text-sm text-gray-600">
                {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
            </div>
            <div class="mt-4 flex items-center justify-between">
                <div class="row">
                    <div class="justify-content-md-center form-otp">
                        <div class="col-md-12 text-center">
                          <div class="row">
                            <div class="col-sm-12 mt-5 bgWhite">
                              <div class="title">
                                Verify OTP
                              </div>

                              <form action="{{ route('verify-otp') }}" method="POST" class="mt-5">
                                @csrf
                                <input class="otp" type="text" name="otp1" oninput='digitValidate(this)' onkeyup='tabChange(1)' maxlength=1 >
                                <input class="otp" type="text" name="otp2" oninput='digitValidate(this)' onkeyup='tabChange(2)' maxlength=1 >
                                <input class="otp" type="text" name="otp3" oninput='digitValidate(this)' onkeyup='tabChange(3)' maxlength=1 >
                                <input class="otp" type="text" name="otp4" oninput='digitValidate(this)'onkeyup='tabChange(4)' maxlength=1 >
                                <hr class="mt-4">
                                <button type="submit" class='btn btn-primary btn-block mt-4 mb-4 customBtn w-100' id="verifyBtn">Verify</button>
                              </form>
                              <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-danger w-100">{{ __('Logout') }}</button>
                            </form>
                            </div>
                          </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
    let digitValidate = function(ele){
            console.log(ele.value);
            ele.value = ele.value.replace(/[^0-9]/g,'');
        }

        let tabChange = function(val){
            let ele = document.querySelectorAll('input');
            if(ele[val-1].value != ''){
            ele[val].focus()
            }else if(ele[val-1].value == ''){
            ele[val-2].focus()
            }
        }
    </script>
@endsection()
