<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::all();

        return response()->json([
            'status' => 'success',
            'message' => 'Schedules retrieved successfully',
            'data' => $schedules,
            'meta' => [
                'service_name' => 'Schedule-Service',
                'api_version' => 'v1'
            ]
        ], 200);
    }

    public function show($id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found',
                'errors' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $schedule,
            'meta' => [
                'service_name' => 'Schedule-Service',
                'api_version' => 'v1'
            ]
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|integer',
            'driver_id' => 'required|integer',
            'destination' => 'required|string|max:255',
            'departure_date' => 'required|date',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'purpose' => 'required|string|max:255',
            'status' => 'nullable|string|max:255',
            'fuel_limit' => 'nullable|numeric|min:0',
            'last_service_date' => 'nullable|date',
            'operational_coupon' => 'nullable|string|max:255',
            'operational_notes' => 'nullable|string',
        ]);

        $dispatchConfig = config('services.dispatch');
        $vehicleBaseUrl = rtrim($dispatchConfig['vehicle_url'], '/');
        $maintenanceBaseUrl = rtrim($dispatchConfig['maintenance_url'], '/');

        try {
            $vehicleResponse = Http::withHeaders([
                'X-IAE-KEY' => $dispatchConfig['vehicle_key'],
            ])->get("{$vehicleBaseUrl}/api/v1/vehicles/{$validated['vehicle_id']}");
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vehicle service is unavailable',
                'errors' => $exception->getMessage(),
            ], 503);
        }

        if ($vehicleResponse->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vehicle could not be verified',
                'errors' => $vehicleResponse->json(),
            ], $vehicleResponse->status());
        }

        $vehicle = $vehicleResponse->json('data');

        if (($vehicle['status'] ?? null) !== 'Available') {
            return response()->json([
                'status' => 'error',
                'message' => 'Vehicle is not available for dispatch',
                'errors' => [
                    'vehicle_id' => $validated['vehicle_id'],
                    'current_status' => $vehicle['status'] ?? null,
                ],
            ], 409);
        }

        $schedulePayload = collect($validated)->only([
            'vehicle_id',
            'driver_id',
            'destination',
            'departure_date',
            'return_date',
            'purpose',
            'status',
        ])->toArray();

        $schedulePayload['status'] = $schedulePayload['status'] ?? 'Scheduled';
        $schedule = Schedule::create($schedulePayload);

        try {
            $lockResponse = Http::withHeaders([
                'X-IAE-KEY' => $dispatchConfig['vehicle_key'],
            ])->patch("{$vehicleBaseUrl}/api/internal/vehicles/{$schedule->vehicle_id}/status", [
                'status' => 'In-Use',
                'notes' => "Locked by dispatch schedule #{$schedule->id}",
            ]);
        } catch (Throwable $exception) {
            $schedule->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Vehicle status could not be locked',
                'errors' => $exception->getMessage(),
            ], 503);
        }

        if ($lockResponse->failed()) {
            $schedule->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Vehicle status could not be locked',
                'errors' => $lockResponse->json(),
            ], $lockResponse->status());
        }

        $maintenancePayload = [
            'schedule_id' => $schedule->id,
            'vehicle_id' => (string) $schedule->vehicle_id,
            'fuel_limit' => $validated['fuel_limit'] ?? 0,
            'last_service_date' => $validated['last_service_date'] ?? ($vehicle['last_service_date'] ?? now()->toDateString()),
            'operational_coupon' => $validated['operational_coupon'] ?? null,
            'notes' => $validated['operational_notes'] ?? "Initial operational allocation for dispatch schedule #{$schedule->id}",
        ];

        try {
            $maintenanceHeaders = [
                'X-IAE-KEY' => $dispatchConfig['maintenance_key'],
            ];

            if ($request->bearerToken()) {
                $maintenanceHeaders['Authorization'] = 'Bearer '.$request->bearerToken();
            }

            if ($request->header('X-SSO-API-KEY')) {
                $maintenanceHeaders['X-SSO-API-KEY'] = $request->header('X-SSO-API-KEY');
            }

            if ($request->header('X-M2M-TOKEN')) {
                $maintenanceHeaders['X-M2M-TOKEN'] = $request->header('X-M2M-TOKEN');
            }

            $maintenanceResponse = Http::withHeaders($maintenanceHeaders)
                ->post("{$maintenanceBaseUrl}/api/v1/maintenance", $maintenancePayload);
        } catch (Throwable $exception) {
            $this->releaseVehicle($vehicleBaseUrl, $dispatchConfig['vehicle_key'], $schedule->vehicle_id);
            $schedule->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Maintenance allocation service is unavailable',
                'errors' => $exception->getMessage(),
            ], 503);
        }

        if ($maintenanceResponse->failed()) {
            $this->releaseVehicle($vehicleBaseUrl, $dispatchConfig['vehicle_key'], $schedule->vehicle_id);
            $schedule->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'Initial operational allocation failed',
                'errors' => $maintenanceResponse->json(),
            ], $maintenanceResponse->status());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Dispatch schedule created, vehicle locked, and operational allocation recorded successfully',
            'data' => [
                'schedule' => $schedule,
                'vehicle' => $lockResponse->json('data'),
                'maintenance' => $maintenanceResponse->json('data'),
            ],
            'integration' => [
                'vehicle_verification_status' => $vehicleResponse->status(),
                'vehicle_lock_status' => $lockResponse->status(),
                'maintenance_allocation_status' => $maintenanceResponse->status(),
                'central_infrastructure' => $maintenanceResponse->json('integration'),
            ],
            'meta' => [
                'service_name' => 'Schedule-Service',
                'api_version' => 'v1'
            ]
        ], 201);
    }

    private function releaseVehicle(string $vehicleBaseUrl, string $vehicleKey, int $vehicleId): void
    {
        try {
            Http::withHeaders([
                'X-IAE-KEY' => $vehicleKey,
            ])->patch("{$vehicleBaseUrl}/api/internal/vehicles/{$vehicleId}/status", [
                'status' => 'Available',
                'notes' => 'Released after dispatch orchestration rollback',
            ]);
        } catch (Throwable) {
            // Best-effort rollback; the failure is reported by the original operation.
        }
    }
}
