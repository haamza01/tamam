<?php

namespace App\Application\Profile;

use App\Domain\Profile\Exceptions\ProfileException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AvatarStorageService
{
    public function resolveUrl(?string $path): string
    {
        if ($path === null || $path === '') {
            return $this->defaultUrl();
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk($this->disk())->url($path);
    }

    public function defaultUrl(): string
    {
        $default = (string) config('media.avatar.default_url');

        if (str_starts_with($default, 'http://') || str_starts_with($default, 'https://') || str_starts_with($default, '/')) {
            return $default;
        }

        return '/'.ltrim($default, '/');
    }

    public function store(User $user, UploadedFile $file): string
    {
        $this->validateImage($file);

        $extension = $this->resolveExtension($file);
        $objectKey = $this->buildObjectKey($user, $extension);

        $stored = Storage::disk($this->disk())->put($objectKey, file_get_contents($file->getRealPath()), [
            'visibility' => 'public',
            'ContentType' => $file->getMimeType(),
        ]);

        if ($stored !== true) {
            throw new ProfileException(
                errorCode: 'profile.avatar_storage_failed',
                message: 'Unable to store avatar at this time.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $objectKey;
    }

    public function replace(User $user, UploadedFile $file): User
    {
        $previousPath = $user->avatar;

        return DB::transaction(function () use ($user, $file, $previousPath): User {
            $newPath = $this->store($user, $file);

            $user->forceFill(['avatar' => $newPath])->save();

            if ($previousPath !== null && $previousPath !== '' && $previousPath !== $newPath) {
                $this->deleteObject($previousPath);
            }

            return $user->fresh();
        });
    }

    public function delete(User $user): User
    {
        $previousPath = $user->avatar;

        if ($previousPath !== null && $previousPath !== '') {
            $this->deleteObject($previousPath);
        }

        $user->forceFill(['avatar' => null])->save();

        return $user->fresh();
    }

    public function validateImage(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new ProfileException(
                errorCode: 'profile.avatar_invalid',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['avatar' => ['profile.avatar_invalid']],
            );
        }

        $maxBytes = (int) config('media.avatar.max_kb') * 1024;

        if ($file->getSize() > $maxBytes) {
            throw new ProfileException(
                errorCode: 'profile.avatar_too_large',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['avatar' => ['profile.avatar_too_large']],
            );
        }

        $detectedMime = $this->detectMimeType($file);

        if (! in_array($detectedMime, config('media.avatar.allowed_mimes'), true)) {
            throw new ProfileException(
                errorCode: 'profile.avatar_invalid_type',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['avatar' => ['profile.avatar_invalid_type']],
            );
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, config('media.avatar.allowed_extensions'), true)) {
            throw new ProfileException(
                errorCode: 'profile.avatar_invalid_type',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['avatar' => ['profile.avatar_invalid_type']],
            );
        }

        if (str_contains($file->getClientOriginalName(), '..')) {
            throw new ProfileException(
                errorCode: 'profile.avatar_invalid',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['avatar' => ['profile.avatar_invalid']],
            );
        }

        $imageInfo = @getimagesize($file->getRealPath());

        if ($imageInfo === false) {
            throw new ProfileException(
                errorCode: 'profile.avatar_invalid',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['avatar' => ['profile.avatar_invalid']],
            );
        }

        $imageMime = image_type_to_mime_type($imageInfo[2]);

        if (! in_array($imageMime, config('media.avatar.allowed_mimes'), true)) {
            throw new ProfileException(
                errorCode: 'profile.avatar_invalid',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['avatar' => ['profile.avatar_invalid']],
            );
        }
    }

    private function buildObjectKey(User $user, string $extension): string
    {
        $prefix = trim((string) config('media.avatar.path_prefix'), '/');

        return $prefix.'/'.$user->id.'/'.Str::uuid()->toString().'.'.$extension;
    }

    private function resolveExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function detectMimeType(UploadedFile $file): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        try {
            return (string) finfo_file($finfo, $file->getRealPath());
        } finally {
            finfo_close($finfo);
        }
    }

    private function deleteObject(string $path): void
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    private function disk(): string
    {
        return (string) config('media.avatar.disk');
    }
}
