<?php

declare(strict_types=1);

namespace OCA\NoteHub\AppInfo;

use OCA\NoteHub\BackgroundJob\ReminderJob;
use OCA\NoteHub\Listener\FileChangeListener;
use OCA\NoteHub\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'notehub';
    public const NOTES_FOLDER = 'NoteHub';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerNotifierService(Notifier::class);

        $context->registerEventListener(NodeCreatedEvent::class, FileChangeListener::class);
        $context->registerEventListener(NodeWrittenEvent::class, FileChangeListener::class);
        $context->registerEventListener(NodeDeletedEvent::class, FileChangeListener::class);
        $context->registerEventListener(NodeRenamedEvent::class, FileChangeListener::class);
    }

    public function boot(IBootContext $context): void {
    }
}
