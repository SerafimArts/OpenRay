<?php

declare(strict_types=1);

namespace Serafim\OpenRay;

use Boson\Application as BosonApplication;
use Boson\ApplicationCreateInfo;
use Boson\Bridge\Static\FilesystemStaticAdapter;
use Boson\Event\ApplicationStarted;
use Boson\WebView\Event\WebViewFaviconChanging;
use Boson\WebView\Event\WebViewRequest;
use Boson\WebView\Event\WebViewTitleChanging;
use Boson\WebView\WebView;
use Boson\WebView\WebViewCreateInfo;
use Boson\Window\Event\WindowStateChanged;
use Boson\Window\Window;
use Boson\Window\WindowCreateInfo;
use Boson\Window\WindowDecoration;
use Boson\Window\WindowState;
use Symfony\Component\VarDumper\Cloner\Data;

final readonly class Application
{
    private BosonApplication $app;

    /**
     * @var \SplObjectStorage<ConcurrentDumpServer, mixed>
     */
    private \SplObjectStorage $connections;

    private FilesystemStaticAdapter $static;

    private OpenRayHtmlDumper $dumper;

    public function __construct(?string $library = null)
    {
        $this->connections = new \SplObjectStorage();
        $this->dumper = new OpenRayHtmlDumper();

        $this->static = new FilesystemStaticAdapter([
            __DIR__ . '/../public',
        ]);

        $this->app = new BosonApplication(new ApplicationCreateInfo(
            schemes: ['boson'],
            debug: false,
            library: $library,
            window: new WindowCreateInfo(
                title: 'OpenRay',
                width: 800,
                height: 600,
                //alwaysOnTop: true,
                decoration: WindowDecoration::Transparent,
                webview: new WebViewCreateInfo(
                    url: 'boson://open-ray/index.html',
                    contextMenu: false,
                ),
            ),
        ));

        $this->bootApplication($this->app);
        $this->bootWindow($this->app->window);
        $this->bootWebView($this->app->webview);

        $this->bootDumpServer();
    }

    private function bootApplication(BosonApplication $app): void
    {
        $app->on($this->onAppStarted(...));
    }

    private function bootDumpServer(): void
    {
        $this->addServerConnection(9912);
    }

    /**
     * @param int<1, 65535> $port
     */
    private function addServerConnection(int $port): void
    {
        $this->connections->attach(new ConcurrentDumpServer(
            'tcp://127.0.0.1:' . $port,
        ));
    }

    private function bootWindow(Window $window): void
    {
        $window->min->update(320, 240);

        $window->on($this->onStateChange(...));
    }

    private function bootWebView(WebView $webview): void
    {
        $webview->bind('window.drag', $this->app->window->startDrag(...));
        $webview->bind('window.close', $this->app->window->close(...));
        $webview->bind('window.minimize', $this->app->window->minimize(...));
        $webview->bind('window.maximize', $this->app->window->maximize(...));
        $webview->bind('window.restore', $this->app->window->restore(...));

        $webview->on($this->onRequest(...));
        $webview->on($this->onIconChanging(...));
        $webview->on($this->onTitleChanging(...));
    }

    private function onStateChange(WindowStateChanged $e): void
    {
        $state = \strtolower($e->state->name);
        $restoreVisibility = 'none';
        $maximizeVisibility = 'block';

        if ($e->state === WindowState::Maximized) {
            $restoreVisibility = 'block';
            $maximizeVisibility = 'none';
        }

        $e->subject->webview->eval(
            code: <<<JS
                document.querySelector('[data-button-id="restore"]')
                    .style.display = '{$restoreVisibility}';
                document.querySelector('[data-button-id="maximize"]')
                    .style.display = '{$maximizeVisibility}';
                document.body.setAttribute('data-window-state', '{$state}');
                JS,
        );
    }

    private function onTitleChanging(WebViewTitleChanging $e): void
    {
        $e->cancel();
    }

    private function onIconChanging(WebViewFaviconChanging $e): void
    {
        $e->cancel();
    }

    private function onAppStarted(ApplicationStarted $e): void
    {
        while ($e->subject->poller->next()) {
            foreach ($this->connections as $connection) {
                /**
                 * @var Data $data
                 * @var array<array-key, mixed> $context
                 */
                foreach ($connection->listen() as $id => [$data, $context]) {
                    $this->onMessageReceived($data, $context);
                }
            }
        }
    }

    private function onMessageReceived(Data $data, array $context): void
    {
        $this->app->window->focus();

        try {
            [$id, $html] = $this->dumper->dumpWithInfo($data, [
                'maxDepth' => 10,
            ]);

            $html = \base64_encode($html);

            $code = "document.getElementById('content').innerHTML = atob(`$html`);";

            if ($id !== null) {
                $code .= \sprintf('Sfdump(%s, {"maxDepth":10});', \json_encode($id));
            }

            $this->app->webview->eval($code);
        } catch (\Throwable $e) {
            $this->app->webview->eval(\sprintf('alert(`%s`)', \addcslashes((string) $e, '\\')));
        }
    }

    private function onRequest(WebViewRequest $e): void
    {
        $e->response = $this->static->lookup($e->request);
    }
}
