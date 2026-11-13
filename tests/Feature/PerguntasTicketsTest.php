<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\Pergunta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerguntasTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_an_empty_question_with_http_422(): void
    {
        $evento = Evento::create(['titulo' => 'Lancamento']);

        $this->postJson("/eventos/{$evento->id}/perguntas", [
            'texto' => '',
            'evento_id' => $evento->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['texto']);
    }

    public function test_rejects_a_one_letter_question_with_http_422(): void
    {
        $evento = Evento::create(['titulo' => 'Lancamento']);

        $this->postJson("/eventos/{$evento->id}/perguntas", [
            'texto' => 'a',
            'evento_id' => $evento->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['texto']);
    }

    public function test_rejects_an_unknown_event_with_http_422(): void
    {
        $this->postJson('/eventos/1/perguntas', [
            'texto' => 'Esta pergunta tem tamanho suficiente.',
            'evento_id' => 999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['evento_id']);
    }

    public function test_lists_only_the_current_event_questions_newest_first_ten_per_page(): void
    {
        $evento = Evento::create(['titulo' => 'Atual']);
        $outro = Evento::create(['titulo' => 'Outro']);

        foreach (range(1, 12) as $i) {
            $pergunta = Pergunta::create([
                'evento_id' => $evento->id,
                'texto' => "Pergunta atual numero {$i} com texto.",
                'status' => 'pendente',
            ]);
            $pergunta->created_at = now()->addMinutes($i);
            $pergunta->save();
        }

        Pergunta::create([
            'evento_id' => $outro->id,
            'texto' => 'Pergunta de outro evento que nao deve aparecer.',
            'status' => 'pendente',
        ]);

        $response = $this->withoutVite()->get("/eventos/{$evento->id}");

        $response->assertOk();
        $response->assertSee('Pergunta atual numero 12 com texto.', false);
        $response->assertSee('Pergunta atual numero 3 com texto.', false);
        $response->assertDontSee('Pergunta atual numero 2 com texto.', false);
        $response->assertDontSee('Pergunta de outro evento que nao deve aparecer.', false);
        $response->assertSee('page=2', false);
    }

    public function test_stores_a_question_that_passes_the_validation_rules(): void
    {
        $user = User::factory()->create();
        $evento = Evento::create([
            'titulo' => 'Lancamento',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)->post("/eventos/{$evento->id}/perguntas", [
            'texto' => 'Esta pergunta tem tamanho suficiente.',
            'evento_id' => $evento->id,
        ])->assertRedirect(route('eventos.show', $evento->id));

        $this->assertDatabaseHas('perguntas', [
            'evento_id' => $evento->id,
            'user_id' => $user->id,
            'texto' => 'Esta pergunta tem tamanho suficiente.',
            'status' => 'pendente',
        ]);
    }
}
