<?php

declare(strict_types=1);

namespace OCA\NoteHub\BackgroundJob;

use OCA\NoteHub\AppInfo\Application;
use OCA\NoteHub\Service\NoteService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use OCP\L10N\IFactory as IL10NFactory;
use Psr\Log\LoggerInterface;

class ReminderJob extends TimedJob {
    private NoteService $noteService;
    private INotificationManager $notificationManager;
    private IMailer $mailer;
    private IUserManager $userManager;
    private LoggerInterface $logger;
    private IL10NFactory $l10nFactory;

    public function __construct(
        ITimeFactory $time,
        NoteService $noteService,
        INotificationManager $notificationManager,
        IMailer $mailer,
        IUserManager $userManager,
        LoggerInterface $logger,
        IL10NFactory $l10nFactory
    ) {
        parent::__construct($time);
        $this->noteService = $noteService;
        $this->notificationManager = $notificationManager;
        $this->mailer = $mailer;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->l10nFactory = $l10nFactory;

        // Run every 5 minutes
        $this->setInterval(300);
    }

    protected function run($argument): void {
        $this->logger->debug('NoteHub ReminderJob: starting');

        $this->userManager->callForSeenUsers(function ($user) {
            $userId = $user->getUID();

            try {
                $pending = $this->noteService->findPendingReminders($userId);
            } catch (\Exception $e) {
                $this->logger->warning('NoteHub ReminderJob: failed to scan user ' . $userId . ': ' . $e->getMessage());
                return;
            }

            foreach ($pending as $note) {
                $this->logger->info('NoteHub ReminderJob: sending reminder for "' . $note['title'] . '" to ' . $userId);

                // Send Nextcloud notification (bell)
                $this->sendNotification($userId, $note);

                // Send email
                $this->sendEmail($user, $note);

                // Mark as reminded
                try {
                    $this->noteService->markReminded($note['id'], $userId);
                } catch (\Exception $e) {
                    $this->logger->error('NoteHub ReminderJob: failed to mark reminded for note ' . $note['id'] . ': ' . $e->getMessage());
                }
            }
        });

        $this->logger->debug('NoteHub ReminderJob: finished');
    }

    private function sendNotification(string $userId, array $note): void {
        try {
            $notification = $this->notificationManager->createNotification();
            $notification
                ->setApp(Application::APP_ID)
                ->setUser($userId)
                ->setDateTime(new \DateTime())
                ->setObject('note', (string)$note['id'])
                ->setSubject('reminder', [
                    'title' => $note['title'],
                    'due' => $note['due'] ?? '',
                ]);

            $this->notificationManager->notify($notification);
        } catch (\Exception $e) {
            $this->logger->error('NoteHub ReminderJob: notification failed for note ' . $note['id'] . ': ' . $e->getMessage());
        }
    }

    private function sendEmail($user, array $note): void {
        $email = $user->getEMailAddress();
        if (empty($email)) {
            return;
        }

        try {
            $l = $this->l10nFactory->get('notehub', $this->l10nFactory->getUserLanguage($user));
            $subject = $l->t('NoteHub Reminder: %s', [$note['title']]);

            $body = $l->t('Task') . ": " . $note['title'] . "\n";
            if (!empty($note['due'])) {
                $body .= $l->t('Due') . ": " . $note['due'] . "\n";
            }
            if (!empty($note['person'])) {
                $body .= $l->t('Assignee') . ": " . $note['person'] . "\n";
            }
            $body .= "\n" . $l->t('This reminder was sent by NoteHub.');

            $message = $this->mailer->createMessage();
            $message->setSubject($subject);
            $message->setTo([$email]);
            $message->setPlainBody($body);

            $failedRecipients = $this->mailer->send($message);
            if (!empty($failedRecipients)) {
                $this->logger->warning('NoteHub ReminderJob: email delivery failed for ' . $email);
            }
        } catch (\Exception $e) {
            $this->logger->error('NoteHub ReminderJob: email send error: ' . $e->getMessage());
        }
    }
}
