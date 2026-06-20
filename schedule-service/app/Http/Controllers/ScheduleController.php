<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Schedule Service API",
    description: "REST API untuk Service Penjadwalan Driver. Seluruh endpoint diamankan menggunakan API Key melalui header X-IAE-KEY."
)]
#[OA\Server(
    url: "http://127.0.0.1:8000",
    description: "Local Development Server"
)]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    name: "X-IAE-KEY",
    in: "header",
    description: "API Key menggunakan NIM mahasiswa"
)]
class ScheduleController extends Controller
{
    #[OA\Get(
        path: "/api/v1/schedules",
        summary: "Melihat daftar seluruh penugasan perjalanan dinas",
        tags: ["Schedule Service"],
        security: [["ApiKeyAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Schedules retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
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
        ]);
    }

    #[OA\Get(
        path: "/api/v1/schedules/{id}",
        summary: "Melihat detail penugasan perjalanan dinas berdasarkan ID",
        tags: ["Schedule Service"],
        security: [["ApiKeyAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Data retrieved successfully"),
            new OA\Response(response: 404, description: "Schedule not found"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function show($id)
    {
        $schedule = Schedule::find($id);

        if (!$schedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'Schedule not found',
                'errors' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule retrieved successfully',
            'data' => $schedule
        ]);
    }

    #[OA\Post(
        path: "/api/v1/schedules",
        summary: "Membuat penugasan perjalanan dinas baru",
        tags: ["Schedule Service"],
        security: [["ApiKeyAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    "vehicle_id",
                    "driver_id",
                    "destination",
                    "departure_date",
                    "return_date",
                    "purpose"
                ],
                properties: [
                    new OA\Property(property: "vehicle_id", type: "integer", example: 101),
                    new OA\Property(property: "driver_id", type: "integer", example: 201),
                    new OA\Property(property: "destination", type: "string", example: "Bandung"),
                    new OA\Property(property: "departure_date", type: "string", example: "2026-06-15"),
                    new OA\Property(property: "return_date", type: "string", example: "2026-06-17"),
                    new OA\Property(property: "purpose", type: "string", example: "Kunjungan Dinas")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Schedule created successfully"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function store(Request $request)
    {
        $schedule = Schedule::create([
            'vehicle_id' => $request->vehicle_id,
            'driver_id' => $request->driver_id,
            'destination' => $request->destination,
            'departure_date' => $request->departure_date,
            'return_date' => $request->return_date,
            'purpose' => $request->purpose
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule created successfully',
            'data' => $schedule
        ], 201);
    }
}