<?php

namespace App\Application\Listing;

use App\Domain\Listing\Exceptions\ListingException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ListingImageValidator
{
    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = ['svg', 'gif', 'bmp', 'ico', 'avif', 'apng'];

    public function validateUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw $this->validationError('listing.image_invalid', 'image');
        }

        $maxBytes = (int) config('media.listing.max_kb') * 1024;

        if ($file->getSize() > $maxBytes) {
            throw $this->validationError('listing.image_too_large', 'image');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw $this->validationError('listing.image_invalid_type', 'image');
        }

        $detectedMime = $this->detectMimeType($file);

        if (str_contains($detectedMime, 'svg') || str_contains($detectedMime, 'gif')) {
            throw $this->validationError('listing.image_invalid_type', 'image');
        }

        if (! in_array($detectedMime, config('media.listing.allowed_mimes'), true)) {
            throw $this->validationError('listing.image_invalid_type', 'image');
        }

        if (! in_array($extension, config('media.listing.allowed_extensions'), true)) {
            throw $this->validationError('listing.image_invalid_type', 'image');
        }

        if (str_contains($file->getClientOriginalName(), '..')) {
            throw $this->validationError('listing.image_invalid', 'image');
        }

        $imageInfo = @getimagesize($file->getRealPath());

        if ($imageInfo === false) {
            throw $this->validationError('listing.image_invalid', 'image');
        }

        [$width, $height] = $imageInfo;
        $maxDimension = (int) config('media.listing.max_dimension');
        $maxPixels = (int) config('media.listing.max_pixels');

        if ($width <= 0 || $height <= 0 || $width > $maxDimension || $height > $maxDimension) {
            throw $this->validationError('listing.image_dimensions_exceeded', 'image');
        }

        if (($width * $height) > $maxPixels) {
            throw $this->validationError('listing.image_dimensions_exceeded', 'image');
        }

        $imageMime = image_type_to_mime_type($imageInfo[2]);

        if (! in_array($imageMime, config('media.listing.allowed_mimes'), true)) {
            throw $this->validationError('listing.image_invalid', 'image');
        }
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

    private function validationError(string $code, string $field): ListingException
    {
        return new ListingException(
            errorCode: $code,
            message: 'Validation failed.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            errors: [$field => [$code]],
        );
    }
}
