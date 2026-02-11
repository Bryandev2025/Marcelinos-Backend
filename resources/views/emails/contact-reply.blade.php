<x-mail::message>
# Reply to Your Inquiry

Hello **{{ $contact->full_name }}**,

Thank you for contacting us regarding: **{{ $contact->subject }}**

## Your Original Message:
{{ $contact->message }}

## Our Response:

{{ $replyMessage }}

@if($contact->phone)
If you need to discuss this further, you can reach us at our contact number.
@endif

Best regards,  
**Marcelinos Team**

---
*This is an automated response to your inquiry submitted on {{ $contact->created_at->format('M j, Y \a\t g:i A') }}*
</x-mail::message>