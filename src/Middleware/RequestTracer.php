<?php

namespace Ar4min\ErpAgent\Middleware;

use Ar4min\ErpAgent\Services\RequestTracerService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestTracer
{
    protected RequestTracerService $tracer;
    protected string $requestId;
    protected float $startTime;
    protected int $startMemory;
    protected array $queries = [];
    protected array $models = [];
    protected array $cacheEvents = [];
    protected array $jobs = [];
    protected array $mailEvents = [];

    public function __construct(RequestTracerService $tracer)
    {
        $this->tracer = $tracer;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('erp-agent.tracing.enabled', true)) {
            return $next($request);
        }

        // Skip excluded paths
        $excludedPaths = config('erp-agent.tracing.exclude_paths', []);
        foreach ($excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $this->requestId = (string) Str::uuid();
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();

        $request->headers->set('X-Request-Id', $this->requestId);

        $this->registerListeners();
        DB::enableQueryLog();

        $response = $next($request);

        try {
            $this->collectAndQueue($request, $response);
        } catch (\Throwable $e) {
            Log::debug('[ERP Agent] RequestTracer error', ['error' => $e->getMessage()]);
        }

        DB::flushQueryLog();

        return $response;
    }

    protected function registerListeners(): void
    {
        // DB queries
        DB::listen(function ($query) {
            $this->queries[] = [
                'sql' => $query->sql,
                'bindings' => $this->sanitizeBindings($query->bindings),
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
            ];
        });

        // Model events
        foreach (['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'] as $event) {
            app('events')->listen($event, function ($eventName, $data) {
                $model = $data[0] ?? null;
                if ($model) {
                    $action = Str::before(Str::after($eventName, 'eloquent.'), ':');
                    $this->models[] = [
                        'action' => $action,
                        'model' => class_basename($model),
                        'id' => $model->getKey(),
                    ];
                }
            });
        }

        // Cache events
        app('events')->listen('Illuminate\Cache\Events\*', function ($eventName, $data) {
            $event = $data[0] ?? null;
            if ($event) {
                $this->cacheEvents[] = [
                    'type' => class_basename($eventName),
                    'key' => $event->key ?? null,
                ];
            }
        });

        // Job dispatched
        app('events')->listen('Illuminate\Queue\Events\JobQueued', function ($event) {
            $this->jobs[] = [
                'job' => class_basename($event->job),
                'queue' => $event->queue ?? 'default',
            ];
        });

        // Mail
        app('events')->listen('Illuminate\Mail\Events\MessageSending', function ($event) {
            $this->mailEvents[] = [
                'subject' => $event->message->getSubject(),
            ];
        });
    }

    protected function collectAndQueue(Request $request, Response $response): void
    {
        $duration = (microtime(true) - $this->startTime) * 1000;
        $memoryPeak = memory_get_peak_usage();

        $totalQueryTime = array_sum(array_column($this->queries, 'time_ms'));
        $slowQueries = count(array_filter($this->queries, fn($q) => $q['time_ms'] > 100));
        $duplicateQueries = $this->detectDuplicateQueries();

        $cacheHits = count(array_filter($this->cacheEvents, fn($e) => $e['type'] === 'CacheHit'));
        $cacheMisses = count(array_filter($this->cacheEvents, fn($e) => $e['type'] === 'CacheMissed'));

        $trace = [
            'request_id' => $this->requestId,
            '@timestamp' => Carbon::now()->toIso8601String(),
            'type' => 'instance_trace',

            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'route' => $request->route()?->getName() ?? $request->route()?->uri() ?? 'unknown',
                'ip' => $request->ip(),
                'user_agent' => Str::limit($request->userAgent() ?? '', 200),
                'ajax' => $request->ajax(),
            ],

            'response' => [
                'status' => $response->getStatusCode(),
                'size_bytes' => strlen($response->getContent() ?: ''),
            ],

            'user' => Auth::check() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name ?? null,
            ] : null,

            'performance' => [
                'duration_ms' => round($duration, 2),
                'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            ],

            'database' => [
                'query_count' => count($this->queries),
                'total_time_ms' => round($totalQueryTime, 2),
                'slow_query_count' => $slowQueries,
                'duplicate_query_count' => $duplicateQueries,
                'queries' => array_slice($this->queries, 0, 30),
            ],

            'models' => [
                'count' => count($this->models),
                'actions' => array_slice($this->models, 0, 50),
            ],

            'cache' => [
                'hits' => $cacheHits,
                'misses' => $cacheMisses,
            ],

            'jobs' => [
                'dispatched' => count($this->jobs),
                'list' => $this->jobs,
            ],

            'mail' => [
                'sent' => count($this->mailEvents),
            ],
        ];

        $this->tracer->queue($trace);
    }

    protected function sanitizeBindings(array $bindings): array
    {
        return array_map(function ($binding) {
            if ($binding instanceof \DateTimeInterface) {
                return $binding->format('Y-m-d H:i:s');
            }
            if (is_string($binding) && strlen($binding) > 200) {
                return Str::limit($binding, 200);
            }
            return $binding;
        }, $bindings);
    }

    protected function detectDuplicateQueries(): int
    {
        $normalized = [];
        foreach ($this->queries as $query) {
            $key = preg_replace('/\b\d+\b/', '?', $query['sql']);
            $key = preg_replace("/'.+?'/", '?', $key);
            $normalized[$key] = ($normalized[$key] ?? 0) + 1;
        }

        $duplicates = 0;
        foreach ($normalized as $count) {
            if ($count > 1) {
                $duplicates += $count;
            }
        }
        return $duplicates;
    }
}
