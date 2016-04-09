<?php
// ----------------------------- //
// Register services
// ----------------------------- //

use Pimple\Container;
use Parvula\Config;
use Parvula\FilesSystem as Files;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$app['config'] = function (Container $c) {
	$fp = $c['fileParser'];

	// Populate Config wrapper
	$config = new Config($fp->read(_CONFIG_ . 'system.yml'));

	// Load user config
	// Append user config to Config wrapper (override if exists)
	$userConfig = $fp->read(_CONFIG_ . $config->get('userConfig'));
	$config->append((array) $userConfig);

	return $config;
};

$app['router'] = function (Container $c) {
	$slimConf = [
		'settings' => [
			'displayErrorDetails' => !$c['config']->get('debug', false)
		],
		'api' => new Parvula\Router\APIRender(),
		'logger' => $c['loggerHandler']
	];

	$router = new Slim\App($slimConf);

	$container = $router->getContainer();
	$router->add(new \Slim\Middleware\JwtAuthentication([
		'path' => '/api',
		// true: If the middleware detects insecure usage over HTTP it will throw a RuntimeException
		'secure' => !$c['config']->get('debug', false),
		// 'cookie' => 'parvula_token',
		'passthrough' => [
			'/api/0/login',
			'/api/0/public'
		],
		'rules' => [
			// GET /api/0/pages
			function($arr) {
				$path = trim($arr->getUri()->getPath(), '/');
				if ($arr->isGet() && preg_match('~^api/0/pages~', $path)) {
					return false;
				}
			}
		],
		'secret' => $c['config']->get('secretToken'),
		'callback' => function ($request, $response, $arguments) use ($container) {
			$container['token'] = $arguments['decoded'];
		}
	]));

	// Remove Slim handler, we want to use our own
	unset($router->getContainer()['errorHandler']);

	return $router;
};

// Log errors in _LOG_ folder
$app['loggerHandler'] = function ($c) {
	if (class_exists('\\Monolog\\Logger')) {
		$logger = new Logger('parvula');
		$file = (new DateTime('now'))->format('Y-m-d') . '.log';

		$logger->pushProcessor(new Monolog\Processor\UidProcessor());
		$logger->pushHandler(new StreamHandler(_LOGS_ . $file, Logger::WARNING));

		Monolog\ErrorHandler::register($logger);

		return $logger;
	}

	return false;
};

$app['errorHandler'] = function (Container $c) {
	if (class_exists('League\\BooBoo\\Runner')) {
		$runner = new League\BooBoo\Runner();

		$accept = $c['router']->getContainer()['request']->getHeader('Accept');
		$accept = join(' ', $accept);

		// If we accept html, show html, else show json
		if (strpos($accept, 'html') !== false){
			$runner->pushFormatter(new Parvula\External\HtmlPrettyFormatter);
			// $runner->pushFormatter(new League\BooBoo\Formatter\HtmlTableFormatter);
		} else {
			$runner->pushFormatter(new League\BooBoo\Formatter\JsonFormatter);
		}

		$runner->register();

	} else if (class_exists('Whoops\\Run')) {
		$run = new Whoops\Run;
		$handler = new Whoops\Handler\PrettyPageHandler;

		$handler->setPageTitle('Parvula Error');
		$handler->addDataTable('Parvula', [
			'Version'=> _VERSION_
		]);

		$run->pushHandler($handler);

		if (Whoops\Util\Misc::isAjaxRequest()) {
			$run->pushHandler(new Whoops\Handler\JsonResponseHandler);
		}

		$run->register();

		if (class_exists('Monolog\\Logger')) {
			// Be sure that Monolog is still register
			Monolog\ErrorHandler::register($c['loggerHandler']);
		}

		return $run;
	}
};

// To parse serialized files in multiple formats
$app['fileParser'] = function () {
	$parsers = [
		'json' => new \Parvula\Parser\Json,
		'yaml' => new \Parvula\Parser\Yaml,
		'yml' => new \Parvula\Parser\Yaml,
		'php' => new \Parvula\Parser\Php
	];

	return new Parvula\FileParser($parsers);
};

$app['plugins'] = function (Container $this) {
	$pluginMediator = new Parvula\PluginMediator;
	$pluginMediator->attach(getPluginList($this['config']->get('disabledPlugins')));
	return $pluginMediator;
};

$app['session'] = function (Container $this) {
	$session = new Parvula\Session($this['config']->get('sessionName'));
	$session->start();
	return $session;
};

$app['auth'] = function (Container $this) {
	return new Parvula\Authentication($this['session'], hash('sha1', '@TODO'));
	// return new Parvula\Authentication($this['session'], hash('sha1', $this['request']->ip . $this['request']->userAgent));
};

// Get current logged User if available
$app['usersession'] = function (Container $this) {
	$sess = $this['session'];
	if ($username = $sess->get('username')) {
		if ($this['auth']->isLogged($username)) {
			return $this['users']->read($username);
		}
	}

	return false;
};

//-- ModelMapper --

