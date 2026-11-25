<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuración del evento (una sola fila).
        Schema::create('event_settings', function (Blueprint $table) {
            $table->id();
            $table->string('celebrant_name');
            $table->string('celebrant_short_name');
            $table->unsignedTinyInteger('age')->nullable();
            $table->date('birthday_date')->nullable();
            $table->string('cover_title')->nullable();
            $table->text('cover_message')->nullable();
            $table->text('invite_title')->nullable();
            $table->text('invite_message')->nullable();
            $table->ulid('cover_media_id')->nullable();
            $table->boolean('memories_enabled')->default(false);
            $table->boolean('submissions_open')->default(true);
            $table->dateTime('submissions_close_at')->nullable();
            $table->string('birthday_password')->nullable();
            $table->boolean('music_enabled')->default(false);
            $table->ulid('music_media_id')->nullable();
            $table->timestamps();
        });

        // Lista manual de invitados (para saber quién falta por participar).
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('relationship', 40)->nullable();
            $table->string('contact')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // Tokens de acceso: invitación general, invitación personal, cumpleañera, participante.
        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // invite | guest_invite | birthday | participant
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted')->nullable(); // para que el admin pueda volver a copiar el enlace
            $table->string('label')->nullable();
            $table->foreignId('guest_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamps();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('relationship', 30); // family | friend | partner | work | school | other
            $table->string('relationship_other', 60)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('access_token_id')->nullable()->constrained('access_tokens')->nullOnDelete();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        Schema::create('contributions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index(); // letter | video | photo | message | secret | questions
            $table->string('title', 200)->nullable();
            $table->longText('body')->nullable();
            $table->string('signature', 120)->nullable();
            $table->string('prompt', 255)->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        // Archivos multimedia: independientes de las aportaciones.
        Schema::create('media_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('contribution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // image | video | audio
            $table->string('purpose', 20)->default('contribution'); // contribution | cover | music
            $table->string('disk', 30);
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Subidas por partes (para videos grandes desde el teléfono).
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('purpose', 20)->default('contribution');
            $table->string('original_name');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('received_chunks')->default(0);
            $table->string('status', 15)->default('pending'); // pending | complete | failed
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('text');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('contribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_text');
            $table->text('answer');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['answers', 'questions', 'upload_sessions', 'media_files', 'contributions', 'participants', 'access_tokens', 'guests', 'event_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
