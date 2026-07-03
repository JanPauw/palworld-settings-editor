<?php

namespace JanPauw\PalworldSettingsEditor\Services;

use Illuminate\Support\Facades\Http;

class PelicanServerStateService
{
    /** @var array<string, array{payload: array<string, mixed>|null, endpoint: ?string, error: ?string}> */
    private array $daemonStateCache = [];

    private const SAFE_STATES = [
        'offline',
        'stopped',
        'stop',
        'off',
    ];

    private const UNSAFE_STATES = [
        'running',
        'starting',
        'stopping',
        'installing',
        'suspended',
        'unknown',
    ];

    public function isSafeToEdit(mixed $server): bool
    {
        return in_array($this->getNormalizedState($server), self::SAFE_STATES, true);
    }

    public function getStateLabel(mixed $server): string
    {
        $state = $this->getNormalizedState($server);

        return $state === 'unknown' ? 'Unknown' : ucfirst($state);
    }

    public function getStatusMessage(mixed $server): string
    {
        if ($this->isSafeToEdit($server)) {
            return 'This server appears to be stopped/offline, so settings can be edited safely.';
        }

        $state = $this->getNormalizedState($server);

        if (in_array($state, self::UNSAFE_STATES, true)) {
            return 'Editing is disabled because the server is not stopped. Stop the server before changing Palworld settings, otherwise Palworld or the egg may overwrite changes.';
        }

        return 'Editing is disabled because the current server state could not be confirmed.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getStateDiagnostics(mixed $server): array
    {
        $daemonState = $this->getDaemonStateProbe($server);

        return [
            'status' => data_get($server, 'status'),
            'state' => data_get($server, 'state'),
            'power_state' => data_get($server, 'power_state'),
            'powerState' => data_get($server, 'powerState'),
            'current_state' => data_get($server, 'current_state'),
            'currentState' => data_get($server, 'currentState'),
            'server_state' => data_get($server, 'server_state'),
            'serverState' => data_get($server, 'serverState'),
            'attributes.status' => data_get($server, 'attributes.status'),
            'attributes.state' => data_get($server, 'attributes.state'),
            'attributes.current_state' => data_get($server, 'attributes.current_state'),
            'attributes.currentState' => data_get($server, 'attributes.currentState'),
            'attributes.server_state' => data_get($server, 'attributes.server_state'),
            'attributes.serverState' => data_get($server, 'attributes.serverState'),
            'suspended' => data_get($server, 'suspended'),
            'attributes.suspended' => data_get($server, 'attributes.suspended'),
            'server.uuid' => data_get($server, 'uuid'),
            'server.node.id' => data_get($server, 'node.id') ?? data_get($server, 'node_id'),
            'daemon.endpoint' => $daemonState['endpoint'],
            'daemon.error' => $daemonState['error'],
            'daemon.current_state' => data_get($daemonState['payload'], 'current_state'),
            'daemon.state' => data_get($daemonState['payload'], 'state'),
            'daemon.status' => data_get($daemonState['payload'], 'status'),
            'daemon.attributes.current_state' => data_get($daemonState['payload'], 'attributes.current_state'),
            'daemon.attributes.state' => data_get($daemonState['payload'], 'attributes.state'),
            'daemon.attributes.status' => data_get($daemonState['payload'], 'attributes.status'),
            'daemon.meta.state' => data_get($daemonState['payload'], 'meta.state'),
            'daemon.data.attributes.current_state' => data_get($daemonState['payload'], 'data.attributes.current_state'),
            'daemon.data.attributes.state' => data_get($daemonState['payload'], 'data.attributes.state'),
            'daemon.data.attributes.status' => data_get($daemonState['payload'], 'data.attributes.status'),
        ];
    }

    private function getNormalizedState(mixed $server): string
    {
        $daemonState = $this->getDaemonStateProbe($server);

        $candidates = [
            data_get($server, 'status'),
            data_get($server, 'state'),
            data_get($server, 'power_state'),
            data_get($server, 'powerState'),
            data_get($server, 'current_state'),
            data_get($server, 'currentState'),
            data_get($server, 'attributes.status'),
            data_get($server, 'attributes.state'),
            data_get($server, 'attributes.current_state'),
            data_get($server, 'attributes.currentState'),
            data_get($server, 'server_state'),
            data_get($server, 'serverState'),
            data_get($server, 'attributes.server_state'),
            data_get($server, 'attributes.serverState'),
            data_get($server, 'status.value'),
            data_get($server, 'attributes.status.value'),
            data_get($daemonState['payload'], 'current_state'),
            data_get($daemonState['payload'], 'state'),
            data_get($daemonState['payload'], 'status'),
            data_get($daemonState['payload'], 'attributes.current_state'),
            data_get($daemonState['payload'], 'attributes.state'),
            data_get($daemonState['payload'], 'attributes.status'),
            data_get($daemonState['payload'], 'meta.state'),
            data_get($daemonState['payload'], 'data.attributes.current_state'),
            data_get($daemonState['payload'], 'data.attributes.state'),
            data_get($daemonState['payload'], 'data.attributes.status'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $normalized = strtolower(trim($candidate));
            if ($normalized !== '') {
                return $normalized;
            }
        }

        if ((bool) data_get($server, 'suspended', false) || (bool) data_get($server, 'attributes.suspended', false)) {
            return 'suspended';
        }

        return 'unknown';
    }

    /**
     * @return array{payload: array<string, mixed>|null, endpoint: ?string, error: ?string}
     */
    private function getDaemonStateProbe(mixed $server): array
    {
        $uuid = data_get($server, 'uuid');
        $node = data_get($server, 'node');

        if (! is_string($uuid) || $uuid === '' || $node === null) {
            return [
                'payload' => null,
                'endpoint' => null,
                'error' => 'Missing server uuid or node relation.',
            ];
        }

        if (array_key_exists($uuid, $this->daemonStateCache)) {
            return $this->daemonStateCache[$uuid];
        }

        $endpoints = [
            "/api/servers/{$uuid}/resources",
            "/api/servers/{$uuid}",
        ];

        $lastError = null;

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::daemon($node)
                    ->get($endpoint)
                    ->throw()
                    ->json();

                return $this->daemonStateCache[$uuid] = [
                    'payload' => is_array($response) ? $response : null,
                    'endpoint' => $endpoint,
                    'error' => null,
                ];
            } catch (\Throwable $throwable) {
                $lastError = $throwable->getMessage();
            }
        }

        return $this->daemonStateCache[$uuid] = [
            'payload' => null,
            'endpoint' => end($endpoints) ?: null,
            'error' => $lastError,
        ];
    }
}
