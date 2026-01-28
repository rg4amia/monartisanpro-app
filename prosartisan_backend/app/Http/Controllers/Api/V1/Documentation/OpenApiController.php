<?php

namespace App\Http\Controllers\Api\V1\Documentation;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * OpenAPI Documentation Controller
 *
 * Serves the OpenAPI specification and documentation UI
 */
class OpenApiController extends Controller
{
    /**
     * Get the OpenAPI specification in JSON format
     */
    public function getSpec(): JsonResponse
    {
        $specPath = storage_path('api-docs/api-docs.json');

        if (! File::exists($specPath)) {
            return response()->json([
                'error' => 'DOCUMENTATION_NOT_FOUND',
                'message' => 'API documentation not found',
                'status_code' => 404,
            ], 404);
        }

        $spec = File::get($specPath);
        $decodedSpec = json_decode($spec, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'error' => 'INVALID_DOCUMENTATION',
                'message' => 'API documentation is malformed',
                'status_code' => 500,
            ], 500);
        }

        return response()->json($decodedSpec);
    }

    /**
     * Serve the Swagger UI documentation page
     */
    public function getSwaggerUI(): Response
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ProSartisan API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin:0;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "'.url('/api/v1/docs/spec').'",
                dom_id: "#swagger-ui",
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: null,
                tryItOutEnabled: true,
                supportedSubmitMethods: ["get", "post", "put", "delete", "patch"],
                onComplete: function() {
                    console.log("ProSartisan API Documentation loaded");
                }
            });
        };
    </script>
</body>
</html>';

        return response($html)->header('Content-Type', 'text/html');
    }
}
