<?php

namespace App\Http\Middleware;

use App\Services\MinisterAssistanceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyMinisterAssistance
{
    /**
     * Faz a assistência funcionar como uma sessão operacional real do usuário assistido,
     * sem trocar a autenticação persistida. O ator real continua na sessão e na auditoria;
     * apenas a identidade efetiva desta requisição é substituída.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        if (! $actor || ! $request->hasSession()) {
            return $next($request);
        }

        // Estas operações sempre pertencem à conta real que iniciou a assistência.
        if ($request->routeIs('admin.assistance.start', 'admin.assistance.stop', 'logout')) {
            return $next($request);
        }

        $context = app(MinisterAssistanceContext::class);
        $assistedPerson = $context->assistedPersonForActor($actor);
        $effectiveUser = $assistedPerson?->user;

        if (! $assistedPerson || ! $effectiveUser || ! $effectiveUser->active) {
            return $next($request);
        }

        // Credenciais, tokens de calendário e integrações pessoais nunca são manipulados
        // em nome de outra pessoa, ainda que a assistência operacional esteja ativa.
        if ($request->routeIs(
            'account.*',
            'calendar.sync',
            'calendar.feed.regenerate',
            'my.agenda.google.*'
        )) {
            abort(409, 'Encerre a assistência ao usuário antes de acessar conta, credenciais ou sincronizações pessoais.');
        }

        $request->attributes->set(MinisterAssistanceContext::ACTOR_ATTRIBUTE, $actor);
        $request->attributes->set(MinisterAssistanceContext::PERSON_ATTRIBUTE, $assistedPerson);
        $request->attributes->set(MinisterAssistanceContext::EFFECTIVE_USER_ATTRIBUTE, $effectiveUser);

        Auth::setUser($effectiveUser);
        $request->setUserResolver(static fn () => $effectiveUser);

        try {
            return $next($request);
        } finally {
            // Evita que listeners termináveis/processos longos confundam o ator real.
            Auth::setUser($actor);
            $request->setUserResolver(static fn () => $actor);
        }
    }
}
