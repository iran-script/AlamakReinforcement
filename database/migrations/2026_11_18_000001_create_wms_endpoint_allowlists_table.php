<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wms_endpoint_allowlists', function (Blueprint $table): void {
            $table->id();
            $table->string('scheme', 5);
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            $table->string('path_prefix', 1000);
            $table->json('resolved_ips')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['scheme', 'host', 'port', 'path_prefix']);
            $table->index(['status', 'host', 'port']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_endpoint_allowlists');
    }
};
