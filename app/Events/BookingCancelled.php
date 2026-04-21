<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCancelled implements ShouldBroadcast 
{
     use SerializesModels; 
     
     public function __construct ( public Booking $booking ) {

     } 
     public function broadcastOn (): PrivateChannel     
     { 
        return new PrivateChannel(             
          BroadcastChannelNames::bookingCancelled(                 
               $this->booking->reference_number             
          )         
          );     
    } 
    public function broadcastAs (): string     {
         return 'booking.cancelled' ;     
    }
     public function broadcastWith (): array     {
         return [ 'reference_number' => $this->booking->reference_number, 'booking_status' => $this->booking->booking_status, 'payment_status' => $this->booking->payment_status,
         ];     
    }
}  

    

        

      

         






      

         


      

        
            
            

