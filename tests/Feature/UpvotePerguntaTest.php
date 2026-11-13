<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\Pergunta;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpvotePerguntaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_rejects_a_duplicate_vote_from_the_same_user(): void
    {
        $user = User::factory()->create();
        $pergunta = $this->pergunta();

        $pergunta->votos()->attach($user->id);

        $this->expectException(QueryException::class);
        $pergunta->votos()->attach($user->id);
    }

    public function test_models_expose_the_many_to_many_vote_relations(): void
    {
        $user = User::factory()->create();
        $pergunta = $this->pergunta();

        $this->assertInstanceOf(BelongsToMany::class, $pergunta->votos());
        $this->assertSame('pergunta_user', $pergunta->votos()->getTable());
        $this->assertInstanceOf(BelongsToMany::class, $user->perguntasVotadas());
        $this->assertSame('pergunta_user', $user->perguntasVotadas()->getTable());
    }

    public function test_guests_cannot_vote(): void
    {
        $pergunta = $this->pergunta();

        $this->post(route('perguntas.votar', $pergunta))
            ->assertRedirect(route('login.create'));

        $this->postJson(route('perguntas.votar', $pergunta))
            ->assertUnauthorized();

        $this->assertDatabaseCount('pergunta_user', 0);
    }

    public function test_voting_toggles_the_pivot_row(): void
    {
        $user = User::factory()->create();
        $pergunta = $this->pergunta();

        $this->actingAs($user)
            ->from(route('eventos.show', $pergunta->evento_id))
            ->post(route('perguntas.votar', $pergunta))
            ->assertRedirect(route('eventos.show', $pergunta->evento_id))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('pergunta_user', [
            'pergunta_id' => $pergunta->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('perguntas.votar', $pergunta))
            ->assertRedirect();

        $this->assertDatabaseMissing('pergunta_user', [
            'pergunta_id' => $pergunta->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_event_screen_ranks_questions_by_votes_descending(): void
    {
        $evento = Evento::create(['titulo' => 'Palestra']);
        $poucos = $this->pergunta($evento, 'Pergunta com poucos votos.');
        $muitos = $this->pergunta($evento, 'Pergunta com muitos votos.');
        $votante = User::factory()->create();

        $poucos->votos()->attach($votante->id);
        $muitos->votos()->attach([
            $votante->id,
            User::factory()->create()->id,
            User::factory()->create()->id,
        ]);

        $response = $this->withoutVite()
            ->actingAs($votante)
            ->get(route('eventos.show', $evento->id));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Pergunta com muitos votos.',
            'Pergunta com poucos votos.',
        ]);
        $response->assertSee('Remover voto', false);
        $response->assertSee('3 votos', false);
        $response->assertSee('1 voto', false);
    }

    private function pergunta(?Evento $evento = null, string $texto = 'Uma pergunta longa o bastante.'): Pergunta
    {
        $evento ??= Evento::create(['titulo' => 'Palestra']);

        return Pergunta::create([
            'evento_id' => $evento->id,
            'texto' => $texto,
            'status' => 'pendente',
        ]);
    }
}
