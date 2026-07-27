<?php

namespace App\Application\Listing;

use App\Application\Audit\AuditLogService;
use App\Application\Category\CategoryListingCountService;
use App\Application\Moderation\ProhibitedWordsChecker;
use App\Application\Platform\PlatformSettingsService;
use App\Application\Shared\SlugGenerator;
use App\Domain\Category\Enums\CategoryStatus;
use App\Domain\Listing\Enums\ListingStatus;
use App\Domain\Listing\Enums\PriceType;
use App\Domain\Listing\Exceptions\ListingException;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Listing;
use App\Models\ListingAttributeValue;
use App\Models\ListingStatistic;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ListingService
{
    /** @var list<string> */
    private const SIGNIFICANT_FIELDS = ['title', 'description', 'category_id', 'price', 'price_type'];

    public function __construct(
        private readonly SlugGenerator $slugGenerator,
        private readonly ListingAttributeValidator $attributeValidator,
        private readonly ListingStateMachine $stateMachine,
        private readonly ProhibitedWordsChecker $prohibitedWords,
        private readonly PlatformSettingsService $settings,
        private readonly CategoryListingCountService $listingCounts,
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Listing
    {
        $this->assertCanCreate($user);

        return DB::transaction(function () use ($user, $data): Listing {
            $category = $this->resolveLeafCategory((string) $data['category_id']);
            $city = $this->resolveCity((string) $data['city_id']);
            $districtId = $this->resolveDistrictId($data['district_id'] ?? null, $city->id);

            $this->assertContentSafe((string) $data['title'], (string) $data['description']);

            $attributes = $this->attributeValidator->validateAndNormalize(
                $category,
                $data['attributes'] ?? null,
                requireAllRequired: false,
            );

            $listing = Listing::query()->create([
                'user_id' => $user->id,
                'category_id' => $category->id,
                'city_id' => $city->id,
                'district_id' => $districtId,
                'title' => $data['title'],
                'slug' => $this->slugGenerator->forListing((string) $data['title']),
                'description' => strip_tags((string) $data['description']),
                'price' => $this->resolvePrice($data),
                'price_type' => $data['price_type'],
                'currency' => $data['currency'] ?? 'QAR',
                'condition' => $data['condition'] ?? null,
                'status' => ListingStatus::Draft,
                'contact_preferences' => $this->normalizeContactPreferences($data['contact_preferences'] ?? null),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            $this->attributeValidator->syncValues($listing, $attributes);
            ListingStatistic::query()->create(['listing_id' => $listing->id]);

            $this->auditLog->log('listing.created', $listing, $user, [
                'category_id' => $listing->category_id,
                'city_id' => $listing->city_id,
            ]);

            return $listing->fresh(['category', 'city', 'district', 'attributeValues.categoryAttribute', 'statistics', 'user']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Listing $listing, array $data): Listing
    {
        if (! $listing->isOwnedBy($user)) {
            throw $this->notEditable();
        }

        if (! $listing->isEditableByOwner()) {
            throw $this->notEditable();
        }

        return DB::transaction(function () use ($user, $listing, $data): Listing {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $previousCategoryId = $locked->category_id;
            $previousStatus = $locked->status;
            $changes = [];

            if (array_key_exists('category_id', $data)) {
                $category = $this->resolveLeafCategory((string) $data['category_id']);
                $locked->category_id = $category->id;
                $changes[] = 'category_id';
            } else {
                $category = Category::query()->findOrFail($locked->category_id);
            }

            if (array_key_exists('city_id', $data) || array_key_exists('district_id', $data)) {
                $city = $this->resolveCity((string) ($data['city_id'] ?? $locked->city_id));
                $locked->city_id = $city->id;
                $locked->district_id = $this->resolveDistrictId($data['district_id'] ?? $locked->district_id, $city->id);
                $changes[] = 'city_id';
            }

            foreach (['title', 'description', 'price_type', 'currency', 'condition', 'latitude', 'longitude'] as $field) {
                if (array_key_exists($field, $data)) {
                    $locked->{$field} = $field === 'description'
                        ? strip_tags((string) $data[$field])
                        : $data[$field];
                    $changes[] = $field;
                }
            }

            if (array_key_exists('price', $data) || array_key_exists('price_type', $data)) {
                $locked->price = $this->resolvePrice(array_merge($locked->only(['price', 'price_type']), $data));
                $changes[] = 'price';
            }

            if (array_key_exists('contact_preferences', $data)) {
                $locked->contact_preferences = $this->normalizeContactPreferences($data['contact_preferences']);
            }

            if (array_key_exists('title', $data) || array_key_exists('description', $data)) {
                $this->assertContentSafe($locked->title, $locked->description);
            }

            if (array_key_exists('attributes', $data)) {
                $attributes = $this->attributeValidator->validateAndNormalize(
                    $category,
                    $data['attributes'],
                    requireAllRequired: false,
                );
                $this->attributeValidator->syncValues($locked, $attributes);
                $changes[] = 'attributes';
            } elseif (array_key_exists('category_id', $data)) {
                $attributes = $this->attributeValidator->validateAndNormalize(
                    $category,
                    [],
                    requireAllRequired: false,
                );
                $this->attributeValidator->syncValues($locked, $attributes);
            }

            if ($this->shouldRemoderate($locked, $changes) && $previousStatus === ListingStatus::Published) {
                $this->listingCounts->decrement($previousCategoryId);
                $locked->status = ListingStatus::PendingReview;
                $locked->published_at = null;
                $locked->expires_at = null;
            } elseif ($previousCategoryId !== $locked->category_id && $previousStatus->countsTowardCategoryListingCount()) {
                $this->listingCounts->decrement($previousCategoryId);
                $this->listingCounts->increment($locked->category_id);
            }

            $locked->version = $locked->version + 1;
            $locked->save();

            $this->auditLog->log('listing.updated', $locked, $user, [
                'changed_fields' => array_values(array_unique($changes)),
            ]);

            return $locked->fresh(['category', 'city', 'district', 'attributeValues.categoryAttribute', 'statistics', 'user']);
        });
    }

    public function findPublic(string $id): Listing
    {
        $listing = Listing::query()
            ->whereKey($id)
            ->where('status', ListingStatus::Published)
            ->whereNull('deleted_at')
            ->with($this->detailRelations())
            ->first();

        if ($listing === null) {
            throw $this->notFound();
        }

        return $listing;
    }

    public function findAccessible(string $id, ?User $user): Listing
    {
        $listing = Listing::query()
            ->withTrashed()
            ->with($this->detailRelations())
            ->find($id);

        if ($listing === null || $listing->status === ListingStatus::Deleted) {
            throw $this->notFound();
        }

        if ($listing->status->isPubliclyVisible()) {
            return $listing;
        }

        if ($user !== null && ($listing->isOwnedBy($user) || $user->hasAnyRole(['moderator', 'admin', 'super_admin']))) {
            return $listing;
        }

        throw $this->notFound();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePublic(array $filters): LengthAwarePaginator
    {
        $query = Listing::query()
            ->where('status', ListingStatus::Published)
            ->whereNull('deleted_at')
            ->with(['category.translations', 'city.translations', 'user']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        $sort = $filters['sort'] ?? 'latest';

        if ($sort === 'price_asc') {
            $query->orderByRaw('price IS NULL')->orderBy('price');
        } elseif ($sort === 'price_desc') {
            $query->orderByRaw('price IS NULL')->orderByDesc('price');
        } else {
            $query->orderByDesc('published_at');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForOwner(User $user, array $filters): LengthAwarePaginator
    {
        $query = Listing::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', ListingStatus::Deleted)
            ->with(['category.translations', 'city.translations']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('updated_at')->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function latest(int $limit = 12): Collection
    {
        return Listing::query()
            ->where('status', ListingStatus::Published)
            ->whereNull('deleted_at')
            ->with(['category.translations', 'city.translations', 'user'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function featured(int $limit = 12): Collection
    {
        return Listing::query()
            ->where('status', ListingStatus::Published)
            ->where('featured', true)
            ->whereNull('deleted_at')
            ->with(['category.translations', 'city.translations', 'user'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function similar(Listing $listing, int $limit = 8): Collection
    {
        return Listing::query()
            ->where('status', ListingStatus::Published)
            ->whereNull('deleted_at')
            ->where('category_id', $listing->category_id)
            ->where('city_id', $listing->city_id)
            ->whereKeyNot($listing->id)
            ->with(['category.translations', 'city.translations', 'user'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function submitForReview(User $user, Listing $listing): Listing
    {
        if (! $listing->isOwnedBy($user)) {
            throw $this->notEditable();
        }

        $listing->load(['attributeValues.categoryAttribute']);

        $category = Category::query()->findOrFail($listing->category_id);

        $this->attributeValidator->validateAndNormalize(
            $category,
            $listing->attributeValues->map(fn ($value) => [
                'slug' => $value->categoryAttribute->slug,
                'value' => $this->extractAttributeValue($value),
            ])->values()->all(),
            requireAllRequired: true,
        );

        return $this->stateMachine->submit($listing, $user);
    }

    private function assertCanCreate(User $user): void
    {
        $max = $this->settings->getInt('max_active_listings_user', 10);
        $activeCount = Listing::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                ListingStatus::Draft,
                ListingStatus::PendingReview,
                ListingStatus::Published,
                ListingStatus::Paused,
                ListingStatus::Rejected,
            ])
            ->count();

        if ($activeCount >= $max) {
            throw new ListingException(
                errorCode: 'listing.limit_reached',
                message: 'You have reached the maximum number of active listings.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['listing' => ['listing.limit_reached']],
            );
        }
    }

    private function resolveLeafCategory(string $categoryId): Category
    {
        $category = Category::query()->find($categoryId);

        if ($category === null || $category->status !== CategoryStatus::Active || ! $category->isLeaf()) {
            throw new ListingException(
                errorCode: 'category.must_be_leaf',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['category_id' => ['category.must_be_leaf']],
            );
        }

        return $category;
    }

    private function resolveCity(string $cityId): City
    {
        $city = City::query()->whereKey($cityId)->where('is_active', true)->first();

        if ($city === null) {
            throw new ListingException(
                errorCode: 'listing.invalid_location',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['city_id' => ['listing.invalid_location']],
            );
        }

        return $city;
    }

    private function resolveDistrictId(mixed $districtId, string $cityId): ?string
    {
        if ($districtId === null || $districtId === '') {
            return null;
        }

        $district = District::query()
            ->whereKey($districtId)
            ->where('city_id', $cityId)
            ->where('is_active', true)
            ->first();

        if ($district === null) {
            throw new ListingException(
                errorCode: 'listing.invalid_location',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['district_id' => ['listing.invalid_location']],
            );
        }

        return $district->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePrice(array $data): ?string
    {
        $priceType = PriceType::from((string) $data['price_type']);

        if (! $priceType->requiresPrice()) {
            return null;
        }

        return isset($data['price']) ? (string) $data['price'] : null;
    }

    /**
     * @return array<string, bool>|null
     */
    private function normalizeContactPreferences(mixed $preferences): ?array
    {
        if (! is_array($preferences)) {
            return [
                'in_app' => true,
                'phone' => false,
                'whatsapp' => false,
                'email' => false,
            ];
        }

        return [
            'in_app' => (bool) ($preferences['in_app'] ?? true),
            'phone' => (bool) ($preferences['phone'] ?? false),
            'whatsapp' => (bool) ($preferences['whatsapp'] ?? false),
            'email' => (bool) ($preferences['email'] ?? false),
        ];
    }

    private function assertContentSafe(string $title, string $description): void
    {
        if ($this->prohibitedWords->containsProhibitedWords($title.' '.$description)) {
            throw new ListingException(
                errorCode: 'listing.prohibited_content',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['content' => ['listing.prohibited_content']],
            );
        }
    }

    /**
     * @param  list<string>  $changes
     */
    private function shouldRemoderate(Listing $listing, array $changes): bool
    {
        if (! $this->settings->getBool('remoderate_on_significant_edit')) {
            return false;
        }

        return count(array_intersect(self::SIGNIFICANT_FIELDS, $changes)) > 0;
    }

    private function extractAttributeValue(ListingAttributeValue $value): mixed
    {
        return $value->value_json
            ?? $value->value_text
            ?? $value->value_number
            ?? $value->value_boolean
            ?? ($value->value_date?->format('Y-m-d'));
    }

    /**
     * @return list<string>
     */
    private function detailRelations(): array
    {
        return [
            'category.translations',
            'city.translations',
            'district.translations',
            'attributeValues.categoryAttribute.translations',
            'attributeValues.categoryAttribute.options.translations',
            'statistics',
            'user',
        ];
    }

    private function notFound(): ListingException
    {
        return new ListingException(
            errorCode: 'listing.not_found',
            message: 'The requested listing was not found.',
            status: Response::HTTP_NOT_FOUND,
        );
    }

    private function notEditable(): ListingException
    {
        return new ListingException(
            errorCode: 'listing.not_editable',
            message: 'This listing cannot be edited in its current state.',
            status: Response::HTTP_FORBIDDEN,
        );
    }
}
