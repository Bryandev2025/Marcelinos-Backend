<?php

namespace App\Jobs;

use App\Models\ContactUs;
use App\Models\User;
use App\Notifications\NewContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendContactNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ContactUs $contact
    ) {}

    public function handle(): void
    {
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new NewContactInquiry($this->contact));
    }
}
