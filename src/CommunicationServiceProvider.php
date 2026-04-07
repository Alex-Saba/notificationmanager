<?php

namespace Acl\Communications;

use Acl\Communications\Channels\NullChannel;
use Acl\Communications\Console\Commands\SyncNotificationEventsCommand;
use Acl\Communications\Contracts\ApplicationEventCatalogInterface;
use Acl\Communications\Contracts\ApplicationEventConsumerInterface;
use Acl\Communications\Contracts\ChannelDriverInterface;
use Acl\Communications\Contracts\CommunicationExposureConsumerInterface;
use Acl\Communications\Contracts\CommunicationReactionPolicyInterface;
use Acl\Communications\Contracts\CommunicationResultConsumerInterface;
use Acl\Communications\Contracts\CommunicationServiceInterface;
use Acl\Communications\Contracts\NotificationManagerInterface;
use Acl\Communications\Contracts\RuleResolverInterface;
use Acl\Communications\Contracts\TemplateRendererInterface;
use Acl\Communications\Contracts\TemplateRepositoryInterface;
use Acl\Communications\Events\CommunicationOrchestrated;
use Acl\Communications\Events\NotificationFailed;
use Acl\Communications\Events\NotificationSent;
use Acl\Communications\Listeners\CommunicationExposureListener;
use Acl\Communications\Listeners\CommunicationOutcomeListener;
use Acl\Communications\Services\ConfigApplicationEventCatalog;
use Acl\Communications\Services\CommunicationResultConsumer;
use Acl\Communications\Services\DatabaseRuleResolver;
use Acl\Communications\Services\DatabaseTemplateRepository;
use Acl\Communications\Services\HostOwnedReactionPolicy;
use Acl\Communications\Services\HostCommunicationExposureConsumer;
use Acl\Communications\Services\LaravelApplicationEventConsumer;
use Acl\Communications\Services\NotificationManager;
use Acl\Communications\Services\NotificationTemplateResolver;
use Acl\Communications\Services\ConfigRuleResolver;
use Acl\Communications\Services\ConfigTemplateRepository;
use Acl\Communications\Services\CommunicationService;
use Acl\Communications\Templates\BladeTemplateRenderer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/communications.php', 'communications');

        $this->app->singleton(TemplateRendererInterface::class, BladeTemplateRenderer::class);
        $this->app->singleton(ChannelDriverInterface::class, NullChannel::class);
        $this->app->singleton(NotificationTemplateResolver::class, NotificationTemplateResolver::class);
        $this->app->singleton(NotificationManagerInterface::class, NotificationManager::class);
        $this->app->singleton(ApplicationEventCatalogInterface::class, ConfigApplicationEventCatalog::class);
        $this->app->singleton(ApplicationEventConsumerInterface::class, LaravelApplicationEventConsumer::class);
        $this->app->singleton(CommunicationReactionPolicyInterface::class, HostOwnedReactionPolicy::class);
        $this->app->singleton(CommunicationResultConsumerInterface::class, CommunicationResultConsumer::class);
        $this->app->singleton(CommunicationExposureConsumerInterface::class, HostCommunicationExposureConsumer::class);
        $this->app->singleton(ConfigRuleResolver::class, ConfigRuleResolver::class);
        $this->app->singleton(ConfigTemplateRepository::class, ConfigTemplateRepository::class);
        $this->app->singleton(RuleResolverInterface::class, DatabaseRuleResolver::class);
        $this->app->singleton(TemplateRepositoryInterface::class, DatabaseTemplateRepository::class);
        $this->app->singleton(CommunicationServiceInterface::class, CommunicationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/communications');

        $this->registerUiRoutes();

        Event::listen(NotificationSent::class, CommunicationOutcomeListener::class);
        Event::listen(NotificationFailed::class, CommunicationOutcomeListener::class);
        Event::listen(CommunicationOrchestrated::class, CommunicationExposureListener::class);

        $this->publishes([
            __DIR__.'/../config/communications.php' => config_path('communications.php'),
        ], 'communications-config');

        $this->publishes([
            __DIR__.'/../database/migrations/communications' => database_path('migrations'),
        ], 'communications-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncNotificationEventsCommand::class,
            ]);
        }
    }

    protected function registerUiRoutes(): void
    {
        if (! config('communications.ui.enabled', true)) {
            return;
        }

        $prefix = trim((string) config('communications.ui.prefix', 'communications'), '/');
        $middleware = config('communications.ui.middleware', ['web']);
        $namePrefix = trim((string) config('communications.ui.name_prefix', 'communications.'), '.').'.';

        Route::middleware($middleware)
            ->prefix($prefix)
            ->as($namePrefix)
            ->group(__DIR__.'/../routes/communications-ui.php');
    }
}
