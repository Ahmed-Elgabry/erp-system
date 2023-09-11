<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;

use App\Models\SmsSetting;
use App\Models\SmsSettingNotification;
use Illuminate\Support\Facades\DB;

class SmsSettingController extends Controller
{
    public function saveSmsSetting(Request $request)
    {

        $request->validate([
            'sms_api_key'    => 'required|string|max:255',
            'sms_sender_name' => 'required|string|max:255',
        ]);

        $sms = SmsSetting::updateOrCreate(
            ['id' => 1],
            [
                'sms_api_key'     => $request->sms_api_key,
                'sms_sender_name' => $request->sms_sender_name
            ]
        );

        return redirect()->back()->with('success', __('Sms Setting successfully updated.'));
    }

    public function saveSmsSettingNotification(Request $request)
    {
        $request->validate([
            'sms_api_key'    => 'required|string|max:255',
            'sms_sender_name' => 'required|string|max:255',
        ]);

        $data = $request->only('sms_api_key', 'sms_sender_name');
        $userId = \Auth::user()->id;

        foreach ($data as $key => $value) {

            $sql = "INSERT INTO sms_setting_notifications (`name`, `value`, `created_by`) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `value` = VALUES(`value`)";

            $params = [$key, $value, $userId];

            DB::statement($sql, $params);
        }

        return redirect()->back()->with('success', __('Sms Setting successfully updated.'));
    }
}
