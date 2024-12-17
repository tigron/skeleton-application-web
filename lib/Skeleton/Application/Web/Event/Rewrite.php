<?php

declare(strict_types=1);

/**
 * Module Context
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Application\Web\Event;

class Rewrite extends \Skeleton\Core\Application\Event {

	/**
	 * Reverse rewrite the HTML which is generated
	 *
	 * @access public
	 * @param string $html
	 * @return string $html
	 */
	public function reverse(string $html): string {
		$html = preg_replace_callback(
			'@\<([^>]*)\s(href|src|action)="\/(?!\/)([^"]*?)@iU',
			function ($matches) {
				if (!isset($matches[3])) {
					return $matches[0];
				}

				$uri = $this->reverse_uri($matches[3]);
				return str_replace('/' . $matches[3], $uri, $matches[0]);
			},
			$html
		);

		return $html;
	}

	/**
	 * Reverse rewrite 1 uri
	 *
	 * @access public
	 * @param string $html
	 * @return string $html
	 */
	public function reverse_uri(string $uri): string {
		$uri = $this->reverse_uri_routes($uri);

		if (
			isset($this->application->config->base_uri) 
			and $this->application->config->base_uri !== null
		) {
			$uri = trim($this->application->config->base_uri, '/') . '/' . trim($uri, '/');
		}

		// We don't support relative URIs at all
		if (strpos($uri, '/') !== 0) {
			$uri = '/' . $uri;
		}

		return $uri;
	}

	/**
	 * Reverse rewrite based on defined routes
	 *
	 * @access private
	 * @return string $reverse_rewrite
	 */
	private function reverse_uri_routes(string $url_raw): string {
		$url = parse_url($url_raw);
		$params = [];

		$application = $this->application;
		$routes = $application->config->routes;

		if (isset($url['query'])) {
			// Allow &amp; instead of &
			$url['query'] = str_replace('&amp;', '&', $url['query']);
			parse_str($url['query'], $params);
		}

		/**
		 * Retrieve extra parameters
		 */
		$extra_parameters = $this->reverse_uri_route_parameters();
		foreach ($extra_parameters as $key => $value) {
			// Don't overwrite params
			if (isset($params[$key])) {
				continue;
			}
			$params[$key] = $value;
		}

		/**
		 * Search for the requested module
		 */
		if (!isset($url['path'])) {
			return $url_raw;
		}
		if ($url['path'] !== '' and $url['path'][0] === '/') {
			$url['path'] = substr($url['path'], 1);
		}

		$classname = null;

		try {
			$closure = $application->config->route_resolver;
			$classname = '\\' . get_class($closure($url['path']));
		} catch (\Exception $e) {
			// No suitable classname found, reverse rewrite not possible
		}

		$module_defined = false;

		if (isset($routes[$classname])) {
			$module_defined = true;
		}

		if (!$module_defined) {
			return $url_raw;
		}

		$routes = $routes[$classname];

		$correct_route = null;
		$correct_route_params_matches = 0;

		foreach ($routes as $route) {
			$route_parts = explode('/', $route);
			$route_part_matches = 0;

			foreach ($route_parts as $key => $route_part) {
				if (trim($route_part) === '') {
					unset($route_parts[$key]);
					continue;
				}
				if ($route_part[0] !== '$') {
					$route_part_matches++;
					continue;
				}
				/**
				 * $language[en,nl] => language[en,nl]
				 */
				$route_part = substr($route_part, 1);

				/**
				 * Fetch required values
				 */
				$required_values = [];
				preg_match_all('/(\[(.*?)\])/', $route_part, $matches);
				if (count($matches[2]) > 0) {
					/**
					 * There are required values, parse them
					 */
					$required_values = explode(',', $matches[2][0]);
					$route_part = str_replace($matches[0][0], '', $route_part);
					$route_parts[$key] = '$' . $route_part;
				}

				if (isset($params[$route_part])) {
					/**
					 * if there are no required values => Proceed
					 */
					if (count($required_values) === 0) {
						$route_part_matches++;
						continue;
					}

					/**
					 * Check the required values
					 */
					$values_ok = false;
					foreach ($required_values as $required_value) {
						if ($required_value === $params[$route_part]) {
							$values_ok = true;
						}
					}

					if ($values_ok) {
						$route_part_matches++;
						continue;
					}
				}
			}

			if ($route_part_matches === count($route_parts) and $route_part_matches > $correct_route_params_matches) {
				$correct_route = $route_parts;
				$correct_route_params_matches = $route_part_matches;
			}
		}

		if ($correct_route === null) {
			return $url_raw;
		}
		$new_url = '';
		foreach ($correct_route as $url_part) {
			if ($url_part[0] !== '$') {
				$new_url .= '/' . $url_part;
				continue;
			}

			$url_part = substr($url_part, 1);
			$new_url .= '/' . $params[$url_part];
			unset($params[$url_part]);
		}

		/**
		 * Remove all extra_parameters from params
		 */
		foreach ($extra_parameters as $key => $value) {
			unset($params[$key]);
		}

		/**
		 * If the first character is a /, remove it
		 */
		if ($new_url[0] === '/') {
			$new_url = substr($new_url, 1);
		}

		if (count($params) > 0) {
			$new_url .= '?' . http_build_query($params);
		}

		/**
		 * Is there a fragment ('#') available?
		 */
		if (isset($url['fragment'])) {
			$new_url .= '#' . $url['fragment'];
		}
		return $new_url;
	}

	/**
	 * Returns an array with extra route params
	 * This method needs to return an array_fill
	 * key = variable in route to be replaced
	 * value = value to which it should be replaced
	 *
	 * Default = 
	 * [
	 * 		'language' => $current_language
	 * ]
	 *
	 * @access protected
	 * @return array $parameters
	 */	 
	protected function reverse_uri_route_parameters(): array {	
		$parameters = [];
		if (isset($this->application->language) ) {
			$parameters['language'] = $this->application->language->name_short;
		}
		return $parameters;
	}

}
