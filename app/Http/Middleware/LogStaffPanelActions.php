<?php

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogStaffPanelActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = auth()->user();
        $role = strtolower(trim((string) ($user?->role ?? '')));

        if (! $user || ! in_array($role, ['admin', 'staff'], true)) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        if (! $this->isPanelRequest($request)) {
            return $response;
        }

        if ($request->isMethod('GET')) {
            return $response;
        }

        $event = $this->resolveEvent($request);

        ActivityLogger::log(
            category: 'panel_action',
            event: $event,
            description: $this->buildDescription($request, $event),
            meta: [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'panel' => $this->detectPanel($request),
                'action' => $this->resolveLivewireAction($request),
            ],
            userId: (int) $user->id,
        );

        return $response;
    }

    private function isPanelRequest(Request $request): bool
    {
        return $request->is('admin') ||
            $request->is('admin/*') ||
            $request->is('staff') ||
            $request->is('staff/*') ||
            $request->is('livewire/update');
    }

    private function resolveEvent(Request $request): string
    {
        if ($request->is('livewire/update')) {
            return 'panel_action.livewire_called';
        }

        return 'panel_action.request_sent';
    }

    private function buildDescription(Request $request, string $event): string
    {
        $path = '/'.$request->path();
        $method = strtoupper($request->method());
        $panel = $this->detectPanel($request);

        if ($event === 'panel_action.livewire_called') {
            $action = $this->resolveLivewireAction($request);

            return sprintf('triggered Livewire action "%s" in %s panel.', $action, $panel);
        }

        return sprintf('sent %s request to %s in %s panel.', $method, $path, $panel);
    }

    private function detectPanel(Request $request): string
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return 'admin';
        }

        if ($request->is('staff') || $request->is('staff/*')) {
            return 'staff';
        }

        $referer = strtolower((string) $request->headers->get('referer', ''));

        if (str_contains($referer, '/admin')) {
            return 'admin';
        }

        if (str_contains($referer, '/staff')) {
            return 'staff';
        }

        return 'unknown';
    }

    private function resolveLivewireAction(Request $request): string
    {
        $calls = $request->input('components.0.calls');

        if (! is_array($calls) || empty($calls)) {
            return 'unknown';
        }

        $methods = [];

        foreach ($calls as $call) {
            $method = trim((string) data_get($call, 'method', ''));

            if ($method !== '') {
                $methods[] = $method;
            }
        }

        if (empty($methods)) {
            return 'unknown';
        }

        return implode(', ', array_unique($methods));
    }
}
