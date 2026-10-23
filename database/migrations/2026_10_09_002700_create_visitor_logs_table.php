<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Visits to public tip and review pages, shown in the admin visitor report.
     */
    public function up(): void
    {
        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->id();
            $table->string('page_type', 30)->comment('review, paytip, review_spot or paytip_spot');
            $table->text('page_url');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('completed')->default(false)->comment('Legacy activity: the visitor submitted the form');
            $table->timestamps();

            $table->index(['page_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};
