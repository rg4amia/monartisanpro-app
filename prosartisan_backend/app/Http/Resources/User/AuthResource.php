<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for authentication response
 *
 * Returns user data with authentication token
 */
class AuthResource extends JsonResource
{
    private string $token;

    /**
     * Create a new resource instance.
     *
     * @param mixed $resource
     * @param string $token
     */
    public function __construct($resource, string $token)
    {
        parent::__construct($resource);
        $this->token = $token;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userData = match ($this->resource->getType()->getValue()) {
            'ARTISAN' => (new ArtisanResource($this->resource))->toArray($request),
            'FOURNISSEUR' => (new FournisseurResource($this->resource))->toArray($request),
            default => (new UserResource($this->resource))->toArray($request),
        };

        return [
            'user' => $userData,
            'token' => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
