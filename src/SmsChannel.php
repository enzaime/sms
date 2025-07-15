<?php

namespace Enzaime\Sms;

use BadMethodCallException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/**
 * Class SmsChannel
 *
 * Laravel notification channel for sending SMS.
 *
 * @package Enzaime\Sms
 */
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
        $message = $this->getMessage($notifiable, $notification);
        $contactNumber = $this->getMobileNumber($notifiable);
        $sms = new SmsService();
        $sms->send($contactNumber, $message);
    }

    /**
     * Get the mobile number from the notifiable entity.
     *
     * @param mixed $notifiable
     * @return string
     * @throws BadMethodCallException
     */
    private function getMobileNumber($notifiable)
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms($notifiable);
        }
        if ($notifiable instanceof AnonymousNotifiable && isset($notifiable->routes['\\' . self::class])) {
            return $notifiable->routes['\\' . self::class];
        }
        $mobile = $notifiable->mobile ?: $notifiable->contact_no;
        if (! $mobile) {
            throw new BadMethodCallException(get_class($notifiable) . ' class must contains either a method named routeNotificationForSms or a property named mobile/contact_no ');
        }
        return $mobile;
    }

    /**
     * Get the SMS message from the notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     * @return string
     * @throws BadMethodCallException
     */
    public function getMessage($notifiable, Notification $notification)
    {
        if (method_exists($notification, 'toSms')) {
            return $notification->toSms($notifiable);
        }
        if (method_exists($notification, 'toSMS')) {
            return $notification->toSMS($notifiable);
        }
        throw new BadMethodCallException(get_class($notification) . ' class must contains a method named toSms or toSMS ');
    }
}
