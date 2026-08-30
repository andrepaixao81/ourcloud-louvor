@extends('layouts.app')
@section('content')
<div class="mb-6"><a href="{{ route('services.show',$service) }}" class="text-sm font-semibold text-indigo-600">← {{ $service->name }}</a><h1 class="mt-2 text-2xl font-black">Repertório</h1><p class="text-sm text-slate-500">Planejamento musical do culto. O histórico real será registrado no histórico do culto.</p></div>
<livewire:repertoire-editor :service="$service" />
@endsection
