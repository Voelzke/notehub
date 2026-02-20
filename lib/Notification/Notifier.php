<?php

declare(strict_types=1);

namespace OCA\NoteHub\Notification;

use OCA\NoteHub\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
    private IFactory $l10nFactory;
    private IURLGenerator $urlGenerator;

    public function __construct(IFactory $l10nFactory, IURLGenerator $urlGenerator) {
        $this->l10nFactory = $l10nFactory;
        $this->urlGenerator = $urlGenerator;
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return $this->l10nFactory->get(Application::APP_ID)->t('NoteHub');
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== Application::APP_ID) {
            throw new UnknownNotificationException();
        }

        $l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
        $params = $notification->getSubjectParameters();

        switch ($notification->getSubject()) {
            case 'reminder':
                $title = $params['title'] ?? '';
                $due = $params['due'] ?? '';

                $notification->setParsedSubject(
                    $l->t('Erinnerung: %s', [$title])
                );

                if ($due !== '') {
                    $notification->setParsedMessage(
                        $l->t('Fällig am %s', [$due])
                    );
                } else {
                    $notification->setParsedMessage(
                        $l->t('Aufgabe erinnert')
                    );
                }

                $notification->setIcon(
                    $this->urlGenerator->getAbsoluteURL(
                        $this->urlGenerator->imagePath('core', 'actions/history.svg')
                    )
                );

                $notification->setLink(
                    $this->urlGenerator->linkToRouteAbsolute('notehub.page.index')
                );

                return $notification;

            default:
                throw new UnknownNotificationException();
        }
    }
}
