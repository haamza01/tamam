<?php

namespace App\Application\Favorite;

use App\Application\Listing\ListingExpiryService;
use App\Application\Search\PublicListingQueryBuilder;
use App\Domain\Favorite\Exceptions\FavoriteException;
use App\Domain\Listing\Exceptions\ListingException;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class FavoriteService
{
    public function __construct(
        private readonly ListingExpiryService $listingExpiry,
        private readonly PublicListingQueryBuilder $publicListingQuery,
        private readonly ListingStatisticsCounter $statisticsCounter,
    ) {}

    public function add(User $user, string $listingId): Favorite
    {
        $this->listingExpiry->expireDue();

        $listing = $this->resolveListing($listingId);

        if ($listing->isOwnedBy($user)) {
            throw $this->ownListing();
        }

        if (! $this->isPubliclyFavoritable($listingId)) {
            throw $this->listingNotFound();
        }

        try {
            return DB::transaction(function () use ($user, $listingId): Favorite {
                $favorite = Favorite::query()->create([
                    'user_id' => $user->id,
                    'listing_id' => $listingId,
                ]);

                $this->statisticsCounter->incrementFavorites($listingId);

                return $favorite;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw $this->alreadyExists();
            }

            throw $exception;
        }
    }

    public function remove(User $user, string $listingId): void
    {
        DB::transaction(function () use ($user, $listingId): void {
            $deleted = Favorite::query()
                ->where('user_id', $user->id)
                ->where('listing_id', $listingId)
                ->delete();

            if ($deleted > 0) {
                $this->statisticsCounter->decrementFavorites($listingId);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function paginateForUser(User $user, array $query): LengthAwarePaginator
    {
        $this->listingExpiry->expireDue();

        $perPage = min(max(1, (int) ($query['per_page'] ?? 20)), 100);

        return Favorite::query()
            ->where('user_id', $user->id)
            ->whereHas('listing', fn ($listingQuery) => $this->publicListingQuery->applyPublicVisibility($listingQuery))
            ->with(['listing.category.translations', 'listing.city.translations', 'listing.images'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, (int) ($query['page'] ?? 1)));
    }

    private function resolveListing(string $listingId): Listing
    {
        $listing = Listing::query()->find($listingId);

        if ($listing === null || $listing->isSoftDeleted()) {
            throw $this->listingNotFound();
        }

        return $listing;
    }

    private function isPubliclyFavoritable(string $listingId): bool
    {
        return $this->publicListingQuery->base()
            ->whereKey($listingId)
            ->exists();
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23505', '23000'], true);
    }

    private function listingNotFound(): ListingException
    {
        return new ListingException(
            errorCode: 'listing.not_found',
            message: 'The requested listing was not found.',
            status: Response::HTTP_NOT_FOUND,
        );
    }

    private function ownListing(): FavoriteException
    {
        return new FavoriteException(
            errorCode: 'favorite.own_listing',
            message: 'You cannot favorite your own listing.',
            status: Response::HTTP_FORBIDDEN,
            errors: ['listing' => ['favorite.own_listing']],
        );
    }

    private function alreadyExists(): FavoriteException
    {
        return new FavoriteException(
            errorCode: 'favorite.already_exists',
            message: 'This listing is already in your favorites.',
            status: Response::HTTP_CONFLICT,
            errors: ['favorite' => ['favorite.already_exists']],
        );
    }
}
