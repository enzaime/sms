<?php

namespace Enzaime\Sms;

use BadMethodCallException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/**
 * Class SmsChannel
 *
 * Laravel notification channel for sending SMS.
 */
class SmsChannel
{
    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $message = $this->getMessage($notifiable, $notification);
        $contactNumber = $this->getMobileNumber($notifiable);
        $sms = new SmsService;
        $sms->send($contactNumber, $message);
    }

    /**
     * Get the mobile number from the notifiable entity.
     *
     * @throws BadMethodCallException
     */
    private function getMobileNumber(mixed $notifiable): string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms($notifiable);
        }
        if ($notifiable instanceof AnonymousNotifiable && isset($notifiable->routes['\\'.self::class])) {
            return $notifiable->routes['\\'.self::class];
        }
        $mobile = $notifiable->mobile ?: $notifiable->contact_no;
        if (! $mobile) {
            throw new BadMethodCallException(get_class($notifiable).' class must contains either a method named routeNotificationForSms or a property named mobile/contact_no ');
        }

        return $mobile;
    }

    /**
     * Get the SMS message from the notification.
     *
     * @throws BadMethodCallException
     */
    public function getMessage(mixed $notifiable, Notification $notification): string
    {
        if (method_exists($notification, 'toSms')) {
            return $notification->toSms($notifiable);
        }
        if (method_exists($notification, 'toSMS')) {
            return $notification->toSMS($notifiable);
        }
        throw new BadMethodCallException(get_class($notification).' class must contains a method named toSms or toSMS ');
    }
}
