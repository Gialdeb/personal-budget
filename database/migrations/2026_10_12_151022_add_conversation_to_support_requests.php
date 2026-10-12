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
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->string('app_version', 80)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_admin_reply_at')->nullable();
            $table->timestamp('last_user_message_at')->nullable();
            $table->index(['user_id', 'status']);
        });
        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('support_request_id')->constrained()->cascadeOnDelete();
            $table->string('author_type', 12);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->timestamp('admin_notified_at')->nullable();
            $table->timestamp('push_sent_at')->nullable();
            $table->index(['support_request_id', 'created_at']);
        });
        DB::table('support_requests')->orderBy('id')->chunkById(200, function ($tickets): void {
            foreach ($tickets as $ticket) {
                DB::table('support_ticket_messages')->insert([
                    'uuid' => (string) Str::uuid(), 'support_request_id' => $ticket->id,
                    'author_type' => 'user', 'user_id' => $ticket->user_id, 'body' => $ticket->message,
                    'created_at' => $ticket->created_at, 'updated_at' => $ticket->created_at,
                    'admin_notified_at' => $ticket->created_at,
                ]);
                DB::table('support_requests')->where('id', $ticket->id)->update([
                    'status' => $ticket->status === 'closed' ? 'closed' : 'waiting_admin',
                    'last_user_message_at' => $ticket->created_at,
                    'closed_at' => $ticket->status === 'closed' ? $ticket->updated_at : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        DB::table('support_requests')->whereIn('status', ['waiting_admin', 'waiting_user'])->update(['status' => 'new']);
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['app_version', 'closed_at', 'last_admin_reply_at', 'last_user_message_at']);
        });
    }
};
