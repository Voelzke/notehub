<?php

declare(strict_types=1);

namespace OCA\NoteHub\Service;

use OCP\Contacts\IManager;

class ContactService {
    private IManager $contactsManager;

    public function __construct(IManager $contactsManager) {
        $this->contactsManager = $contactsManager;
    }

    /**
     * Search Nextcloud address books for contacts matching the query.
     */
    public function searchContacts(string $query): array {
        if (strlen($query) < 1) {
            return [];
        }

        $results = $this->contactsManager->search($query, ['FN', 'ORG', 'EMAIL'], ['limit' => 20]);
        $contacts = [];

        foreach ($results as $contact) {
            $name = $contact['FN'] ?? '';
            if ($name === '') {
                continue;
            }

            $org = '';
            if (!empty($contact['ORG'])) {
                $org = is_array($contact['ORG']) ? ($contact['ORG'][0] ?? '') : $contact['ORG'];
            }

            $email = '';
            if (!empty($contact['EMAIL'])) {
                $email = is_array($contact['EMAIL']) ? ($contact['EMAIL'][0] ?? '') : $contact['EMAIL'];
            }

            $phone = '';
            if (!empty($contact['TEL'])) {
                $phone = is_array($contact['TEL']) ? ($contact['TEL'][0] ?? '') : $contact['TEL'];
            }

            $uid = '';
            if (!empty($contact['UID'])) {
                $uid = is_array($contact['UID']) ? ($contact['UID'][0] ?? '') : $contact['UID'];
            }

            $contacts[] = [
                'name' => $name,
                'company' => $org,
                'email' => $email,
                'phone' => $phone,
                'uid' => $uid,
            ];
        }

        return $contacts;
    }
}
