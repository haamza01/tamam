<?php

namespace App\Providers;

use App\Application\Audit\AuditLogService;
use App\Application\Auth\AuthAuditService;
use App\Application\Auth\AuthCookieService;
use App\Application\Auth\AuthService;
use App\Application\Auth\OtpService;
use App\Application\Auth\PasswordResetService;
use App\Application\Auth\PhoneNormalizer;
use App\Application\Auth\PhoneVerificationService;
use App\Application\Auth\RefreshTokenService;
use App\Application\Catalog\CatalogCacheService;
use App\Application\Category\CategoryHierarchyValidator;
use App\Application\Category\CategoryListingCountService;
use App\Application\Category\CategoryService;
use App\Application\Listing\ListingAttributeValidator;
use App\Application\Listing\ListingImageProcessor;
use App\Application\Listing\ListingImageService;
use App\Application\Listing\ListingImageStorageService;
use App\Application\Listing\ListingImageValidator;
use App\Application\Listing\ListingService;
use App\Application\Listing\ListingStateMachine;
use App\Application\Location\LocationService;
use App\Application\Moderation\ProhibitedWordsChecker;
use App\Application\Platform\PlatformSettingsService;
use App\Application\Profile\AvatarStorageService;
use App\Application\Profile\ProfileAuditService;
use App\Application\Profile\ProfileService;
use App\Application\Search\CategoryDescendantResolver;
use App\Application\Search\PopularSearchService;
use App\Application\Search\PublicListingQueryBuilder;
use App\Application\Search\SearchQueryParser;
use App\Application\Search\SearchService;
use App\Application\Search\SearchSuggestionService;
use App\Application\Shared\LocaleResolver;
use App\Application\Shared\SlugGenerator;
use App\Application\Storage\PublicAssetUrlResolver;
use App\Domain\Auth\Contracts\OtpProviderInterface;
use App\Infrastructure\Auth\LogOtpProvider;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\District;
use App\Models\DistrictTranslation;
use App\Observers\CategoryObserver;
use App\Observers\CategoryTranslationObserver;
use App\Observers\LocationObserver;
use App\Observers\LocationTranslationObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlatformSettingsService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(ProhibitedWordsChecker::class);
        $this->app->singleton(PhoneNormalizer::class);
        $this->app->singleton(OtpService::class);
        $this->app->singleton(RefreshTokenService::class);
        $this->app->singleton(AuthService::class);
        $this->app->singleton(AuthAuditService::class);
        $this->app->singleton(AuthCookieService::class);
        $this->app->singleton(PhoneVerificationService::class);
        $this->app->singleton(PasswordResetService::class);

        $this->app->singleton(ProfileService::class);
        $this->app->singleton(AvatarStorageService::class);
        $this->app->singleton(ProfileAuditService::class);

        $this->app->singleton(CatalogCacheService::class);
        $this->app->singleton(LocaleResolver::class);
        $this->app->singleton(CategoryHierarchyValidator::class);
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(LocationService::class);

        $this->app->singleton(SlugGenerator::class);
        $this->app->singleton(CategoryListingCountService::class);
        $this->app->singleton(ListingAttributeValidator::class);
        $this->app->singleton(PublicAssetUrlResolver::class);
        $this->app->singleton(ListingImageValidator::class);
        $this->app->singleton(ListingImageStorageService::class);
        $this->app->singleton(ListingImageProcessor::class);
        $this->app->singleton(ListingImageService::class);
        $this->app->singleton(ListingStateMachine::class);
        $this->app->singleton(ListingService::class);

        $this->app->singleton(CategoryDescendantResolver::class);
        $this->app->singleton(SearchAttributeFilterApplier::class);
        $this->app->singleton(PublicListingQueryBuilder::class);
        $this->app->singleton(SearchQueryParser::class);
        $this->app->singleton(SearchService::class);
        $this->app->singleton(SearchSuggestionService::class);
        $this->app->singleton(PopularSearchService::class);

        $this->app->bind(OtpProviderInterface::class, function (): OtpProviderInterface {
            $driver = (string) config('otp.driver');

            if ($driver !== 'log') {
                throw new RuntimeException("Unsupported OTP driver [{$driver}] configured.");
            }

            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException('Log OTP provider cannot be configured outside local and testing environments.');
            }

            return new LogOtpProvider;
        });
    }

    public function boot(): void
    {
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('auth-refresh', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('auth-password', fn (Request $request) => Limit::perHour(3)->by($request->ip().':'.$request->input('identifier', 'unknown')));
        RateLimiter::for('auth-otp', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('auth-otp-resend', fn (Request $request) => Limit::perHour(3)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('profile-update', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('profile-avatar', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('listing-write', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('listing-image', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute((int) config('search.rate_limits.search_per_minute'))->by($request->ip()));
        RateLimiter::for('search-suggestions', fn (Request $request) => Limit::perMinute((int) config('search.rate_limits.suggestions_per_minute'))->by($request->ip()));
        RateLimiter::for('search-popular', fn (Request $request) => Limit::perMinute((int) config('search.rate_limits.popular_per_minute'))->by($request->ip()));

        Category::observe(CategoryObserver::class);
        CategoryTranslation::observe(CategoryTranslationObserver::class);
        Country::observe(LocationObserver::class);
        CountryTranslation::observe(LocationTranslationObserver::class);
        City::observe(LocationObserver::class);
        CityTranslation::observe(LocationTranslationObserver::class);
        District::observe(LocationObserver::class);
        DistrictTranslation::observe(LocationTranslationObserver::class);
    }
}
