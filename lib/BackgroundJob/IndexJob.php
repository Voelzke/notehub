<?php

declare(strict_types=1);

namespace OCA\NoteHub\BackgroundJob;

use OCA\NoteHub\Service\IndexService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class IndexJob extends TimedJob {
    private IndexService $indexService;
    private IUserManager $userManager;
    private LoggerInterface $logger;

    public function __construct(
        ITimeFactory $time,
        IndexService $indexService,
        IUserManager $userManager,
        LoggerInterface $logger
    ) {
        parent::__construct($time);
        $this->indexService = $indexService;
        $this->userManager = $userManager;
        $this->logger = $logger;

        // Run every 5 minutes
        $this->setInterval(300);
    }

    protected function run($argument): void {
        $this->logger->debug('NoteHub IndexJob: starting incremental sync');

        $this->userManager->callForSeenUsers(function ($user) {
            $userId = $user->getUID();

            try {
                $result = $this->indexService->incrementalSync($userId);
                if ($result['updated'] > 0) {
                    $this->logger->info('NoteHub IndexJob: synced ' . $result['updated'] . ' changes for ' . $userId);
                }
            } catch (\Exception $e) {
                $this->logger->warning('NoteHub IndexJob: failed for user ' . $userId . ': ' . $e->getMessage());
            }
        });

        $this->logger->debug('NoteHub IndexJob: finished');
    }
}
