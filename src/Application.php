<?php

namespace Coursework\Chat;

use Facebook\Facebook;
use Silex\Application\UrlGeneratorTrait;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MemcacheSessionHandler;

class Application extends \Silex\Application
{
    use UrlGeneratorTrait;

    public function __construct(array $values = array())
    {
        parent::__construct($values);

        $app = $this;

        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new SessionServiceProvider());

        $app['session.storage.handler'] = $app->share(function ($app) {
            $memcache = new \Memcache();
            $memcache->connect($app['memcache.host'], $app['memcache.port']);
            return new MemcacheSessionHandler($memcache);
        });

        $app['facebook'] = $app->share(function () use ($app) {
            return new Facebook([
                'app_id' => $app['facebook.app_id'],
                'app_secret' => $app['facebook.secret'],
                'default_graph_version' => 'v3.0',
            ]);
        });

        $app['user'] = function () use ($app) {
            return $app['session']->get('user');
        };

        $app->get('/login', function () use($app) {
            /** @var Application $app */
            /** @var Facebook $facebook */
            $facebook = $app['facebook'];

            $helper = $facebook->getRedirectLoginHelper();

            if (!$accessToken = $app['session']->get('fb_access_token')) {
                $_SESSION['FBRLH_state'] = $_GET['state'];
                $accessToken = $helper->getAccessToken();
                $app['session']->set('fb_access_token', $accessToken);
            }

            $response = $facebook->get('/me?fields=id,name,picture', $accessToken);

            $user = $response->getGraphUser();
            $app['session']->set('user', [
                'uid' => $user->getId(),
                'name' => $user->getName(),
                'pic_square' => $user->getPicture()->getUrl()
            ]);

            return $app->redirect($app->url('index'));
        })->bind('login')->requireHttps();

        $app->before(function ($request) use ($app) {
            $user = $app['user'];

            if (null === $user && $request->get('_route') != 'login') {
                /** @var Facebook $facebook */
                $facebook = $app['facebook'];
                $helper = $facebook->getRedirectLoginHelper();
                $permissions = ['email'];
                $loginUrl = $helper->getLoginUrl($app->url('login'), $permissions);

                return $app->render('login.phtml', [
                    'loginUrl' => $loginUrl,
                ]);
            }
        });
    }

    public function render($viewPath, $params = [])
    {
        $app = $this;
        $viewPath = $app['view_dir'] . $viewPath;
        $basePath = $app['request']->getBasePath();
        extract($params);

        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        return new Response($content);
    }
}
