<?php

namespace Enzaime\Sms;

use Illuminate\Notifications\Notification;
 
class SmsChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toSms($notifiable);

        $contactNumber = $this->getMobileNumber($notifiable);
        $sms = new SmsService();

        $sms->send($contactNumber, $message);
    }

    private function getMobileNumber($notifiable)
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms($notifiable);
        } 

        return $notifiable->mobile ?: $notifiable->contact_no;
    }
}