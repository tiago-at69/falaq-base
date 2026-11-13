<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\Pergunta;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModeracaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderacao_belongs_to_pergunta_and_pergunta_has_many(): void
    {
        $pergunta = $this->pergunta();
        $moderacao = $pergunta->moderacoes()->create([
            'motivo' => 'Conteudo ofensivo fora do escopo.',
        ]);

        $this->assertInstanceOf(BelongsTo::class, $moderacao->pergunta());
        $this->assertTrue($moderacao->pergunta->is($pergunta));
        $this->assertInstanceOf(HasMany::class, $pergunta->moderacoes());
        $this->assertSame('analisando', $moderacao->fresh()->status);
    }

    public function test_short_report_is_rejected_with_422(): void
    {
        $user = User::factory()->create();
        $pergunta = $this->pergunta();

        $this->actingAs($user)
            ->postJson(route('perguntas.moderar', $pergunta), [
                'motivo' => 'curto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertDatabaseCount('moderacoes', 0);
    }

    public function test_authenticated_participant_reports_a_question(): void
    {
        $user = User::factory()->create();
        $pergunta = $this->pergunta();

        $this->actingAs($user)
            ->post(route('perguntas.moderar', $pergunta), [
                'motivo' => 'Pergunta ofensiva e fora do escopo.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('moderacoes', [
            'pergunta_id' => $pergunta->id,
            'motivo' => 'Pergunta ofensiva e fora do escopo.',
            'status' => 'analisando',
        ]);
    }

    public function test_guests_cannot_report_or_open_the_queue(): void
    {
        $evento = Evento::create(['titulo' => 'Palestra']);
        $pergunta = $this->pergunta($evento);

        $this->post(route('perguntas.moderar', $pergunta), [
            'motivo' => 'Pergunta ofensiva e fora do escopo.',
        ])->assertRedirect(route('login.create'));

        $this->get(route('eventos.moderacoes', $evento))
            ->assertRedirect(route('login.create'));
    }

    public function test_only_the_event_owner_sees_the_queue_without_n_plus_one(): void
    {
        $dono = User::factory()->create();
        $outro = User::factory()->create();
        $evento = Evento::create(['titulo' => 'Palestra', 'user_id' => $dono->id]);
        $pergunta = $this->pergunta($evento);

        foreach (range(1, 3) as $i) {
            $pergunta->moderacoes()->create([
                'motivo' => "Denuncia numero {$i} com texto.",
            ]);
        }

        $this->actingAs($outro)
            ->get(route('eventos.moderacoes', $evento))
            ->assertForbidden();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withoutVite()
            ->actingAs($dono)
            ->get(route('eventos.moderacoes', $evento));

        $response->assertOk();
        $response->assertSee('Denuncia numero 3 com texto.', false);
        $response->assertSee('Denuncia numero 1 com texto.', false);

        $perguntasQueries = collect(DB::getQueryLog())->pluck('query');

        $this->assertSame(1, $perguntasQueries->filter(
            fn (string $query) => str_contains($query, 'from "perguntas"') && str_contains($query, ' in (')
        )->count());
        $this->assertSame(0, $perguntasQueries->filter(
            fn (string $query) => str_contains($query, 'from "perguntas"') && str_contains($query, '"id" =')
        )->count());
    }

    public function test_only_the_event_owner_can_change_the_moderation_status(): void
    {
        $dono = User::factory()->create();
        $outro = User::factory()->create();
        $evento = Evento::create(['titulo' => 'Palestra', 'user_id' => $dono->id]);
        $moderacao = $this->pergunta($evento)->moderacoes()->create([
            'motivo' => 'Conteudo ofensivo fora do escopo.',
        ]);

        $this->actingAs($outro)
            ->patch(route('moderacoes.status', $moderacao), ['status' => 'rejeitada'])
            ->assertForbidden();

        $this->assertSame('analisando', $moderacao->fresh()->status);

        $this->actingAs($dono)
            ->patch(route('moderacoes.status', $moderacao), ['status' => 'rejeitada'])
            ->assertRedirect();

        $this->assertSame('rejeitada', $moderacao->fresh()->status);
    }

    private function pergunta(?Evento $evento = null): Pergunta
    {
        $evento ??= Evento::create(['titulo' => 'Palestra']);

        return Pergunta::create([
            'evento_id' => $evento->id,
            'texto' => 'Pergunta exibida no telao da palestra.',
            'status' => 'pendente',
        ]);
    }
}
