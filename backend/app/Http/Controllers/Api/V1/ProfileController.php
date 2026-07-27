<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Profile\AvatarStorageService;
use App\Application\Profile\ProfileAuditService;
use App\Application\Profile\ProfileService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly AvatarStorageService $avatarStorageService,
        private readonly ProfileAuditService $profileAudit,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        return ApiResponse::success(
            data: ['profile' => new ProfileResource($request->user())],
            message: 'Profile retrieved successfully.',
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $profile = $this->profileService->update(
            $request->user(),
            $request->validated(),
            $request->all(),
        );

        return ApiResponse::success(
            data: ['profile' => new ProfileResource($profile)],
            message: 'Profile updated successfully.',
        );
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $profile = $this->avatarStorageService->replace(
            $request->user(),
            $request->file('avatar'),
        );

        $this->profileAudit->avatarUploaded($profile);

        return ApiResponse::success(
            data: ['profile' => new ProfileResource($profile)],
            message: 'Avatar uploaded successfully.',
        );
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $this->authorize('update', $request->user());

        $profile = $this->avatarStorageService->delete($request->user());

        $this->profileAudit->avatarDeleted($profile);

        return ApiResponse::success(
            data: ['profile' => new ProfileResource($profile)],
            message: 'Avatar removed successfully.',
        );
    }
}
