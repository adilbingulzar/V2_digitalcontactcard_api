<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the legacy `tbl_shift_hours` and `tbl_shift_hours_spot` tables.
     * One row holds the time ranges of one weekday.
     */
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 20)->comment('vcard = opening hours on a vCard, shift = employee shift at a business, spot = spot shift');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('spot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('day', 3)->comment('Mon, Tue, Wed, Thu, Fri, Sat or Sun');
            $table->json('time_ranges')->comment('[{"from":"09:00","to":"17:00"}, ...]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['purpose', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
