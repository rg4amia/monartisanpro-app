<?php

namespace App\Providers;

use App\Application\Handlers\NotificationEventHandler;
use App\Application\Handlers\IntegrationEventHandler;
use App\Domain\Financial\Events\LaborPaymentReleased;
use App\Domain\Marketplace\Events\MissionCreated;
use App\Domain\Marketplace\Events\QuoteSubmitted;
use App\Domain\Marketplace\Events\QuoteAccepted;
use App\Domain\Worksite\Events\MilestoneProofSubmitted;
use App\Domain\Worksite\Events\MilestoneValidated;
use App\Domain\Worksite\Events\ChantierCompleted;
use App\Domain\Dispute\Events\DisputeReported;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
 /**
  * The event to listener mappings for the application.
  *
  * @var array<class-string, array<int, class-string>>
  */
 protected $listen = [
  // Notification events
  MissionCreated::class => [
   [NotificationEventHandler::class, 'handleMissionCreated'],
  ],
  QuoteSubmitted::class => [
   [NotificationEventHandler::class, 'handleQuoteSubmitted'],
  ],
  MilestoneProofSubmitted::class => [
   [NotificationEventHandler::class, 'handleMilestoneProofSubmitted'],
  ],
  LaborPaymentReleased::class => [
   [NotificationEventHandler::class, 'handleLaborPaymentReleased'],
  ],

  // Integration events
  QuoteAccepted::class => [
   [IntegrationEventHandler::class, 'handleQuoteAccepted'],
  ],
  MilestoneValidated::class => [
   [IntegrationEventHandler::class, 'handleMilestoneValidated'],
  ],
  ChantierCompleted::class => [
   [IntegrationEventHandler::class, 'handleChantierCompleted'],
  ],
  DisputeReported::class => [
   [IntegrationEventHandler::class, 'handleDisputeReported'],
  ],
 ];

 /**
  * Register any events for your application.
  */
 public function boot(): void
 {
  parent::boot();

  // Register notification event handlers
  Event::listen(MissionCreated::class, function (MissionCreated $event) {
   app(NotificationEventHandler::class)->handleMissionCreated($event);
  });

  Event::listen(QuoteSubmitted::class, function (QuoteSubmitted $event) {
   app(NotificationEventHandler::class)->handleQuoteSubmitted($event);
  });

  Event::listen(MilestoneProofSubmitted::class, function (MilestoneProofSubmitted $event) {
   app(NotificationEventHandler::class)->handleMilestoneProofSubmitted($event);
  });

  Event::listen(LaborPaymentReleased::class, function (LaborPaymentReleased $event) {
   app(NotificationEventHandler::class)->handleLaborPaymentReleased($event);
  });

  // Register integration event handlers
  Event::listen(QuoteAccepted::class, function (QuoteAccepted $event) {
   app(IntegrationEventHandler::class)->handleQuoteAccepted($event);
  });

  Event::listen(MilestoneValidated::class, function (MilestoneValidated $event) {
   app(IntegrationEventHandler::class)->handleMilestoneValidated($event);
  });

  Event::listen(ChantierCompleted::class, function (ChantierCompleted $event) {
   app(IntegrationEventHandler::class)->handleChantierCompleted($event);
  });

  Event::listen(DisputeReported::class, function (DisputeReported $event) {
   app(IntegrationEventHandler::class)->handleDisputeReported($event);
  });
 }

 /**
  * Determine if events and listeners should be automatically discovered.
  */
 public function shouldDiscoverEvents(): bool
 {
  return false;
 }
}
