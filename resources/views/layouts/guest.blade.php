<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $rawTitle = $title ?? null;
        $safeTitle = is_scalar($rawTitle) && trim((string) $rawTitle) !== '' ? (string) $rawTitle : 'Acesso';
        $rawStatus = session('status');
        $safeStatus = is_array($rawStatus)
            ? collect($rawStatus)->flatten()->filter(fn ($value) => is_scalar($value))->map(fn ($value) => (string) $value)->implode(' ')
            : (is_scalar($rawStatus) ? (string) $rawStatus : '');
    @endphp
    <title>{{ $safeTitle }} - OurCloud Louvor</title>
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css','resources/js/app.js'])
    <style>
        /* Camada crítica independente do bundle: a tela de acesso deve continuar legível mesmo durante atualização do frontend. */
        html,body{min-height:100%;margin:0}
        body.ourcloud-auth{background:linear-gradient(145deg,#0f172a 0%,#1e1b4b 54%,#164e63 100%);color:#0f172a;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .ourcloud-auth-shell{width:100%;max-width:440px;margin:auto;padding:32px 16px}
        .ourcloud-auth-brand{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:22px;color:#fff;text-align:left}
        .ourcloud-auth-logo{width:56px;height:56px;flex:0 0 56px;filter:drop-shadow(0 12px 20px rgba(15,23,42,.30))}
        .ourcloud-auth-name{font-size:26px;line-height:1.05;font-weight:900;letter-spacing:-.03em}
        .ourcloud-auth-subtitle{margin-top:5px;color:#cbd5e1;font-size:13px}
        .ourcloud-auth-card{border:1px solid rgba(255,255,255,.16);border-radius:20px;background:#fff;padding:24px;box-shadow:0 24px 60px rgba(15,23,42,.34)}
        @media(max-width:520px){.ourcloud-auth-shell{padding:24px 14px}.ourcloud-auth-logo{width:50px;height:50px;flex-basis:50px}.ourcloud-auth-name{font-size:23px}.ourcloud-auth-card{padding:20px}}
    </style>
</head>
<body class="ourcloud-auth flex min-h-screen items-center justify-center px-4 py-10">
<div class="ourcloud-auth-shell w-full max-w-md">
    <div class="ourcloud-auth-brand">
        <img class="ourcloud-auth-logo" src="/brand/ourcloud-louvor.svg" alt="OurCloud Louvor">
        <div>
            <div class="ourcloud-auth-name">OurCloud Louvor</div>
            <div class="ourcloud-auth-subtitle">Sistema de Gestão do Ministério de Louvor</div>
        </div>
    </div>
    @if($safeStatus !== '')<div class="mb-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ $safeStatus }}</div>@endif
    @if($errors->any())<div class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    <div class="ourcloud-auth-card rounded-2xl bg-white p-6 shadow-2xl">@yield('content')</div>
</div>
</body>
</html>
