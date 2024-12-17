<?php

declare(strict_types=1);

/**
 * Skeleton Core Application class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Application;

use Skeleton\Application\Web\Module;
use Skeleton\Core\Http\Media;
use Skeleton\Core\Http\Session;

class Web extends \Skeleton\Core\Application {
	/**
	 * Media Path
	 *
	 * @access public
	 */
	public ?string $media_path = null;

	/**
	 * Module Path
	 *
	 * @access public
	 */
	public ?string $module_path = null;

	/**
	 * Module namespace
	 *
	 * @access public
	 */
	public ?string $module_namespace = null;

	/**
	 * Template path
	 *
	 * @ccess public
	 */
	public ?string $template_path = null;

	/**
	 * Template path
	 *
	 * @ccess public
	 */
	public ?\Skeleton\I18n\Language $language = null;

	/**
	 * Run the application
	 *
	 * @access public
	 */
	public function run(): void {
		/**
		 * Handle the media
		 */
		$is_media = false;

		if (
			isset($this->config->detect_media) &&
			$this->config->detect_media === true ||
			!isset($this->config->detect_media)
		) {
			try {
				$is_media = Media::detect($this->request_relative_uri);
			} catch (\Skeleton\Core\Exception\Media\Not\Found $e) {
				\Skeleton\Core\Http\Status::code_404('media');
			}
		}

		if ($is_media === true) {
			exit;
		}

		/**
		 * Start the session
		 */
		$session_properties = [];
		Session::start($session_properties);

		/**
		 * Find the module to load
		 */
		$module = null;
		try {
			// Attempt to find the module by matching defined routes
			$module = $this->route($this->request_relative_uri);
		} catch (\Exception $e) {
			try {
				// Attempt to find a module by matching paths
				$module = Module::resolve($this->request_relative_uri);
			} catch (\Exception $e) {
				$this->detect_language();
				$this->call_event('module', 'not_found');
			}
		}

		/**
		 * Set language
		 */
		if (!isset($this->language) || $this->language === null) {
			$this->detect_language();
		}

		/**
		 * Validate CSRF
		 */
		$csrf = \Skeleton\Application\Web\Security\Csrf::get();

		if ($session_properties['resumed'] === true && !$csrf->validate()) {
			$this->call_event('security', 'csrf_validate_failed');
		}

		/**
		 * Check for replays
		 */
		$replay = \Skeleton\Application\Web\Security\Replay::get();
		if ($replay->check() === false) {
			$this->call_event('security', 'replay_detected');
		}

		if ($module !== null) {
			$this->call_event('module', 'bootstrap', [ $module ]);
			$module->accept_request();
			$this->call_event('module', 'teardown', [ $module ]);
		}
	}

	/**
	 * Search module
	 *
	 * @access public
	 */
	public function route(string $request_uri): object {
		/**
		 * Remove leading slash
		 */
		if ($request_uri[0] === '/') {
			$request_uri = substr($request_uri, 1);
		}

		if (substr($request_uri, -1) === '/') {
			$request_uri = substr($request_uri, 0, strlen($request_uri) - 1);
		}

		if (strpos('/' . $request_uri, $this->config->base_uri) === 0) {
			$request_uri = substr($request_uri, strlen($this->config->base_uri) - 1);
		}
		$request_parts = explode('/', $request_uri);

		$routes = $this->config->routes;

		/**
		 * We need to find the route that matches the most the fixed parts
		 */
		$matched_module = null;
		$best_matches_fixed_parts = 0;
		$route = '';

		foreach ($routes as $module => $uris) {
			foreach ($uris as $uri) {
				if (isset($uri[0]) and $uri[0] === '/') {
					$uri = substr($uri, 1);
				}
				$parts = explode('/', $uri);
				$matches_fixed_parts = 0;
				$match = true;

				foreach ($parts as $key => $value) {
					if (!isset($request_parts[$key])) {
						$match = false;
						continue;
					}

					if ($value === $request_parts[$key]) {
						$matches_fixed_parts++;
						continue;
					}

					if (isset($value[0]) and $value[0] === '$') {
						preg_match_all('/(\[(.*?)\])/', $value, $matches);
						if (!isset($matches[2][0])) {
							/**
							 *  There are no possible values for the variable
							 *  The match is valid
							 */
							continue;
						}

						$possible_values = explode(',', $matches[2][0]);

						$variable_matches = false;
						foreach ($possible_values as $possible_value) {
							if ($request_parts[$key] === $possible_value) {
								$variable_matches = true;
							}
						}

						if (!$variable_matches) {
							$match = false;
						}

						// This is a variable, we do not increase the fixed parts
						continue;
					}
					$match = false;
				}

				if ($match and count($parts) === count($request_parts)) {
					if ($matches_fixed_parts >= $best_matches_fixed_parts) {
						$best_matches_fixed_parts = $matches_fixed_parts;
						$route = $uri;
						$matched_module = $module;
					}
				}
			}
		}

		if ($matched_module === null) {
			throw new \Exception('No matching route found');
		}

		/**
		 * We now have the correct route
		 * Now fill in the GET-parameters
		 */
		$parts = explode('/', $route);

		foreach ($parts as $key => $value) {
			if (isset($value[0]) and $value[0] === '$') {
				$value = substr($value, 1);
				if (strpos($value, '[') !== false) {
					$value = substr($value, 0, strpos($value, '['));
				}
				$_GET[$value] = $request_parts[$key];
				$_REQUEST[$value] = $request_parts[$key];
			}
		}

		if (!class_exists($matched_module)) {
			throw new \Exception('Incorrect classname for matching route');
		}

		return new $matched_module();
	}

	/**
	 * Get details
	 *
	 * @access protected
	 */
	protected function get_details(): void {
		parent::get_details();

		$this->media_path = $this->path . '/media/';
		$this->template_path = $this->path . '/template/';
		$this->module_path = $this->path . '/module/';
		$this->module_namespace = '\\App\\' . ucfirst($this->name) . "\Module\\";

		$autoloader = new \Skeleton\Core\Autoloader();
		$autoloader->add_namespace($this->module_namespace, $this->module_path);
		$autoloader->register();

		/**
		 * Configure translation: default = po storage
		 */
		if (class_exists('\Skeleton\I18n\Translator')) {
			$translator = $this->call_event('i18n', 'get_translator');
			if ($translator !== null) {
				$translator->save();
			}
		}
	}

	/**
	 * Load the config
	 *
	 * @access private
	 */
	protected function load_config(): void {
		/**
		 * Set some defaults
		 */
		$this->config->session_name = 'APP';
		$this->config->sticky_session_name = 'sys_sticky_session';
		$this->config->csrf_enabled = false;
		$this->config->replay_enabled = false;
		$this->config->hostnames = [];
		$this->config->routes = [];
		$this->config->default_language = 'en';
		$this->config->module_default = 'index';
		$this->config->sticky_pager = false;
		$this->config->base_uri = '/';
		$this->config->route_resolver = static function($path) {
			return \Skeleton\Application\Web\Module::resolve($path);
		};

		parent::load_config();
	}

	/**
	 * Get events
	 *
	 * Get a list of events for this application.
	 * The returned array has the context as key, the value is the classname
	 * of the default event
	 *
	 * @access protected
	 * @return array<string>
	 */
	protected function get_events(): array {
		$parent_events = parent::get_events();
		$web_events = [
			'I18n' => '\\Skeleton\\Application\\Web\\Event\\I18n',
			'Security' => '\\Skeleton\\Application\\Web\\Event\\Security',
			'Module' => '\\Skeleton\\Application\\Web\\Event\\Module',
			'Rewrite' => '\\Skeleton\\Application\\Web\\Event\\Rewrite',
		];
		return array_merge($parent_events, $web_events);
	}

	/**
	 * Handle i18n
	 *
	 * @access private
	 */
	private function detect_language(): void {
		if (!class_exists('\Skeleton\I18n\Config')) {
			/**
			 * Skeleton-i18n is not installed, nothing to do
			 */
			return;
		}

		if (!isset(\Skeleton\I18n\Config::$language_interface)) {
			/**
			 * This should not happen, there is a default set
			 */
			return;
		}

		$language_interface = \Skeleton\I18n\Config::$language_interface;

		if (!class_exists($language_interface)) {
			throw new \Exception('The language interface does not exists: ' . $language_interface);
		}

		/**
		 * Try to set the language
		 */
		$_SESSION['language'] = $this->call_event('i18n', 'detect_language');

		$this->language = $_SESSION['language'];

		if (class_exists('\Skeleton\I18n\Translator')) {
			$translator = $this->call_event('i18n', 'get_translator');
			if ($translator !== null) {
				$template = \Skeleton\Application\Web\Template::get();
				$translation = $translator->get_translation($this->language);
				$template->set_translation($translation);
			}
		}
	}
}
