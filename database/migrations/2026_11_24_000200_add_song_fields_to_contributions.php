<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tipo "song": canción que les recuerda a ella, con enlace a YouTube, Spotify, etc. */
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->string('artist', 200)->nullable()->after('title');
            $table->string('link', 500)->nullable()->after('artist');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn(['artist', 'link']);
        });
    }
};
