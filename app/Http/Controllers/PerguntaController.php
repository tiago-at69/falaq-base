<?php

namespace App\Http\Controllers;

use App\Models\Pergunta;
use Illuminate\Http\RedirectResponse;

class PerguntaController extends Controller
{
    public function votar(Pergunta $pergunta): RedirectResponse
    {
        $pergunta->votos()->toggle(auth()->id());

        return back()->with('status', 'Voto atualizado com sucesso!');
    }
}
