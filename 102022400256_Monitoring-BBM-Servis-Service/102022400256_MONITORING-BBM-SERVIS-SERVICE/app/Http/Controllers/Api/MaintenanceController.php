<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Maintenance;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;
use Throwable;

class MaintenanceController extends Controller
{
    #[OA\Get(
        path: "/api/v1/maintenance",
        summary: "Get all maintenance data",
        security: [["IAEApiKey" => []]],
        tags: ["Maintenance"],
        responses: [
            new OA\Response(response: 200, description: "Data retrieved successfully"),
            new OA\Response(response: 401, description: "Invalid API Key")
        ]
    )]
    public function index()
    {
        $data = Maintenance::all();

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $data
        ]);
    }

    #[OA\Get(
        path: "/api/v1/maintenance/{id}",
        summary: "Get maintenance by ID",
        security: [["IAEApiKey" => []]],
        tags: ["Maintenance"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "Maintenance ID",
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Data retrieved successfully"),
            new OA\Response(response: 404, description: "Data not found"),
            new OA\Response(response: 401, description: "Invalid API Key")
        ]
    )]
    public function show($id)
    {
        $data = Maintenance::find($id);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found',
                'errors' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $data
        ]);
    }

    #[OA\Post(
        path: "/api/v1/maintenance",
        summary: "Create maintenance data",
        security: [["IAEApiKey" => []]],
        tags: ["Maintenance"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["vehicle_id", "fuel_limit", "last_service_date"],
                properties: [
                    new OA\Property(property: "vehicle_id", type: "string", example: "K001"),
                    new OA\Property(property: "fuel_limit", type: "number", example: 500000),
                    new OA\Property(property: "last_service_date", type: "string", format: "date", example: "2026-06-08"),
                    new OA\Property(property: "operational_coupon", type: "string", example: "CPN001"),
                    new OA\Property(property: "notes", type: "string", example: "Servis rutin dan pengecekan BBM")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Maintenance data created successfully"),
            new OA\Response(response: 401, description: "Invalid API Key")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'nullable|integer',
            'vehicle_id' => 'required|string|max:255',
            'fuel_limit' => 'required|numeric|min:0',
            'last_service_date' => 'required|date',
            'operational_coupon' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $maintenance = Maintenance::create($validated);
        $integration = $this->sendCentralIntegration($maintenance, $request);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Maintenance data created successfully',
            'data' => $maintenance,
            'integration' => $integration,
            'meta' => [
                'service_name' => 'Monitoring-BBM-Servis-Service',
                'api_version' => 'v1',
            ],
        ], 201);
    }

    private function sendCentralIntegration(Maintenance $maintenance, Request $request): array
    {
        $central = config('services.iae_central');
        $tokenResult = $this->resolveCentralToken($central, $request);
        $centralApiKey = $request->header('X-SSO-API-KEY') ?: $central['api_key'];

        $integration = [
            'sso_status' => $tokenResult['status'],
            'soap_status' => null,
            'rabbitmq_status' => null,
            'errors' => $tokenResult['error'] ? ['sso' => $tokenResult['error']] : [],
        ];

        if (! $tokenResult['token']) {
            return $integration;
        }

        $baseUrl = rtrim($central['base_url'], '/');
        $auditPayload = [
            'maintenance_id' => $maintenance->id,
            'schedule_id' => $maintenance->schedule_id,
            'vehicle_id' => $maintenance->vehicle_id,
            'fuel_limit' => $maintenance->fuel_limit,
            'last_service_date' => $maintenance->last_service_date,
            'operational_coupon' => $maintenance->operational_coupon,
        ];

        $xmlBody = $this->buildSoapAuditEnvelope(
            $central['team_id'],
            'MaintenanceCreated',
            json_encode($auditPayload, JSON_UNESCAPED_SLASHES)
        );

        try {
            $soapResponse = Http::timeout(15)
                ->withToken($tokenResult['token'])
                ->withHeaders(array_filter([
                    'Content-Type' => 'text/xml',
                    'X-IAE-KEY' => $centralApiKey,
                ]))
                ->send('POST', "{$baseUrl}{$central['soap_audit_path']}", ['body' => $xmlBody]);

            $integration['soap_status'] = $soapResponse->status();

            if ($soapResponse->failed()) {
                $integration['errors']['soap'] = $soapResponse->body();
                return $integration;
            }
        } catch (Throwable $exception) {
            $integration['soap_status'] = 'failed';
            $integration['errors']['soap'] = $exception->getMessage();
            return $integration;
        }

        try {
            $rabbitResponse = Http::timeout(15)
                ->withToken($tokenResult['token'])
                ->withHeaders(array_filter([
                    'X-IAE-KEY' => $centralApiKey,
                ]))
                ->acceptJson()
                ->post("{$baseUrl}{$central['rabbitmq_publish_path']}", [
                    'exchange' => $central['exchange'],
                    'routing_key' => 'maintenance.created',
                    'payload' => [
                        'event_name' => 'maintenance.created',
                        'service_name' => 'Monitoring BBM Servis',
                        'team' => $central['team_id'],
                        ...$auditPayload,
                    ],
                ]);

            $integration['rabbitmq_status'] = $rabbitResponse->status();

            if ($rabbitResponse->failed()) {
                $integration['errors']['rabbitmq'] = $rabbitResponse->body();
            }
        } catch (Throwable $exception) {
            $integration['rabbitmq_status'] = 'failed';
            $integration['errors']['rabbitmq'] = $exception->getMessage();
        }

        return $integration;
    }

    private function resolveCentralToken(array $central, Request $request): array
    {
        if ($request->header('X-M2M-TOKEN')) {
            return [
                'status' => 'request_m2m_token',
                'token' => $request->header('X-M2M-TOKEN'),
                'error' => null,
            ];
        }

        if (! empty($central['m2m_token'])) {
            return [
                'status' => 'configured_m2m_token',
                'token' => $central['m2m_token'],
                'error' => null,
            ];
        }

        if ($request->bearerToken()) {
            return [
                'status' => 'user_token',
                'token' => $request->bearerToken(),
                'error' => null,
            ];
        }

        return $this->fetchM2mToken($central);
    }

    private function fetchM2mToken(array $central): array
    {
        if (! empty($central['m2m_token'])) {
            return [
                'status' => 'static_token',
                'token' => $central['m2m_token'],
                'error' => null,
            ];
        }

        if (empty($central['api_key']) && empty($central['client_id'])) {
            return [
                'status' => 'skipped',
                'token' => null,
                'error' => 'IAE central credential is not configured',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders(array_filter([
                    'X-IAE-KEY' => $central['api_key'],
                ]))
                ->post(rtrim($central['base_url'], '/').$central['token_path'], array_filter([
                    'grant_type' => 'client_credentials',
                    'client_id' => $central['client_id'],
                    'client_secret' => $central['client_secret'],
                ]));

            $token = $response->json('access_token')
                ?? $response->json('token')
                ?? $response->json('data.access_token')
                ?? $response->json('data.token');

            return [
                'status' => $response->status(),
                'token' => $response->successful() ? $token : null,
                'error' => $response->successful() ? null : $response->body(),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'token' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function buildSoapAuditEnvelope(string $teamId, string $activityName, string $logContent): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
    <soap:Body>
        <iae:AuditRequest>
            <iae:TeamID>{$teamId}</iae:TeamID>
            <iae:ActivityName>{$activityName}</iae:ActivityName>
            <iae:LogContent><![CDATA[{$logContent}]]></iae:LogContent>
        </iae:AuditRequest>
    </soap:Body>
</soap:Envelope>
XML;
    }
}
