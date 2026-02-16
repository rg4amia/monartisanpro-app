<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    /**
     * Upload KYC documents (ID and selfie).
     */
    public function uploadDocuments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_type' => ['required', Rule::in(['cni', 'passport', 'attestation'])],
            'document' => ['required', 'image', 'max:5120'], // 5MB max
            'selfie' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Store documents
            $documentPath = $request->file('document')->store('kyc_documents', 'public');
            $selfiePath = $request->file('selfie')->store('kyc_documents', 'public');

            // Create or update KYC document
            $kycDocument = KycDocument::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'document_type' => $request->document_type,
                    'document_path' => $documentPath,
                    'selfie_path' => $selfiePath,
                    'verification_status' => 'pending',
                    'rejection_reason' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                ]
            );

            // Update user's KYC status
            $user->update(['kyc_status' => 'pending']);

            return response()->json([
                'success' => true,
                'message' => 'KYC documents uploaded successfully',
                'data' => $kycDocument
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KYC verification status.
     */
    public function getStatus(Request $request)
    {
        $user = $request->user();
        $kycDocument = $user->kycDocuments()->latest()->first();

        if (!$kycDocument) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'not_submitted',
                    'message' => 'No KYC documents submitted'
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $kycDocument->verification_status,
                'document_type' => $kycDocument->document_type,
                'submitted_at' => $kycDocument->created_at,
                'verified_at' => $kycDocument->verified_at,
                'rejection_reason' => $kycDocument->rejection_reason,
            ]
        ]);
    }

    /**
     * Get KYC documents for review (admin only).
     */
    public function getDocuments(Request $request)
    {
        $user = $request->user();
        $kycDocuments = $user->kycDocuments()->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $kycDocuments
        ]);
    }
}
