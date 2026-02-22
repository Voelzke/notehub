<?php

declare(strict_types=1);

namespace OCA\NoteHub\Controller;

use OCA\NoteHub\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

class PageController extends Controller {
    private IURLGenerator $urlGenerator;

    public function __construct(IRequest $request, IURLGenerator $urlGenerator) {
        parent::__construct(Application::APP_ID, $request);
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'notehub-main');
        Util::addStyle(Application::APP_ID, 'style');

        $manifestUrl = $this->urlGenerator->imagePath(Application::APP_ID, 'manifest.json');
        Util::addHeader('link', ['rel' => 'manifest', 'href' => $manifestUrl]);
        Util::addHeader('meta', ['name' => 'theme-color', 'content' => '#0082c9']);

        return new TemplateResponse(Application::APP_ID, 'main');
    }
}
