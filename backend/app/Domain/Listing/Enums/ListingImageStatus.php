<?php

namespace App\Domain\Listing\Enums;

enum ListingImageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Failed],
            self::Processing => [self::Ready, self::Failed],
            self::Ready => [],
            self::Failed => [self::Processing],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function countsTowardSubmitMinimum(): bool
    {
        return $this === self::Ready;
    }
}
