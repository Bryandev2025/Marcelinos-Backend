<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_us_id')->constrained('contact_us')->cascadeOnDelete();
            $table->enum('sender_type', ['client', 'admin']);
            $table->string('sender_name');
            $table->string('sender_email')->nullable();
            $table->text('body');
            $table->enum('sent_via', ['web', 'admin_panel', 'email_out'])->default('web');
            $table->timestamps();

            $table->index(['contact_us_id', 'created_at']);
        });

        Schema::table('contact_us', function (Blueprint $table): void {
            $table->string('conversation_token', 64)->nullable()->unique()->after('replied_at');
        });

        DB::table('contact_us')
            ->orderBy('id')
            ->select(['id', 'full_name', 'email', 'message', 'created_at', 'updated_at', 'conversation_token'])
            ->chunkById(200, function ($contacts): void {
                $messageRows = [];
                $tokenUpdates = [];

                foreach ($contacts as $contact) {
                    if (blank($contact->conversation_token)) {
                        $tokenUpdates[$contact->id] = Str::random(48);
                    }

                    if (blank($contact->message)) {
                        continue;
                    }

                    $messageRows[] = [
                        'contact_us_id' => $contact->id,
                        'sender_type' => 'client',
                        'sender_name' => (string) $contact->full_name,
                        'sender_email' => (string) $contact->email,
                        'body' => (string) $contact->message,
                        'sent_via' => 'web',
                        'created_at' => $contact->created_at ?? now(),
                        'updated_at' => $contact->updated_at ?? now(),
                    ];
                }

                if ($messageRows !== []) {
                    DB::table('contact_messages')->insert($messageRows);
                }

                foreach ($tokenUpdates as $contactId => $token) {
                    DB::table('contact_us')
                        ->where('id', $contactId)
                        ->update(['conversation_token' => $token]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('contact_us', function (Blueprint $table): void {
            $table->dropUnique(['conversation_token']);
            $table->dropColumn('conversation_token');
        });

        Schema::dropIfExists('contact_messages');
    }
};
