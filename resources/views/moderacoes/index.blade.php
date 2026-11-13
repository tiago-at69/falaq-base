@extends('layouts.app')

@section('title', 'Moderação — ' . $evento->titulo)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold m-0">Fila de moderação</h2>
    <a href="{{ route('eventos.show', $evento->id) }}" class="btn btn-outline-light btn-sm">Voltar ao evento</a>
</div>

@forelse($moderacoes as $moderacao)
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <p class="mb-1 text-white">{{ $moderacao->pergunta->texto }}</p>
            <p class="mb-2 text-secondary">{{ $moderacao->motivo }}</p>
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge bg-secondary">{{ $moderacao->status }}</span>
                @if($moderacao->status === 'analisando')
                    <div class="d-flex gap-2">
                        <form action="{{ route('moderacoes.status', $moderacao) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="aprovada">
                            <button type="submit" class="btn btn-sm btn-success">Aprovar</button>
                        </form>
                        <form action="{{ route('moderacoes.status', $moderacao) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="rejeitada">
                            <button type="submit" class="btn btn-sm btn-danger">Rejeitar</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="alert alert-dark text-center">Nenhuma denúncia neste evento.</div>
@endforelse

<div class="d-flex justify-content-center mt-4">
    {{ $moderacoes->links() }}
</div>
@endsection