$app['pageRenderer'] = function (Container $this) {
	$contentParser = $this['config']->get('contentParser');
	$pageRenderer = $this['config:mapper']->get('pageRenderer');

	$headParser = $this['config:mapper']->get('headParser');
	$options = $this['config:mapper']->get('pageRendererOptions');

	if ($headParser === null) {
		return new $pageRenderer(new $contentParser, $options);
	}

	return new $pageRenderer(new $headParser, new $contentParser, $options);
};

$app['pageRendererRAW'] = function (Container $this) {
	$headParser = $this['config']->get('headParser');
	$pageRenderer = $this['config']->get('pageRenderer');

	if ($headParser === null) {
		return new $pageRenderer(new Parvula\ContentParser\None);
	}

	return new $pageRenderer(new $headParser, new Parvula\ContentParser\None);
};

$app['mongodb'] = function (Container $this) {
	$fp = $this['fileParser'];

	$config = new Config($fp->read(_CONFIG_ . 'mappers.yml'));

	if (isset($config->get('mongodb')['username'], $config->get('mongodb')['password'])) {
		$mongoUri = "mongodb://{$config->get('mongodb')['username']}
			:{$config->get('mongodb')['password']}@127.0.0.1";
	}

	$client = new MongoDB\Client($mongoUri);
	$db = $client->{$config->get('mongodb')['name']};

	return $db;
};

$app['mappers'] = function (Container $c) {
	$conf = $c['config:mapper'];
	$mapperName = $conf->get('mapperName');

	$mappers = [
		'mongodb' => [
			'pages' => function () use ($c) {
				return new Parvula\Model\Mapper\PagesMongo($c['pageRenderer'], $c['mongodb']->pages);
			},
			'users' => function () use ($c) {
				return new Parvula\Model\Mapper\UsersMongo($c['mongodb']->users);
			}
		],
		'flatfiles' => [
			'pages' => function () use ($c, $conf) {
				return new Parvula\Model\Mapper\PagesFlatFiles($c['pageRenderer'], _PAGES_, $conf->get('fileExtension'));
			},
			'users' => function () use ($c) {
				return new Parvula\Model\Mapper\Users($c['fileParser'], _USERS_ . '/users.php');
			}
		]
	];

	if (!isset($mappers[$mapperName])) {
		throw new Exception(
			'Mapper `' . htmlspecialchars($mapperName) . '` does not exists, please edit your settings.');

	}

	return $mappers[$mapperName];
};

$app['config:mapper'] = function (Container $c) {
	$fp = $c['fileParser'];
	$mappersConfig = new Config($fp->read(_CONFIG_ . 'mappers.yml'));
	$mapperName = $mappersConfig->get('mapper');

	$conf = new Config($mappersConfig->get($mapperName));
	$conf->set('mapperName', $mapperName);

	return $conf;
};

// Aliases
$app['pages'] = $app['mappers']['pages'];

$app['users'] = $app['mappers']['users'];

$app['themes'] = function (Container $c) {
	return new Parvula\Model\Mapper\Themes(_THEMES_, $c['fileParser']);
};

$app['theme'] = function (Container $this) {
	if ($this['themes']->has($themeName = $this['config']->get('theme'))) {
		return $this['themes']->read($themeName);
	} else {
		throw new Exception('Theme `' . $themeName . '` does not exists');
	}
};

$app['view'] = function (Container $app) {
	$theme = $app['theme'];
	$config = $app['config'];

	// Create new Plates instance to render theme files
	$path = $theme->getPath();
	$view = new League\Plates\Engine($path, $theme->getExtension());

	// Helper function
	// List pages
	$view->registerFunction('listPages', function ($pages, $options) {
		return listPagesAndChildren(listPagesRoot($pages), $options);
	});

	// System date format
	$view->registerFunction('dateFormat', function (DateTime $date) use ($config) {
		return $date->format($config->get('dateFormat'));
	});

	// System date format
	$view->registerFunction('pageDateFormat', function (Parvula\Model\Page $page) use ($config) {
		return $page->getDateTime()->format($config->get('dateFormat'));
	});

	// Excerpt strings
	$view->registerFunction('excerpt', function ($text, $length = 275) {
		$text = strip_tags($text);
		$excerpt = substr($text, 0, $length);
		if ($excerpt !== $text) {
			$lastDot = strrpos($excerpt, '. ');
			if ($lastDot === false) {
				$lastDot = strrpos($excerpt, ' ');
			}
			$excerpt = substr($excerpt, 0, $lastDot);
		}
		return $excerpt;
	});

	// Register folder begining with a '_' as Plates folder
	// (Plates will resolve `this->fetch('myFolder::file')` as `_myFolder/file.html`)
	$filter = function ($current) {
		// Must be a dir begining with _
		return $current->isDir() && $current->getFilename()[0] === '_';
	};

	(new Files($path))->index('', function (\SplFileInfo $file, $dir) use ($path, $view) {
		$view->addFolder(substr($file->getFileName(), 1), $path . $dir . '/' . $file->getFileName());
	}, $filter);

	return $view;
};